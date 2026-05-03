<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

$pageTitle  = 'Route Finder';
$activePage = 'route';

// Load locations for dropdowns
$locations = mysqli_query($conn, "SELECT location_id, location_name, area_zone FROM location ORDER BY location_name");
$loc_list  = [];
while ($l = mysqli_fetch_assoc($locations)) $loc_list[] = $l;

// Load transport modes
$transports = mysqli_query($conn, "SELECT * FROM transport_mode ORDER BY transport_type");
$trans_list = [];
while ($t = mysqli_fetch_assoc($transports)) $trans_list[] = $t;

// ── Pre-fill from user preferences ────────────────────────
$prefill_budget = '';
if (isLoggedIn()) {
    $uid_pref  = (int)$_SESSION['user_id'];
    $user_pref = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT preferred_budget, preferred_time FROM user WHERE user_id=$uid_pref"));
    if ($user_pref && !empty($user_pref['preferred_budget'])) {
        $prefill_budget = $user_pref['preferred_budget'];
    }
}

$routes   = [];
$searched = false;
$msg      = '';

// ── START TRIP ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_trip'])) {
    if (isLoggedIn()) {
        $uid = (int)$_SESSION['user_id'];
        if ($uid === 0) {
            $msg = "Demo admin cannot log trips. Please log in as a regular user.";
        } else {
            $rid = (int)$_POST['trip_route_id'];
            $tc  = (float)$_POST['trip_cost'];
            $tt  = (int)$_POST['trip_time'];
            mysqli_query($conn,
                "INSERT INTO trip_history (user_id, route_id, travel_time, travel_cost, trip_date)
                 VALUES ($uid, $rid, $tt, $tc, NOW())");
            $msg = "Trip successfully started! It has been logged to your Trip History.";
        }
    } else {
        $msg = "Please log in to start a trip.";
    }
}

// ── SEARCH ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $src  = (int)$_POST['source'];
    $dst  = (int)$_POST['destination'];
    $budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? (float)$_POST['budget'] : 99999;
    $sort   = $_POST['sort'] ?? 'estimated_time';
    $allowed_sorts = ['estimated_time','estimated_cost','total_distance'];
    if (!in_array($sort, $allowed_sorts)) $sort = 'estimated_time';

    $searched = true;

    if ($src === $dst) {
        $msg = 'Source and destination cannot be the same.';
    } else {
        $sql = "SELECT R.route_id, R.total_distance, R.estimated_time, R.estimated_cost,
                       SL.location_name AS source_name, DL.location_name AS dest_name,
                       GROUP_CONCAT(DISTINCT TM.transport_type ORDER BY RS.segment_id SEPARATOR ' → ') AS transport_chain,
                       MIN(RS.segment_cost) AS min_seg_cost,
                       MAX(RS.segment_cost) AS max_seg_cost
                FROM route R
                JOIN location SL ON R.source_location_id      = SL.location_id
                JOIN location DL ON R.destination_location_id = DL.location_id
                LEFT JOIN route_segment RS ON RS.route_id     = R.route_id
                LEFT JOIN transport_mode TM ON RS.transport_id = TM.transport_id
                WHERE R.source_location_id = $src
                  AND R.destination_location_id = $dst
                GROUP BY R.route_id
                ORDER BY $sort ASC
                LIMIT 10";

        $res = mysqli_query($conn, $sql);
        while ($r = mysqli_fetch_assoc($res)) $routes[] = $r;

        // ── CACHE RESULT ─────────────────────────────────
        if (!empty($routes)) {
            $route_json = mysqli_real_escape_string($conn, json_encode($routes));
            mysqli_query($conn,
                "INSERT INTO cached_routes (source_location_id, destination_location_id, route_data, calculated_time)
                 VALUES ($src, $dst, '$route_json', NOW())
                 ON DUPLICATE KEY UPDATE route_data='$route_json', calculated_time=NOW()") ;
        }

        // (Trip saving logic moved to individual route card buttons)
    }
}

