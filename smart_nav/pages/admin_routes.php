<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle  = 'Manage Routes';
$activePage = 'ar';

$msg = ''; $err = '';

$locations  = mysqli_query($conn,"SELECT location_id, location_name FROM location ORDER BY location_name");
$loc_arr    = [];
while($l=mysqli_fetch_assoc($locations)) $loc_arr[] = $l;

$transports = mysqli_query($conn,"SELECT transport_id, transport_type FROM transport_mode ORDER BY transport_type");
$trans_arr  = [];
while($t=mysqli_fetch_assoc($transports)) $trans_arr[] = $t;

// ── ADD ROUTE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_route'])) {
    $src   = (int)$_POST['source_location_id'];
    $dst   = (int)$_POST['destination_location_id'];
    $dist  = (float)$_POST['total_distance'];
    $time  = (int)$_POST['estimated_time'];
    $cost  = (float)$_POST['estimated_cost'];

    if ($src===$dst) { $err='Source and destination cannot be the same.'; }
    else {
        $res = mysqli_query($conn,
            "INSERT INTO route (source_location_id, destination_location_id, total_distance, estimated_time, estimated_cost)
             VALUES ($src, $dst, $dist, $time, $cost)");
        if ($res) { $msg = 'Route added. Route ID: '.mysqli_insert_id($conn); }
        else       { $err = mysqli_error($conn); }
    }
}

// ── DELETE ROUTE ──────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn,"DELETE FROM route_segment WHERE route_id=$id");
    mysqli_query($conn,"DELETE FROM trip_history  WHERE route_id=$id");
    mysqli_query($conn,"DELETE FROM route         WHERE route_id=$id");
    $msg = 'Route deleted.';
}

// ── DELETE SEGMENT ────────────────────────────────────
if (isset($_GET['delete_seg'])) {
    $seg_id = (int)$_GET['delete_seg'];
    mysqli_query($conn,"DELETE FROM route_segment WHERE segment_id=$seg_id");
    $msg = 'Segment deleted.';
}

// ── ADD SEGMENT ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_segment'])) {
    $rid  = (int)$_POST['route_id'];
    $tid  = (int)$_POST['transport_id'];
    $sl   = (int)$_POST['start_location_id'];
    $el   = (int)$_POST['end_location_id'];
    $sd   = (float)$_POST['segment_distance'];
    $st   = (int)$_POST['segment_time'];
    $sc   = (float)$_POST['segment_cost'];
    mysqli_query($conn,
        "INSERT INTO route_segment (route_id,transport_id,start_location_id,end_location_id,segment_distance,segment_time,segment_cost)
         VALUES ($rid,$tid,$sl,$el,$sd,$st,$sc)");
    $msg = 'Segment added.';
}

