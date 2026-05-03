<?php
session_start();
require_once '../includes/db_connect.php';

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login';

// ── REGISTER ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $budget   = (float)($_POST['preferred_budget'] ?? 0);
    $time     = (int)($_POST['preferred_time'] ?? 0);
    $comfort  = (int)($_POST['comfort_level'] ?? 3);

    $check = mysqli_query($conn, "SELECT user_id FROM user WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = 'Email already registered.';
    } else {
        $sql = "INSERT INTO user (name,email,password,preferred_budget,preferred_time,comfort_level)
                VALUES ('$name','$email','$password',$budget,$time,$comfort)";
        if (mysqli_query($conn, $sql)) {
            $success = 'Account created! You can now log in.';
            $mode = 'login';
        } else {
            $error = 'Registration failed: ' . mysqli_error($conn);
        }
    }
}

// ── LOGIN ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    $result = mysqli_query($conn, "SELECT * FROM user WHERE email='$email'");
    $user   = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'] ?? 'user';
        header("Location: /smart_nav/index.php");
        exit();
    } else {
        // Demo: allow admin@admin.com / admin123
        if ($email === 'admin@admin.com' && $password === 'admin123') {
            $_SESSION['user_id']   = 0;
            $_SESSION['user_name'] = 'Admin';
            $_SESSION['role']      = 'admin';
            header("Location: /smart_nav/index.php");
            exit();
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SmartNav Dhaka</title>
<meta name="description" content="Sign in to SmartNav — Dhaka's multimodal smart navigation system.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg: #060b18; --bg2: #0a1128;
    --surface: rgba(255,255,255,0.04); --surface2: rgba(255,255,255,0.07);
    --border: rgba(255,255,255,0.08); --accent: #00e5a0; --accent2: #38bdf8;
    --accent-g: linear-gradient(135deg, #00e5a0, #38bdf8);
    --text: #e8ecf4; --muted: #7a8599;
    --danger: #ff6b8a;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', -apple-system, sans-serif;
    background: var(--bg); color: var(--text);
    min-height: 100vh;
    display: grid; grid-template-columns: 1fr 1fr;
    overflow: hidden;
}

/* Animated background */
.left-panel {
    display: flex; flex-direction: column;
    justify-content: center; align-items: flex-start;
    padding: 60px;
    position: relative; overflow: hidden;
}
.left-panel::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 30% 50%, rgba(0,229,160,.1) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(56,189,248,.06) 0%, transparent 50%);
}
.left-panel::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(0,229,160,0.08), transparent 70%);
    top: 10%; right: -100px;
    animation: orbFloat 20s ease-in-out infinite alternate;
    filter: blur(60px);
}
@keyframes orbFloat {
    0%   { transform: translate(0, 0) scale(1); }
    50%  { transform: translate(30px, -20px) scale(1.1); }
    100% { transform: translate(-20px, 10px) scale(0.95); }
}

.brand { position: relative; z-index: 1; }
.brand .icon {
    width: 60px; height: 60px;
    background: var(--accent-g);
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(0,229,160,0.25);
}
.brand h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 44px; font-weight: 800; line-height: 1.1;
    margin-bottom: 16px; letter-spacing: -1px;
}
.brand h1 span {
    background: var(--accent-g); -webkit-background-clip: text;
    -webkit-text-fill-color: transparent; background-clip: text;
}
.brand p { color: var(--muted); font-size: 15px; line-height: 1.7; max-width: 400px; }

.features-list { margin-top: 44px; display: flex; flex-direction: column; gap: 14px; }
.feature-item {
    display: flex; align-items: center; gap: 14px;
    color: var(--muted); font-size: 14px;
    transition: color .2s;
}
.feature-item:hover { color: var(--text); }
.feature-item i { color: var(--accent); width: 18px; font-size: 15px; }

/* Right panel — glass auth box */
.right-panel {
    display: flex; align-items: center; justify-content: center;
    padding: 40px;
    background: rgba(255,255,255,0.02);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-left: 1px solid var(--border);
}
.auth-box { width: 100%; max-width: 420px; }
.auth-tabs {
    display: flex; gap: 0; margin-bottom: 32px;
    background: rgba(255,255,255,0.04); border-radius: 12px; padding: 4px;
    border: 1px solid var(--border);
}
.auth-tab {
    flex: 1; padding: 11px; text-align: center;
    border-radius: 10px; cursor: pointer;
    font-size: 14px; font-weight: 500;
    color: var(--muted); text-decoration: none;
    transition: all .3s;
}
.auth-tab.active {
    background: var(--surface2); color: var(--text);
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
}

