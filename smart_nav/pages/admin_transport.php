<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle  = 'Transport Management';
$activePage = 'at';

$msg = '';

// ── ADD TRANSPORT MODE ────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_transport'])) {
    $type = mysqli_real_escape_string($conn, trim($_POST['transport_type']));
    $spd  = (float)$_POST['average_speed'];
    $fare = (float)$_POST['base_fare'];
    mysqli_query($conn,"INSERT INTO transport_mode (transport_type, average_speed, base_fare) VALUES ('$type',$spd,$fare)");
    $msg = 'Transport mode added.';
}

// ── ADD AVAILABILITY RECORD ───────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_availability'])) {
    $tid   = (int)$_POST['transport_id'];
    $lid   = (int)$_POST['location_id'];
    $count = (int)$_POST['available_count'];
    mysqli_query($conn,
        "INSERT INTO transport_availability (transport_id, location_id, available_count, timestamp)
         VALUES ($tid, $lid, $count, NOW())");
    $msg = 'Availability updated.';
}

// ── DELETE TRANSPORT ──────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn,"DELETE FROM transport_mode WHERE transport_id=$id");
    $msg = 'Transport mode removed.';
}

$transports = mysqli_query($conn,"SELECT * FROM transport_mode ORDER BY transport_id DESC");
$trans_arr  = [];
while($t=mysqli_fetch_assoc($transports)) $trans_arr[] = $t;

$locations  = mysqli_query($conn,"SELECT location_id, location_name FROM location ORDER BY location_name");
$loc_arr    = [];
while($l=mysqli_fetch_assoc($locations)) $loc_arr[] = $l;

// ── LATEST AVAILABILITY ───────────────────────────────
$availability = mysqli_query($conn,
    "SELECT TA.*, TM.transport_type, L.location_name
     FROM transport_availability TA
     JOIN transport_mode TM ON TA.transport_id = TM.transport_id
     JOIN location L ON TA.location_id = L.location_id
     ORDER BY TA.timestamp DESC LIMIT 20");

include '../includes/layout.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $msg ?></div><?php endif; ?>

<div class="grid-2" style="align-items:start;margin-bottom:24px">

  <!-- ADD TRANSPORT MODE -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-bus" style="color:var(--accent);margin-right:8px"></i>Add Transport Mode</span></div>
    <form method="POST">
      <div class="form-group">
        <label>Transport Type Name</label>
        <input type="text" name="transport_type" required placeholder="e.g. Electric Bus">
      </div>
      <div class="grid-2" style="gap:12px">
        <div class="form-group"><label>Avg Speed (km/h)</label><input type="number" name="average_speed" step="0.1" required placeholder="30"></div>
        <div class="form-group"><label>Base Fare (BDT)</label><input type="number" name="base_fare" step="0.01" required placeholder="25"></div>
      </div>
      <button class="btn btn-primary" name="add_transport" style="width:100%;justify-content:center"><i class="fa fa-plus"></i> Add Transport Mode</button>
    </form>

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Current Transport Modes</div>
      <?php foreach($trans_arr as $t): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(48,54,61,.4)">
          <div>
            <span style="font-weight:500;font-size:13.5px"><?= htmlspecialchars($t['transport_type']) ?></span>
            <span style="color:var(--muted);font-size:12px;margin-left:8px">৳<?= $t['base_fare'] ?> · <?= $t['average_speed'] ?>km/h</span>
          </div>
          <a href="?delete=<?= $t['transport_id'] ?>" onclick="return confirm('Remove this transport mode?')" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- UPDATE AVAILABILITY -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-map-pin" style="color:var(--accent);margin-right:8px"></i>Update Availability</span></div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:18px">Log how many vehicles of each type are currently available near each location.</p>
    <form method="POST">
      <div class="form-group">
        <label>Transport Mode</label>
        <select name="transport_id" required>
          <?php foreach($trans_arr as $t): ?><option value="<?= $t['transport_id'] ?>"><?= $t['transport_type'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Location</label>
        <select name="location_id" required>
          <?php foreach($loc_arr as $l): ?><option value="<?= $l['location_id'] ?>"><?= $l['location_name'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Available Count</label>
        <input type="number" name="available_count" required placeholder="e.g. 5" min="0">
      </div>
      <button class="btn btn-secondary" name="add_availability" style="width:100%;justify-content:center"><i class="fa fa-location-dot"></i> Log Availability</button>
    </form>
  </div>

</div>

<!-- AVAILABILITY LOG -->
<div class="card">
  <div class="card-header"><span class="card-title"><i class="fa fa-traffic-light" style="color:var(--warn);margin-right:8px"></i>Recent Availability Records</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Transport</th><th>Location</th><th>Available</th><th>Logged At</th></tr></thead>
      <tbody>
      <?php while($a=mysqli_fetch_assoc($availability)): ?>
        <tr>
          <td><?= htmlspecialchars($a['transport_type']) ?></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($a['location_name']) ?></td>
          <td>
            <span class="badge <?= $a['available_count']>3?'badge-green':($a['available_count']>0?'badge-yellow':'badge-red') ?>">
              <?= $a['available_count'] ?> units
            </span>
          </td>
          <td style="color:var(--muted);font-size:12px"><?= date('M d H:i', strtotime($a['timestamp'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