// ── LIST ROUTES ───────────────────────────────────────
$routes = mysqli_query($conn,
    "SELECT R.route_id, SL.location_name AS src, DL.location_name AS dst,
            R.total_distance, R.estimated_time, R.estimated_cost,
            COUNT(RS.segment_id) AS seg_count
     FROM route R
     JOIN location SL ON R.source_location_id      = SL.location_id
     JOIN location DL ON R.destination_location_id = DL.location_id
     LEFT JOIN route_segment RS ON RS.route_id = R.route_id
     GROUP BY R.route_id ORDER BY R.route_id DESC");

include '../includes/layout.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= $err ?></div><?php endif; ?>

<div class="grid-2" style="align-items:start;margin-bottom:24px">

  <!-- ADD ROUTE FORM -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add New Route</span></div>
    <form method="POST">
      <div class="form-group">
        <label>Source Location</label>
        <select name="source_location_id" required>
          <?php foreach($loc_arr as $l): ?>
            <option value="<?= $l['location_id'] ?>"><?= $l['location_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Destination Location</label>
        <select name="destination_location_id" required>
          <?php foreach($loc_arr as $l): ?>
            <option value="<?= $l['location_id'] ?>"><?= $l['location_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid-2" style="gap:12px">
        <div class="form-group"><label>Distance (km)</label><input type="number" name="total_distance" step="0.1" required placeholder="8.5"></div>
        <div class="form-group"><label>Time (min)</label><input type="number" name="estimated_time" required placeholder="35"></div>
      </div>
      <div class="form-group"><label>Estimated Cost (BDT)</label><input type="number" name="estimated_cost" step="0.01" required placeholder="80"></div>
      <button class="btn btn-primary" name="add_route" style="width:100%;justify-content:center"><i class="fa fa-plus"></i> Add Route</button>
    </form>
  </div>

  <!-- ADD SEGMENT FORM -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-code-branch" style="color:var(--accent2);margin-right:8px"></i>Add Route Segment</span></div>
    <form method="POST">
      <div class="form-group">
        <label>Route ID</label>
        <input type="number" name="route_id" required placeholder="e.g. 1">
      </div>
      <div class="form-group">
        <label>Transport Mode</label>
        <select name="transport_id" required>
          <?php foreach($trans_arr as $t): ?>
            <option value="<?= $t['transport_id'] ?>"><?= $t['transport_type'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid-2" style="gap:12px">
        <div class="form-group"><label>Start Location</label>
          <select name="start_location_id">
            <?php foreach($loc_arr as $l): ?><option value="<?= $l['location_id'] ?>"><?= $l['location_name'] ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>End Location</label>
          <select name="end_location_id">
            <?php foreach($loc_arr as $l): ?><option value="<?= $l['location_id'] ?>"><?= $l['location_name'] ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Distance (km)</label><input type="number" name="segment_distance" step="0.1" required placeholder="4.5"></div>
        <div class="form-group"><label>Time (min)</label><input type="number" name="segment_time" required placeholder="20"></div>
      </div>
      <div class="form-group"><label>Cost (BDT)</label><input type="number" name="segment_cost" step="0.01" required placeholder="40"></div>
      <button class="btn btn-secondary" name="add_segment" style="width:100%;justify-content:center"><i class="fa fa-plus"></i> Add Segment</button>
    </form>
  </div>

</div>

<!-- ROUTES TABLE WITH EXPANDABLE SEGMENTS -->
<div class="card">
  <div class="card-header"><span class="card-title"><i class="fa fa-route" style="color:var(--accent);margin-right:8px"></i>All Routes & Segments</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th></th><th>ID</th><th>Route</th><th>Distance</th><th>Time</th><th>Cost</th><th>Segments</th><th>Action</th></tr></thead>
      <tbody>
      <?php while($r=mysqli_fetch_assoc($routes)): ?>
        <tr style="cursor:pointer" onclick="toggleSegments(<?= $r['route_id'] ?>)">
          <td><i class="fa fa-chevron-right" id="chevron-<?= $r['route_id'] ?>" style="color:var(--muted);transition:transform .2s;font-size:11px"></i></td>
          <td style="color:var(--muted)"><?= $r['route_id'] ?></td>
          <td style="font-weight:500"><?= htmlspecialchars($r['src']) ?> → <?= htmlspecialchars($r['dst']) ?></td>
          <td><?= $r['total_distance'] ?> km</td>
          <td><?= $r['estimated_time'] ?> min</td>
          <td><span class="badge badge-green">৳<?= $r['estimated_cost'] ?></span></td>
          <td><span class="badge badge-blue"><?= $r['seg_count'] ?> segment<?= $r['seg_count']!=1?'s':'' ?></span></td>
          <td>
            <a href="?delete=<?= $r['route_id'] ?>"
               onclick="event.stopPropagation(); return confirm('Delete this route and all its segments?')"
               class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a>
          </td>
        </tr>
        <!-- EXPANDABLE SEGMENT ROWS -->
        <tr id="seg-row-<?= $r['route_id'] ?>" style="display:none">
          <td colspan="8" style="padding:0;background:rgba(0,229,160,.02)">
            <?php
              $segs_admin = mysqli_query($conn,
                "SELECT RS.segment_id, RS.segment_distance, RS.segment_time, RS.segment_cost,
                        TM.transport_type, SL.location_name AS s, DL.location_name AS d
                 FROM route_segment RS
                 JOIN transport_mode TM ON RS.transport_id = TM.transport_id
                 JOIN location SL ON RS.start_location_id = SL.location_id
                 JOIN location DL ON RS.end_location_id   = DL.location_id
                 WHERE RS.route_id = {$r['route_id']}
                 ORDER BY RS.segment_cost ASC");
              $seg_count_actual = 0;
            ?>
            <?php if (mysqli_num_rows($segs_admin) > 0): ?>
              <div style="padding:16px 24px">
                <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">
                  <i class="fa fa-bus" style="margin-right:6px"></i>Transport Options for Route #<?= $r['route_id'] ?>
                </div>
                <?php while($sg = mysqli_fetch_assoc($segs_admin)): $seg_count_actual++; ?>
                  <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;padding:10px 14px;background:var(--surface2);border-radius:var(--radius-sm);border:1px solid var(--border)">
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(56,189,248,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                      <i class="fa <?= $sg['transport_type']==='Bus'?'fa-bus':($sg['transport_type']==='CNG'?'fa-taxi':($sg['transport_type']==='Rickshaw'?'fa-bicycle':($sg['transport_type']==='Metro'?'fa-train':'fa-car'))) ?>" style="color:var(--accent2);font-size:12px"></i>
                    </div>
                    <div style="flex:1">
                      <span style="font-weight:600;font-size:13px"><?= htmlspecialchars($sg['transport_type']) ?></span>
                      <span style="color:var(--muted);font-size:12px;margin-left:8px"><?= $sg['s'] ?> → <?= $sg['d'] ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:16px;font-size:12px">
                      <span style="color:var(--muted)"><i class="fa fa-road" style="margin-right:4px"></i><?= $sg['segment_distance'] ?>km</span>
                      <span style="color:var(--muted)"><i class="fa fa-clock" style="margin-right:4px"></i><?= $sg['segment_time'] ?>min</span>
                      <span style="font-weight:700;color:var(--accent);font-size:14px">৳<?= number_format($sg['segment_cost'],0) ?></span>
                    </div>
                    <a href="?delete_seg=<?= $sg['segment_id'] ?>&route_id=<?= $r['route_id'] ?>"
                       onclick="event.stopPropagation(); return confirm('Delete this segment?')"
                       style="color:var(--danger);font-size:12px;opacity:.6;transition:opacity .2s"
                       onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.6'">
                       <i class="fa fa-times-circle"></i>
                    </a>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div style="padding:16px 24px;color:var(--muted);font-size:13px;font-style:italic">
                <i class="fa fa-info-circle" style="margin-right:6px"></i>No segments added yet. Use the "Add Route Segment" form above with Route ID <?= $r['route_id'] ?>.
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleSegments(routeId) {
    const row = document.getElementById('seg-row-' + routeId);
    const chevron = document.getElementById('chevron-' + routeId);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        chevron.style.transform = 'rotate(90deg)';
    } else {
        row.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}
</script>

<?php include '../includes/layout_end.php'; ?>