// ── CHECK CACHE ───────────────────────────────────────────
$cached = null;
if (isset($_POST['source'], $_POST['destination']) && empty($routes) && !$msg) {
    $src2 = (int)($_POST['source'] ?? 0);
    $dst2 = (int)($_POST['destination'] ?? 0);
    $cr   = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT route_data, calculated_time FROM cached_routes
         WHERE source_location_id=$src2 AND destination_location_id=$dst2
         ORDER BY calculated_time DESC LIMIT 1"));
    if ($cr) {
        $cached = $cr;
        $routes = json_decode($cached['route_data'], true) ?: [];
    }
}

// ══════════════════════════════════════════════════════════════
//  REAL-TIME ADAPTIVE ROUTING (Features 16, 17 + Dynamic Fare)
// ══════════════════════════════════════════════════════════════
if (!empty($routes)) {
    $current_hour = date('H') . ':00:00';

    foreach ($routes as &$route) {
        $rid = (int)($route['route_id'] ?? 0);
        $adjusted_time     = 0;
        $dynamic_cost      = 0;
        $incident_warnings = [];
        $traffic_alerts    = [];
        $has_segments      = false;

        if ($rid > 0) {
            // Fetch segment locations for this route
            $seg_q = mysqli_query($conn,
                "SELECT RS.segment_id, RS.start_location_id, RS.end_location_id,
                        RS.segment_distance, RS.segment_time, RS.segment_cost,
                        RS.transport_id
                 FROM route_segment RS WHERE RS.route_id = $rid
                 ORDER BY RS.segment_id");

            $segments_data    = [];
            $all_location_ids = [];
            while ($seg = mysqli_fetch_assoc($seg_q)) {
                $has_segments = true;
                $segments_data[] = $seg;
                $all_location_ids[] = (int)$seg['start_location_id'];
                $all_location_ids[] = (int)$seg['end_location_id'];
            }
            $all_location_ids = array_unique($all_location_ids);

            if ($has_segments && !empty($all_location_ids)) {
                $loc_ids_str = implode(',', $all_location_ids);

                // ── Feature 16: Query live traffic conditions ──────
                $traffic_q = mysqli_query($conn,
                    "SELECT TD.location_id, TD.congestion_level, TD.avg_speed,
                            L.location_name
                     FROM traffic_data TD
                     JOIN location L ON TD.location_id = L.location_id
                     WHERE TD.location_id IN ($loc_ids_str)
                       AND TD.date = CURDATE()
                       AND TD.time_slot = '$current_hour'");

                $traffic_by_loc = [];
                while ($td = mysqli_fetch_assoc($traffic_q)) {
                    $traffic_by_loc[(int)$td['location_id']] = $td;
                }

                // ── Feature 17: Query active incidents along route ─
                $incident_q = mysqli_query($conn,
                    "SELECT I.location_id, I.incident_type, I.severity,
                            L.location_name
                     FROM incident_report I
                     JOIN location L ON I.location_id = L.location_id
                     WHERE I.location_id IN ($loc_ids_str)
                       AND I.status = 'Active'");

                while ($inc = mysqli_fetch_assoc($incident_q)) {
                    $incident_warnings[] = $inc;
                }

                // ── Compute adjusted time & dynamic cost per segment
                foreach ($segments_data as $seg) {
                    $seg_time = (int)$seg['segment_time'];
                    $seg_cost = (float)$seg['segment_cost'];
                    $seg_dist = (float)$seg['segment_distance'];

                    // Traffic-based time multiplier
                    $multiplier = 1.0;
                    foreach ([(int)$seg['start_location_id'], (int)$seg['end_location_id']] as $lid) {
                        if (isset($traffic_by_loc[$lid])) {
                            $cong = $traffic_by_loc[$lid]['congestion_level'];
                            $m = 1.0;
                            if     ($cong === 'Gridlock')  $m = 2.5;
                            elseif ($cong === 'Heavy')     $m = 1.8;
                            elseif ($cong === 'Moderate')  $m = 1.3;
                            if ($m > $multiplier) {
                                $multiplier = $m;
                                $traffic_alerts[$lid] = $traffic_by_loc[$lid];
                            }
                        }
                    }
                    $adjusted_time += (int)round($seg_time * $multiplier);

                    // Dynamic fare from transport_fare table
                    $fare_r = mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT base_fare, cost_per_km, time_multiplier
                         FROM transport_fare
                         WHERE transport_id = {$seg['transport_id']}
                         LIMIT 1"));

                    if ($fare_r) {
                        $dynamic_cost += (float)$fare_r['base_fare']
                            + ($seg_dist * (float)$fare_r['cost_per_km'])
                            * (float)$fare_r['time_multiplier'];
                    } else {
                        $dynamic_cost += $seg_cost; // fallback to static
                    }
                }
            }
        }

        // Fallback to static values when no segments or no data
        if ($adjusted_time === 0) $adjusted_time = (int)$route['estimated_time'];
        if ($dynamic_cost == 0)   $dynamic_cost  = (float)$route['estimated_cost'];

        $route['adjusted_time']     = $adjusted_time;
        $route['dynamic_cost']      = round($dynamic_cost, 2);
        $route['incident_warnings'] = $incident_warnings;
        $route['traffic_alerts']    = array_values($traffic_alerts);
        $route['has_incidents']     = !empty($incident_warnings);
        $route['has_traffic']       = !empty($traffic_alerts);

        // Conflict score for route ranking (Feature 17)
        $penalty = 0;
        foreach ($incident_warnings as $iw) {
            if     ($iw['severity'] === 'High')   $penalty += 100;
            elseif ($iw['severity'] === 'Medium') $penalty += 40;
            else                                  $penalty += 10;
        }
        $route['conflict_score'] = $penalty;
    }
    unset($route);

    // ── Re-sort: conflict-free first, then by user criterion ──
    usort($routes, function($a, $b) use ($sort) {
        if ($a['conflict_score'] !== $b['conflict_score']) {
            return $a['conflict_score'] - $b['conflict_score'];
        }
        if ($sort === 'estimated_time') return $a['adjusted_time'] - $b['adjusted_time'];
        if ($sort === 'estimated_cost') return $a['dynamic_cost'] <=> $b['dynamic_cost'];
        return $a['total_distance'] <=> $b['total_distance'];
    });
}

include '../includes/layout.php';
?>

<div class="grid-2" style="gap:24px;align-items:start">

  <!-- SEARCH FORM -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-magnifying-glass" style="color:var(--accent);margin-right:8px"></i>Find Your Route</span></div>
    <form method="POST">
      <div class="form-group">
        <label>From</label>
        <select name="source" required>
          <option value="">— Select source —</option>
          <?php foreach ($loc_list as $l): ?>
            <option value="<?= $l['location_id'] ?>"
              <?= (isset($_POST['source']) && $_POST['source']==$l['location_id']) ? 'selected':'' ?>>
              <?= htmlspecialchars($l['location_name']) ?> (<?= $l['area_zone'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>To</label>
        <select name="destination" required>
          <option value="">— Select destination —</option>
          <?php foreach ($loc_list as $l): ?>
            <option value="<?= $l['location_id'] ?>"
              <?= (isset($_POST['destination']) && $_POST['destination']==$l['location_id']) ? 'selected':'' ?>>
              <?= htmlspecialchars($l['location_name']) ?> (<?= $l['area_zone'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Max Budget (BDT) — optional</label>
        <input type="number" name="budget" placeholder="e.g. 200" min="0"
          value="<?= htmlspecialchars($_POST['budget'] ?? $prefill_budget) ?>">
      </div>

      <div class="form-group">
        <label>Sort By</label>
        <select name="sort">
          <option value="estimated_time"     <?= (($_POST['sort']??'')==='estimated_time')     ?'selected':'' ?>>Fastest First</option>
          <option value="estimated_cost"     <?= (($_POST['sort']??'')==='estimated_cost')     ?'selected':'' ?>>Cheapest First</option>
          <option value="total_distance"     <?= (($_POST['sort']??'')==='total_distance')     ?'selected':'' ?>>Shortest Distance</option>
        </select>
      </div>



      <button class="btn btn-primary" name="search" style="width:100%;justify-content:center">
        <i class="fa fa-magnifying-glass"></i> Search Routes
      </button>
    </form>

    <!-- TRANSPORT REFERENCE -->
    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
      <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Available Transport</div>
      <?php foreach ($trans_list as $t): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(48,54,61,.4);font-size:13px">
          <span><?= htmlspecialchars($t['transport_type']) ?></span>
          <span style="color:var(--muted)">৳<?= $t['base_fare'] ?> base · <?= $t['average_speed'] ?> km/h</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RESULTS -->
  <div>
    <?php if ($msg): ?>
      <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= $msg ?></div>
    <?php endif; ?>

    <?php if ($searched && empty($routes) && !$msg): ?>
      <div class="alert alert-info"><i class="fa fa-circle-info"></i>No routes found for that combination. Try relaxing the budget or check the Admin Panel to add routes.</div>
    <?php endif; ?>
    <?php if ($cached && !empty($routes)): ?>
      <div class="alert alert-info" style="margin-bottom:16px">
        <i class="fa fa-database"></i> Showing cached result from <?= date('M d H:i', strtotime($cached['calculated_time'])) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($routes)): ?>
      <div style="margin-bottom:16px;color:var(--muted);font-size:13px">
        <i class="fa fa-circle-check" style="color:var(--accent)"></i>
        Found <?= count($routes) ?> route<?= count($routes)>1?'s':'' ?>
      </div>

      <?php foreach ($routes as $i => $r): ?>
        <?php
          // Fetch segments for this route
          $segs = mysqli_query($conn,
            "SELECT RS.segment_id, RS.segment_distance, RS.segment_time, RS.segment_cost,
                    TM.transport_type, TM.transport_id,
                    SL.location_name AS s, DL.location_name AS d
             FROM route_segment RS
             JOIN transport_mode TM ON RS.transport_id = TM.transport_id
             JOIN location SL ON RS.start_location_id = SL.location_id
             JOIN location DL ON RS.end_location_id   = DL.location_id
             WHERE RS.route_id = {$r['route_id']}
             ORDER BY RS.segment_cost ASC");
          $seg_rows = [];
          while ($sg = mysqli_fetch_assoc($segs)) $seg_rows[] = $sg;
          $has_segments = !empty($seg_rows);

          // Determine the budget status
          $user_budget = $budget ?? 99999;
          $over_budget = false;
          $display_cost = $r['dynamic_cost'] ?? $r['estimated_cost'];
          if ($has_segments) {
              $min_cost = min(array_column($seg_rows, 'segment_cost'));
              $max_cost = max(array_column($seg_rows, 'segment_cost'));
              // If ALL options exceed budget, show highest fare
              if ($min_cost > $user_budget) {
                  $over_budget = true;
                  $display_cost = $max_cost;
              }
          }
        ?>
        <div class="card" style="margin-bottom:16px;border-color:<?= $i===0?'var(--accent)':(!empty($r['has_incidents'])?'var(--danger)':'var(--border)') ?>">
          <?php if ($i===0): ?>
            <div style="display:inline-block;background:var(--accent);color:#000;font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:12px;letter-spacing:.5px">BEST MATCH</div>
          <?php endif; ?>
          <?php if ($over_budget): ?>
            <div style="display:inline-block;background:rgba(255,107,138,.12);color:var(--danger);font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:12px;letter-spacing:.5px;border:1px solid rgba(255,107,138,.3)">OVER BUDGET</div>
          <?php endif; ?>

          <?php if (!empty($r['incident_warnings'])): ?>
            <div class="alert alert-error" style="margin-bottom:14px;font-size:12.5px">
              <i class="fa fa-triangle-exclamation"></i>
              <div>
                <strong>Route passes through incident zone</strong>
                <?php foreach ($r['incident_warnings'] as $iw): ?>
                  <div style="margin-top:4px"><?= htmlspecialchars($iw['incident_type']) ?>
                    (<span class="badge <?= $iw['severity']==='High'?'badge-red':($iw['severity']==='Medium'?'badge-yellow':'badge-green') ?>" style="font-size:10px"><?= $iw['severity'] ?></span>)
                    @ <?= htmlspecialchars($iw['location_name']) ?></div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($r['traffic_alerts'])): ?>
            <div class="alert alert-info" style="margin-bottom:14px;font-size:12.5px">
              <i class="fa fa-gauge-high"></i>
              <div>
                <strong>Live Traffic on Route</strong>
                <?php foreach ($r['traffic_alerts'] as $ta): ?>
                  <div style="margin-top:4px"><?= htmlspecialchars($ta['location_name']) ?>:
                    <span class="badge <?= $ta['congestion_level']==='Gridlock'?'badge-red':($ta['congestion_level']==='Heavy'?'badge-yellow':'badge-blue') ?>"><?= $ta['congestion_level'] ?></span>
                    <?= $ta['avg_speed'] ?> km/h</div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Route Header -->
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px">
            <div>
              <div style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700"><?= htmlspecialchars($r['source_name']) ?> → <?= htmlspecialchars($r['dest_name']) ?></div>
              <div style="color:var(--muted);font-size:12px;margin-top:4px"><?= $r['total_distance'] ?> km · <?= $r['adjusted_time'] ?? $r['estimated_time'] ?> min est.
                <?php if (isset($r['adjusted_time']) && $r['adjusted_time'] > (int)$r['estimated_time']): ?>
                  <span style="color:var(--warn)">(+<?= $r['adjusted_time'] - (int)$r['estimated_time'] ?>m delay)</span>
                <?php endif; ?>
              </div>
            </div>
            <!-- Dynamic cost display (updated by JS) -->
            <div id="route-cost-<?= $r['route_id'] ?>" style="text-align:right">
              <div style="font-size:20px;font-weight:800;font-family:'Syne',sans-serif;color:var(--accent)">৳<?= number_format($display_cost,0) ?></div>
              <div style="font-size:11px;color:var(--muted)">estimated</div>
            </div>
          </div>

          <?php if ($has_segments): ?>
          <!-- ── Transport Mode Selector ─────────────────── -->
          <div style="margin-bottom:16px">
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">
              <i class="fa fa-bus" style="margin-right:6px"></i>Choose Transport Mode
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap" id="transport-tabs-<?= $r['route_id'] ?>">
              <!-- ALL Tab -->
              <button onclick="switchTransport(<?= $r['route_id'] ?>, 'all', this)"
                style="padding:8px 16px;border-radius:20px;border:1px solid var(--accent);background:rgba(0,229,160,.12);color:var(--accent);font-size:12px;font-weight:600;cursor:pointer;transition:all .2s"
                class="transport-tab-active">
                <i class="fa fa-layer-group" style="margin-right:4px"></i> All Options
              </button>
              <?php foreach ($seg_rows as $idx => $sg): ?>
                <button onclick="switchTransport(<?= $r['route_id'] ?>, <?= $idx ?>, this)"
                  data-cost="<?= $sg['segment_cost'] ?>" data-time="<?= $sg['segment_time'] ?>" data-dist="<?= $sg['segment_distance'] ?>"
                  data-type="<?= htmlspecialchars($sg['transport_type']) ?>"
                  style="padding:8px 16px;border-radius:20px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:12px;font-weight:500;cursor:pointer;transition:all .2s">
                  <?= htmlspecialchars($sg['transport_type']) ?> · ৳<?= number_format($sg['segment_cost'],0) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ── ALL: Show every transport option ─────────── -->
          <div id="transport-all-<?= $r['route_id'] ?>" style="display:block">
            <?php foreach ($seg_rows as $sg): ?>
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;padding:12px 16px;background:var(--surface);border-radius:var(--radius-sm);border:1px solid var(--border);transition:all .2s"
                   onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="width:36px;height:36px;border-radius:50%;background:rgba(56,189,248,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <i class="fa <?= $sg['transport_type']==='Bus'?'fa-bus':($sg['transport_type']==='CNG'?'fa-taxi':($sg['transport_type']==='Rickshaw'?'fa-bicycle':($sg['transport_type']==='Metro'?'fa-train':'fa-car'))) ?>" style="color:var(--accent2);font-size:14px"></i>
                </div>
                <div style="flex:1">
                  <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($sg['transport_type']) ?></div>
                  <div style="color:var(--muted);font-size:11px"><?= $sg['s'] ?> → <?= $sg['d'] ?> · <?= $sg['segment_distance'] ?>km · <?= $sg['segment_time'] ?>min</div>
                </div>
                <div style="text-align:right">
                  <div style="font-size:16px;font-weight:800;font-family:'Syne',sans-serif;color:var(--accent)">৳<?= number_format($sg['segment_cost'],0) ?></div>
                  <?php if ($sg['segment_cost'] > $user_budget && $user_budget < 99999): ?>
                    <div style="font-size:10px;color:var(--danger)">Over budget</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- ── Individual transport detail (hidden by default) ── -->
          <div id="transport-single-<?= $r['route_id'] ?>" style="display:none">
            <div style="padding:20px;background:var(--surface);border-radius:var(--radius-sm);border:1px solid var(--border);text-align:center">
              <div id="single-icon-<?= $r['route_id'] ?>" style="width:56px;height:56px;border-radius:50%;background:rgba(0,229,160,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                <i class="fa fa-bus" style="color:var(--accent);font-size:22px" id="single-fa-<?= $r['route_id'] ?>"></i>
              </div>
              <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700" id="single-type-<?= $r['route_id'] ?>"></div>
              <div style="display:flex;justify-content:center;gap:24px;margin-top:14px;font-size:13px">
                <div><i class="fa fa-road" style="color:var(--warn);margin-right:6px"></i><span id="single-dist-<?= $r['route_id'] ?>"></span> km</div>
                <div><i class="fa fa-clock" style="color:var(--accent2);margin-right:6px"></i><span id="single-time-<?= $r['route_id'] ?>"></span> min</div>
              </div>
              <div style="margin-top:14px;font-size:28px;font-weight:800;font-family:'Syne',sans-serif;color:var(--accent)" id="single-cost-<?= $r['route_id'] ?>"></div>
            </div>
          </div>
          <?php else: ?>
          <!-- No segments — show basic route info -->
          <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:8px">
            <div style="display:flex;align-items:center;gap:8px;font-size:13px">
              <i class="fa fa-clock" style="color:var(--accent2)"></i>
              <span><?= $r['adjusted_time'] ?? $r['estimated_time'] ?> min</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;font-size:13px">
              <i class="fa fa-road" style="color:var(--warn)"></i>
              <span><?= $r['total_distance'] ?> km</span>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($r['has_incidents'])): ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--danger);margin-top:8px">
              <i class="fa fa-shield-halved"></i>
              <span>Conflict risk on this route</span>
            </div>
          <?php endif; ?>

          <?php if (isLoggedIn()): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);text-align:right">
              <form method="POST" style="display:inline-block">
                <input type="hidden" name="trip_route_id" value="<?= $r['route_id'] ?>">
                <input type="hidden" name="trip_cost" id="trip-cost-<?= $r['route_id'] ?>" value="<?= $display_cost ?>">
                <input type="hidden" name="trip_time" id="trip-time-<?= $r['route_id'] ?>" value="<?= $r['adjusted_time'] ?? $r['estimated_time'] ?>">
                <button class="btn btn-primary" name="start_trip" style="padding:10px 24px;border-radius:30px">
                  <i class="fa fa-play" style="margin-right:8px"></i> Start Trip
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php elseif (!$searched): ?>
      <div class="card" style="text-align:center;padding:48px">
        <div style="margin-bottom:16px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></div>
        <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:8px">Select a source & destination</div>
        <div style="color:var(--muted)">Choose your start and end points to see available routes</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
function switchTransport(routeId, mode, btn) {
    const tabsContainer = document.getElementById('transport-tabs-' + routeId);
    const allView = document.getElementById('transport-all-' + routeId);
    const singleView = document.getElementById('transport-single-' + routeId);
    const costBox = document.getElementById('route-cost-' + routeId);
    const tripCost = document.getElementById('trip-cost-' + routeId);
    const tripTime = document.getElementById('trip-time-' + routeId);

    // Reset all tabs
    tabsContainer.querySelectorAll('button').forEach(b => {
        b.style.border = '1px solid var(--border)';
        b.style.background = 'transparent';
        b.style.color = 'var(--muted)';
        b.style.fontWeight = '500';
    });

    // Activate clicked tab
    btn.style.border = '1px solid var(--accent)';
    btn.style.background = 'rgba(0,229,160,.12)';
    btn.style.color = 'var(--accent)';
    btn.style.fontWeight = '600';

    if (mode === 'all') {
        // Show ALL options view
        if (allView) allView.style.display = 'block';
        if (singleView) singleView.style.display = 'none';
        // Reset cost to estimated
        if (costBox) costBox.innerHTML = costBox.dataset.originalHtml || costBox.innerHTML;
    } else {
        // Show single transport view
        if (allView) allView.style.display = 'none';
        if (singleView) singleView.style.display = 'block';

        const cost = btn.dataset.cost;
        const time = btn.dataset.time;
        const dist = btn.dataset.dist;
        const type = btn.dataset.type;

        // Map transport type to icon
        const iconMap = {
            'Bus': 'fa-bus', 'CNG': 'fa-taxi', 'Rickshaw': 'fa-bicycle',
            'Metro': 'fa-train', 'Uber': 'fa-car'
        };
        const faClass = iconMap[type] || 'fa-car';

        // Update single view content
        const faEl = document.getElementById('single-fa-' + routeId);
        if (faEl) faEl.className = 'fa ' + faClass;
        const typeEl = document.getElementById('single-type-' + routeId);
        if (typeEl) typeEl.textContent = type;
        const distEl = document.getElementById('single-dist-' + routeId);
        if (distEl) distEl.textContent = dist;
        const timeEl = document.getElementById('single-time-' + routeId);
        if (timeEl) timeEl.textContent = time;
        const costEl = document.getElementById('single-cost-' + routeId);
        if (costEl) costEl.textContent = '৳' + parseInt(cost).toLocaleString();

        // Update the header cost display
        if (costBox) {
            if (!costBox.dataset.originalHtml) costBox.dataset.originalHtml = costBox.innerHTML;
            costBox.innerHTML = '<div style="font-size:20px;font-weight:800;font-family:\'Syne\',sans-serif;color:var(--accent)">৳' + parseInt(cost).toLocaleString() + '</div>' +
                '<div style="font-size:11px;color:var(--muted)">' + type + '</div>';
        }

        // Update hidden form values for Start Trip
        if (tripCost) tripCost.value = cost;
        if (tripTime) tripTime.value = time;
    }
}
</script>

<?php include '../includes/layout_end.php'; ?>
