<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle  = 'Report Incident';
$activePage = 'report';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid   = (int)$_SESSION['user_id'];
    if ($uid === 0) {
        $err = 'Demo admin cannot report incidents. Please log in as a regular user.';
    } else {
        $loc   = (int)$_POST['location_id'];
        $type  = mysqli_real_escape_string($conn, trim($_POST['incident_type']));
        $sev   = in_array($_POST['severity'],['Low','Medium','High']) ? $_POST['severity'] : 'Low';

        if ($loc && $type) {
            mysqli_query($conn,
                "INSERT INTO incident_report (user_id, location_id, incident_type, severity, timestamp, status)
                 VALUES ($uid, $loc, '$type', '$sev', NOW(), 'Active')");
            $msg = 'Incident reported successfully. It is now visible on the Chaos Map.';
        } else {
            $err = 'Please fill in all fields.';
        }
    }
}

$locations = mysqli_query($conn,"SELECT location_id, location_name, area_zone FROM location ORDER BY location_name");

include '../includes/layout.php';
?>

<div style="max-width:560px">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-flag" style="color:var(--danger);margin-right:8px"></i>Report a Road Incident</span></div>
    <p style="color:var(--muted);font-size:13.5px;margin-bottom:22px">Your report goes live on the Chaos Map immediately and helps other users avoid dangerous areas.</p>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= $err ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Location</label>
        <select name="location_id" required>
          <option value="">— Select location —</option>
          <?php while($l=mysqli_fetch_assoc($locations)): ?>
            <option value="<?= $l['location_id'] ?>"><?= htmlspecialchars($l['location_name']) ?> (<?= $l['area_zone'] ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Incident Type</label>
        <select name="incident_type" required>
          <option value="">— Select type —</option>
          <option value="Accident">Accident</option>
          <option value="Flood">Flood</option>
          <option value="Protest">Protest / Blockade</option>
          <option value="Traffic Jam">Traffic Jam</option>
          <option value="Road Work">Road Work</option>
          <option value="VIP Movement">VIP Movement</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-group">
        <label>Severity</label>
        <div style="display:flex;gap:12px;margin-top:6px">
          <?php foreach(['Low'=>['badge-green','#00C9A7'],'Medium'=>['badge-yellow','#FFC947'],'High'=>['badge-red','#FF6B6B']] as $sev=>[$cls,$color]): ?>
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="severity" value="<?= $sev ?>" style="display:none" required>
            <div class="sev-option" data-val="<?= $sev ?>" style="text-align:center;padding:10px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;color:<?= $color ?>;transition:all .2s;cursor:pointer"
              onclick="document.querySelectorAll('.sev-option').forEach(e=>e.style.background='');this.style.background='rgba(<?= implode(',', sscanf($color, '#%02x%02x%02x')) ?>,0.1)';this.style.borderColor='<?= $color ?>'">
              <?= $sev ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="btn btn-danger" style="width:100%;justify-content:center;margin-top:8px">
        <i class="fa fa-flag"></i> Submit Report
      </button>
    </form>
  </div>

  <div class="card" style="margin-top:20px">
    <div class="card-header"><span class="card-title">My Recent Reports</span></div>
    <?php
      $uid = (int)$_SESSION['user_id'];
      $my = mysqli_query($conn,
        "SELECT I.incident_type, I.severity, I.status, I.timestamp, L.location_name
         FROM incident_report I
         JOIN location L ON I.location_id = L.location_id
         WHERE I.user_id = $uid ORDER BY I.timestamp DESC LIMIT 5");
    ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Location</th><th>Severity</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
        <?php while($r=mysqli_fetch_assoc($my)): ?>
          <tr>
            <td><?= $r['incident_type'] ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= $r['location_name'] ?></td>
            <td><span class="badge <?= $r['severity']==='High'?'badge-red':($r['severity']==='Medium'?'badge-yellow':'badge-green') ?>"><?= $r['severity'] ?></span></td>
            <td><span class="badge <?= $r['status']==='Active'?'badge-red':($r['status']==='Resolved'?'badge-green':'badge-blue') ?>"><?= $r['status'] ?></span></td>
            <td style="font-size:12px;color:var(--muted)"><?= date('M d H:i', strtotime($r['timestamp'])) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
