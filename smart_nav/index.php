<?php
require_once 'includes/auth.php';
require_once 'includes/db_connect.php';

$pageTitle  = 'Dashboard';
$activePage = 'home';

// ── STATS ────────────────────────────────────────────────
$totalUsers     = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM user"))[0];
$totalRoutes    = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM route"))[0];
$activeIncidents= mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM incident_report WHERE status='Active'"))[0];
$totalTrips     = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM trip_history"))[0];

// ── ONBOARDING CHECK ──────────────────────────
$show_onboarding = false;
$locations_query = null;
if (isLoggedIn() && empty($_SESSION['onboarding_shown'])) {
    $uid_chk = (int)$_SESSION['user_id'];
    if ($uid_chk > 0) {
        $show_onboarding = true;
        $_SESSION['onboarding_shown'] = true;
        $locations_query = mysqli_query($conn, "SELECT location_id, location_name, area_zone FROM location ORDER BY location_name");
    }
}

// ── RECENT TRIPS ─────────────────────────────────────────
$recentTrips = mysqli_query($conn,
    "SELECT TH.trip_id, U.name, SL.location_name AS source, DL.location_name AS dest,
            TH.travel_cost, TH.trip_date
     FROM trip_history TH
     JOIN user U     ON TH.user_id  = U.user_id
     JOIN route R    ON TH.route_id = R.route_id
     JOIN location SL ON R.source_location_id      = SL.location_id
     JOIN location DL ON R.destination_location_id = DL.location_id
     ORDER BY TH.trip_date DESC LIMIT 6");

// ── RECENT INCIDENTS ─────────────────────────────────────
$recentIncidents = mysqli_query($conn,
    "SELECT I.incident_type, I.severity, I.timestamp, L.location_name
     FROM incident_report I
     JOIN location L ON I.location_id = L.location_id
     WHERE I.status = 'Active'
     ORDER BY I.timestamp DESC LIMIT 5");

// ── TOP ROUTES ────────────────────────────────────────────
$topRoutes = mysqli_query($conn,
    "SELECT SL.location_name AS src, DL.location_name AS dst,
            COUNT(*) AS trips, AVG(TH.travel_cost) AS avg_cost
     FROM trip_history TH
     JOIN route R    ON TH.route_id = R.route_id
     JOIN location SL ON R.source_location_id      = SL.location_id
     JOIN location DL ON R.destination_location_id = DL.location_id
     GROUP BY R.route_id ORDER BY trips DESC LIMIT 5");

// ── TRANSPORT RATINGS ─────────────────────────────────────
$ratings = mysqli_query($conn,
    "SELECT TM.transport_type, AVG(TR.rating) AS avg_r, COUNT(*) AS cnt
     FROM transport_reviews TR
     JOIN transport_mode TM ON TR.transport_id = TM.transport_id
     GROUP BY TM.transport_id ORDER BY avg_r DESC");

include 'includes/layout.php';
?>

<!-- STAT CARDS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa fa-users"></i></div>
    <div><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Registered Users</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-route"></i></div>
    <div><div class="stat-value"><?= $totalRoutes ?></div><div class="stat-label">Total Routes</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa fa-triangle-exclamation"></i></div>
    <div><div class="stat-value"><?= $activeIncidents ?></div><div class="stat-label">Active Incidents</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa fa-clock-rotate-left"></i></div>
    <div><div class="stat-value"><?= $totalTrips ?></div><div class="stat-label">Trips Logged</div></div>
  </div>
</div>

<!-- QUICK ACTIONS -->
<div class="flex gap-12 mb-20" style="gap:12px;margin-bottom:24px;">
  <a href="/smart_nav/pages/route_finder.php" class="btn btn-primary"><i class="fa fa-route"></i> Find a Route</a>
  <a href="/smart_nav/pages/report.php"       class="btn btn-secondary"><i class="fa fa-flag"></i> Report Incident</a>
  <a href="/smart_nav/pages/chaos_map.php"    class="btn btn-secondary"><i class="fa fa-map"></i> View Chaos Map</a>
</div>

<div class="grid-2">

  <!-- RECENT TRIPS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Trips</span>
      <a href="/smart_nav/pages/trip_history.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>User</th><th>Route</th><th>Cost</th><th>Date</th></tr></thead>
        <tbody>
        <?php while($t = mysqli_fetch_assoc($recentTrips)): ?>
          <tr>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($t['source']) ?> → <?= htmlspecialchars($t['dest']) ?></td>
            <td><span class="badge badge-green">৳<?= number_format($t['travel_cost'],0) ?></span></td>
            <td style="color:var(--muted);font-size:12px"><?= date('M d, H:i', strtotime($t['trip_date'])) ?></td>
          </tr>
        <?php endwhile; ?>
        <?php if(mysqli_num_rows($recentTrips)===0): ?>
          <tr><td colspan="4" style="color:var(--muted);text-align:center;padding:20px">No trips yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ACTIVE INCIDENTS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-bell" style="color:var(--danger);margin-right:8px"></i>Active Incidents</span>
      <a href="/smart_nav/pages/chaos_map.php" class="btn btn-secondary btn-sm">Chaos Map</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Location</th><th>Type</th><th>Severity</th><th>Time</th></tr></thead>
        <tbody>
        <?php while($i = mysqli_fetch_assoc($recentIncidents)): ?>
          <tr>
            <td><?= htmlspecialchars($i['location_name']) ?></td>
            <td><?= htmlspecialchars($i['incident_type']) ?></td>
            <td>
              <?php
                $sev = $i['severity'];
                $cls = $sev==='High'?'badge-red':($sev==='Medium'?'badge-yellow':'badge-green');
              ?>
              <span class="badge <?= $cls ?>"><?= $sev ?></span>
            </td>
            <td style="color:var(--muted);font-size:12px"><?= date('H:i', strtotime($i['timestamp'])) ?></td>
          </tr>
        <?php endwhile; ?>
        <?php if(mysqli_num_rows($recentIncidents)===0): ?>
          <tr><td colspan="4" style="color:var(--muted);text-align:center;padding:20px">No active incidents</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<div class="grid-2 mt-20" style="margin-top:20px">

  <!-- TOP ROUTES -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-chart-bar" style="color:var(--accent2);margin-right:8px"></i>Most Popular Routes</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Route</th><th>Trips</th><th>Avg Cost</th></tr></thead>
        <tbody>
        <?php while($r = mysqli_fetch_assoc($topRoutes)): ?>
          <tr>
            <td style="font-size:12.5px"><?= htmlspecialchars($r['src']) ?> → <?= htmlspecialchars($r['dst']) ?></td>
            <td><?= $r['trips'] ?></td>
            <td><span class="badge badge-blue">৳<?= number_format($r['avg_cost'],0) ?></span></td>
          </tr>
        <?php endwhile; ?>
        <?php if(mysqli_num_rows($topRoutes)===0): ?>
          <tr><td colspan="3" style="color:var(--muted);text-align:center;padding:20px">No data yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TRANSPORT RATINGS -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-star" style="color:var(--warn);margin-right:8px"></i>Transport Ratings</span></div>
    <?php while($r = mysqli_fetch_assoc($ratings)): ?>
      <?php $pct = ($r['avg_r']/5)*100; ?>
      <div style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px">
          <span><?= htmlspecialchars($r['transport_type']) ?></span>
          <span style="color:var(--accent)"><?= number_format($r['avg_r'],1) ?>/5 <span style="color:var(--muted)">(<?= $r['cnt'] ?> reviews)</span></span>
        </div>
        <div style="background:var(--bg);border-radius:20px;height:6px;overflow:hidden">
          <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:20px"></div>
        </div>
      </div>
    <?php endwhile; ?>
    <?php if(mysqli_num_rows($ratings)===0): ?>
      <p style="color:var(--muted);text-align:center;padding:20px">No ratings yet</p>
    <?php endif; ?>
  </div>

</div>

<?php if ($show_onboarding): ?>
<!-- ONBOARDING MODAL -->
<div id="onboardingModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,11,24,0.85);backdrop-filter:blur(10px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;animation:modalFadeIn .5s forwards;">
  <div class="card" style="width:100%;max-width:440px;position:relative;transform:translateY(20px);animation:modalSlideUp .5s .2s forwards;opacity:0;">
    <button onclick="document.getElementById('onboardingModal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;transition:color .2s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--muted)'"><i class="fa fa-times"></i></button>
    <div style="text-align:center;margin-bottom:24px">
      <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;color:#000;margin:0 auto 16px;box-shadow:0 0 30px rgba(0,229,160,0.3)">
        <i class="fa fa-rocket"></i>
      </div>
      <h2 style="font-family:'Poppins',sans-serif;font-size:22px;color:var(--text);margin-bottom:8px">Welcome to SmartNav!</h2>
      <p style="color:var(--muted);font-size:13px;line-height:1.5">Where are you heading today? Let's find your perfect route! We will automatically apply your registered budget and time preferences to find the best rides for you.</p>
    </div>
    
    <form action="/smart_nav/pages/route_finder.php" method="POST">
      <input type="hidden" name="search" value="1">
      <div class="form-group">
        <label>Where are you starting?</label>
        <select name="source" required>
          <option value="">— Select Origin —</option>
          <?php 
            $locs_arr = [];
            while($l = mysqli_fetch_assoc($locations_query)) {
              $locs_arr[] = $l;
              echo "<option value='{$l['location_id']}'>{$l['location_name']} ({$l['area_zone']})</option>";
            }
          ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:24px">
        <label>Where do you want to go?</label>
        <select name="destination" required>
          <option value="">— Select Destination —</option>
          <?php foreach($locs_arr as $l): ?>
            <option value="<?= $l['location_id'] ?>"><?= htmlspecialchars($l['location_name']) ?> (<?= htmlspecialchars($l['area_zone']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-size:15px;padding:12px">
        <i class="fa fa-route"></i> Find My Perfect Ride
      </button>
    </form>
  </div>
</div>
<style>
@keyframes modalFadeIn { to { opacity: 1; } }
@keyframes modalSlideUp { to { opacity: 1; transform: translateY(0); } }
</style>
<?php endif; ?>

<?php include 'includes/layout_end.php'; ?>
