<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle  = 'My Preferences';
$activePage = 'prefs';

$uid = (int)$_SESSION['user_id'];
$msg = '';

// ── LOAD USER ──────────────────────────────────────────
$user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user WHERE user_id=$uid"));

// ── UPDATE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));

    $pwsql = '';
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
            $msg = 'Passwords do not match.';
        } else {
            $hash  = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pwsql = ", password='$hash'";
        }
    }
    if ($msg !== 'Passwords do not match.') {
        $upd_sql = "UPDATE user SET name='$name', email='$email' $pwsql
         WHERE user_id=$uid";
        $upd = mysqli_query($conn, $upd_sql);
         
        if (!$upd) {
            $msg = 'Database Error: ' . mysqli_error($conn);
        } else {
            $_SESSION['user_name'] = $name;
            if (empty($msg)) $msg = 'Preferences saved successfully.';
            $user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user WHERE user_id=$uid"));
        }
    }
}

include '../includes/layout.php';
?>

<div style="max-width:560px">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-gear" style="color:var(--accent2);margin-right:8px"></i>My Preferences</span></div>
    <?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $msg ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= $user['email'] ?>" required>
      </div>



      <div style="padding-top:16px;border-top:1px solid var(--border);margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px">Change Password</div>
        <div class="grid-2">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Leave blank to keep current">
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat new password">
          </div>
        </div>
      </div>

      <button class="btn btn-primary" style="width:100%;justify-content:center">
        <i class="fa fa-floppy-disk"></i> Save Preferences
      </button>
    </form>
  </div>

  <!-- ACCOUNT INFO -->
  <div class="card" style="margin-top:20px">
    <div class="card-header"><span class="card-title">Account Summary</span></div>
    <?php
      $summary = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
          (SELECT COUNT(*) FROM trip_history WHERE user_id=$uid) AS trips,
          (SELECT COUNT(*) FROM incident_report WHERE user_id=$uid) AS incidents,
          (SELECT COUNT(*) FROM transport_reviews WHERE user_id=$uid) AS reviews,
          (SELECT SUM(travel_cost) FROM trip_history WHERE user_id=$uid) AS total_spent"));
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div style="padding:14px;background:var(--bg);border-radius:10px;text-align:center">
        <div style="font-size:24px;font-weight:800;font-family:'Syne',sans-serif;color:var(--accent)"><?= $summary['trips'] ?></div>
        <div style="font-size:12px;color:var(--muted)">Total Trips</div>
      </div>
      <div style="padding:14px;background:var(--bg);border-radius:10px;text-align:center">
        <div style="font-size:24px;font-weight:800;font-family:'Syne',sans-serif;color:var(--accent2)">৳<?= number_format($summary['total_spent']??0,0) ?></div>
        <div style="font-size:12px;color:var(--muted)">Total Spent</div>
      </div>
      <div style="padding:14px;background:var(--bg);border-radius:10px;text-align:center">
        <div style="font-size:24px;font-weight:800;font-family:'Syne',sans-serif;color:var(--warn)"><?= $summary['incidents'] ?></div>
        <div style="font-size:12px;color:var(--muted)">Reports Filed</div>
      </div>
      <div style="padding:14px;background:var(--bg);border-radius:10px;text-align:center">
        <div style="font-size:24px;font-weight:800;font-family:'Syne',sans-serif;color:var(--danger)"><?= $summary['reviews'] ?></div>
        <div style="font-size:12px;color:var(--muted)">Reviews Written</div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