.form-group { margin-bottom: 18px; }
.form-group label {
    display: block; margin-bottom: 6px;
    font-size: 11px; font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: .6px;
}
.form-group input, .form-group select {
    width: 100%; padding: 12px 16px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 10px; color: var(--text); font-size: 14px;
    font-family: 'Inter', sans-serif; outline: none;
    transition: all .3s;
}
.form-group input:focus, .form-group select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0,229,160,.1), 0 0 20px rgba(0,229,160,.05);
}
.form-group select option { background: #0f1629; }

.btn-full {
    width: 100%; padding: 13px;
    background: var(--accent-g);
    color: #000; font-weight: 700; font-size: 15px;
    border: none; border-radius: 10px; cursor: pointer;
    font-family: 'Poppins', sans-serif; letter-spacing: .3px;
    transition: all .3s;
    box-shadow: 0 4px 16px rgba(0,229,160,.2);
}
.btn-full:hover {
    box-shadow: 0 8px 32px rgba(0,229,160,.35);
    transform: translateY(-1px);
}

.alert {
    padding: 12px 16px; border-radius: 10px; margin-bottom: 18px;
    font-size: 13px; display: flex; align-items: center; gap: 10px;
    backdrop-filter: blur(8px);
}
.alert-error   { background: rgba(255,107,138,.08); border: 1px solid rgba(255,107,138,.2); color: var(--danger); }
.alert-success { background: rgba(0,229,160,.08); border: 1px solid rgba(0,229,160,.2); color: var(--accent); }

.hint { text-align: center; margin-top: 22px; font-size: 12px; color: var(--muted); }
.hint code {
    background: rgba(255,255,255,0.06); padding: 2px 8px;
    border-radius: 4px; font-size: 11px; color: var(--accent2);
}
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

@media (max-width: 768px) {
    body { grid-template-columns: 1fr; }
    .left-panel { display: none; }
}
</style>
</head>
<body>

<div class="left-panel">
  <div class="brand">
    <div class="icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></div>
    <h1>Multimodal<br><span>Smart Nav</span></h1>
    <p>Navigate Dhaka smarter — find the fastest, cheapest multimodal routes using real-time traffic and live incident data.</p>
    <div class="features-list">
      <div class="feature-item"><i class="fa fa-route"></i> Multimodal route optimization</div>
      <div class="feature-item"><i class="fa fa-triangle-exclamation"></i> Crowdsourced Chaos Map</div>
      <div class="feature-item"><i class="fa fa-gauge-high"></i> Real-time traffic analytics</div>
      <div class="feature-item"><i class="fa fa-star"></i> Transport ratings & reviews</div>
      <div class="feature-item"><i class="fa fa-clock-rotate-left"></i> Full trip history & analytics</div>
      <div class="feature-item"><i class="fa fa-shield-halved"></i> Conflict-free route optimization</div>
    </div>
  </div>
</div>

<div class="right-panel">
  <div class="auth-box">
    <div class="auth-tabs">
      <a href="?mode=login"    class="auth-tab <?= $mode==='login'    ? 'active':'' ?>">Login</a>
      <a href="?mode=register" class="auth-tab <?= $mode==='register' ? 'active':'' ?>">Register</a>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $success ?></div><?php endif; ?>

    <?php if ($mode === 'login'): ?>
    <form method="POST">
      <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="you@example.com" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
      <button class="btn-full" name="login">Sign In →</button>
    </form>
    <p class="hint">Demo: <code>admin@admin.com</code> / <code>admin123</code></p>

    <?php else: ?>
    <form method="POST">
      <div class="form-group"><label>Full Name</label><input type="text" name="name" placeholder="Your name" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="you@example.com" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Min 6 characters" required></div>
      <div class="grid-2">
        <div class="form-group"><label>Budget (BDT)</label><input type="number" name="preferred_budget" placeholder="150" min="0"></div>
        <div class="form-group"><label>Max Time (min)</label><input type="number" name="preferred_time" placeholder="60" min="0"></div>
      </div>
      <div class="form-group">
        <label>Comfort Level</label>
        <select name="comfort_level">
          <option value="1">1 — Economy</option>
          <option value="2">2 — Budget</option>
          <option value="3" selected>3 — Standard</option>
          <option value="4">4 — Comfort</option>
          <option value="5">5 — Premium</option>
        </select>
      </div>
      <button class="btn-full" name="register">Create Account →</button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
