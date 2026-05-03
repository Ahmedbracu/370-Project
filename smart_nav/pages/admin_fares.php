<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle  = 'Manage Fares';
$activePage = 'af';

$msg = '';

// ── ADD FARE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_fare'])) {
    $tid   = (int)$_POST['transport_id'];
    $base  = (float)$_POST['base_fare'];
    $cpk   = (float)$_POST['cost_per_km'];
    $mult  = (float)$_POST['time_multiplier'];
    $zone  = mysqli_real_escape_string($conn, trim($_POST['area_zone']));
    mysqli_query($conn,
        "INSERT INTO transport_fare (transport_id, base_fare, cost_per_km, time_multiplier, area_zone)
         VALUES ($tid, $base, $cpk, $mult, '$zone')");
    $msg = 'Fare rule added.';
}

// ── DELETE ────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn,"DELETE FROM transport_fare WHERE fare_id=$id");
    $msg = 'Fare rule deleted.';
}

// ── UPDATE TRANSPORT BASE FARE ────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_transport'])) {
    $tid  = (int)$_POST['transport_id'];
    $fare = (float)$_POST['base_fare'];
    $spd  = (float)$_POST['average_speed'];
    mysqli_query($conn,"UPDATE transport_mode SET base_fare=$fare, average_speed=$spd WHERE transport_id=$tid");
    $msg = 'Transport mode updated.';
}

$transports = mysqli_query($conn,"SELECT * FROM transport_mode ORDER BY transport_type");
$trans_arr  = [];
while($t=mysqli_fetch_assoc($transports)) $trans_arr[] = $t;

$fares = mysqli_query($conn,
    "SELECT TF.*, TM.transport_type FROM transport_fare TF
     JOIN transport_mode TM ON TF.transport_id = TM.transport_id
     ORDER BY TF.fare_id DESC");

include '../includes/layout.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $msg ?></div><?php endif; ?>

<div class="grid-2" style="align-items:start;margin-bottom:24px">

  <!-- TRANSPORT BASE FARES -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-bus" style="color:var(--accent);margin-right:8px"></i>Transport Mode Rates</span></div>
    <?php foreach($trans_arr as $t): ?>
      <form method="POST" style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border)">
        <input type="hidden" name="transport_id" value="<?= $t['transport_id'] ?>">
        <span style="width:90px;font-size:13.5px;font-weight:500"><?= $t['transport_type'] ?></span>
        <div style="flex:1">
          <input type="number" name="base_fare" value="<?= $t['base_fare'] ?>" step="0.01"
                 style="width:100%;padding:7px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px"
                 placeholder="Base fare BDT">
        </div>
        <div style="flex:1">
          <input type="number" name="average_speed" value="<?= $t['average_speed'] ?>" step="0.1"
                 style="width:100%;padding:7px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px"
                 placeholder="Avg km/h">
        </div>
        <button class="btn btn-secondary btn-sm" name="update_transport">Save</button>
      </form>
    <?php endforeach; ?>
  </div>

  <!-- ADD FARE RULE -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add Dynamic Fare Rule</span></div>
    <form method="POST">
      <div class="form-group">
        <label>Transport Mode</label>
        <select name="transport_id" required>
          <?php foreach($trans_arr as $t): ?><option value="<?= $t['transport_id'] ?>"><?= $t['transport_type'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="grid-2" style="gap:12px">
        <div class="form-group"><label>Base Fare (BDT)</label><input type="number" name="base_fare" step="0.01" required placeholder="20"></div>
        <div class="form-group"><label>Cost / km (BDT)</label><input type="number" name="cost_per_km" step="0.01" required placeholder="5"></div>
        <div class="form-group"><label>Time Multiplier</label><input type="number" name="time_multiplier" step="0.01" value="1.0" required></div>
        <div class="form-group"><label>Area Zone</label>
          <select name="area_zone">
            <option>Central</option><option>North</option><option>South</option><option>East</option><option>West</option><option>North-West</option><option>Commercial</option>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" name="add_fare" style="width:100%;justify-content:center"><i class="fa fa-plus"></i> Add Fare Rule</button>
    </form>
  </div>

</div>

<!-- FARE RULES TABLE -->
<div class="card">
  <div class="card-header"><span class="card-title">All Fare Rules</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Transport</th><th>Base Fare</th><th>Cost/km</th><th>Multiplier</th><th>Zone</th><th>Action</th></tr></thead>
      <tbody>
      <?php while($f=mysqli_fetch_assoc($fares)): ?>
        <tr>
          <td style="color:var(--muted)"><?= $f['fare_id'] ?></td>
          <td><?= $f['transport_type'] ?></td>
          <td><span class="badge badge-green">৳<?= $f['base_fare'] ?></span></td>
          <td>৳<?= $f['cost_per_km'] ?>/km</td>
          <td><?= $f['time_multiplier'] ?>x</td>
          <td><span class="badge badge-blue"><?= $f['area_zone'] ?></span></td>
          <td><a href="?delete=<?= $f['fare_id'] ?>" onclick="return confirm('Delete this fare rule?')" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
