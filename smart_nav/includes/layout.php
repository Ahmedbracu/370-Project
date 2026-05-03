<?php
// includes/layout.php
// Usage: include at top of every page AFTER session_start()
// $pageTitle and $activePage must be set before including
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'SmartNav') ?> — SmartNav Dhaka</title>
<meta name="description" content="Multimodal Smart Navigation System for Dhaka — real-time routes, traffic, and incident mapping.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --sidebar-w: 272px;
    --bg:        #060b18;
    --bg2:       #0a1128;
    --surface:   rgba(255,255,255,0.04);
    --surface2:  rgba(255,255,255,0.07);
    --surface3:  rgba(255,255,255,0.10);
    --border:    rgba(255,255,255,0.08);
    --border2:   rgba(255,255,255,0.12);
    --accent:    #00e5a0;
    --accent2:   #38bdf8;
    --accent-g:  linear-gradient(135deg, #00e5a0, #38bdf8);
    --danger:    #ff6b8a;
    --warn:      #fbbf24;
    --text:      #e8ecf4;
    --muted:     #7a8599;
    --radius:    16px;
    --radius-sm: 10px;
    --shadow:    0 8px 32px rgba(0,0,0,.4);
    --glass:     rgba(255,255,255,0.05);
    --glass-border: rgba(255,255,255,0.08);
    --blur:      blur(16px);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.6;
    overflow-x: hidden;
}

/* ── ANIMATED BACKGROUND ──────────────────────────── */
.bg-orbs {
    position: fixed; inset: 0;
    pointer-events: none; z-index: 0;
    overflow: hidden;
}
.bg-orbs::before,
.bg-orbs::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.12;
    animation: orbFloat 20s ease-in-out infinite alternate;
}
.bg-orbs::before {
    width: 600px; height: 600px;
    background: radial-gradient(circle, #00e5a0, transparent 70%);
    top: -10%; left: 20%;
}
.bg-orbs::after {
    width: 500px; height: 500px;
    background: radial-gradient(circle, #38bdf8, transparent 70%);
    bottom: -10%; right: 10%;
    animation-delay: -10s;
    animation-duration: 25s;
}
@keyframes orbFloat {
    0%   { transform: translate(0, 0) scale(1); }
    50%  { transform: translate(40px, -30px) scale(1.15); }
    100% { transform: translate(-30px, 20px) scale(0.95); }
}

/* ── SCROLLBAR ─────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

/* ── SIDEBAR ────────────────────────────────────────── */
.sidebar {
    width: var(--sidebar-w);
    background: rgba(10,17,40,0.85);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    overflow-y: auto;
}

.sidebar-logo {
    padding: 28px 24px 20px;
    border-bottom: 1px solid var(--border);
}
.sidebar-logo .logo-mark {
    display: flex; align-items: center; gap: 12px;
}
.sidebar-logo .logo-icon {
    width: 40px; height: 40px;
    background: var(--accent-g);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    box-shadow: 0 4px 16px rgba(0,229,160,0.25);
}
.sidebar-logo h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 18px; font-weight: 700;
    color: var(--text); letter-spacing: -0.3px;
}
.sidebar-logo p {
    font-size: 10px; color: var(--muted);
    margin-top: 1px; letter-spacing: 1.2px; text-transform: uppercase;
    font-weight: 500;
}

.sidebar-user {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
}
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--accent-g);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; color: #000;
    flex-shrink: 0;
    box-shadow: 0 2px 12px rgba(0,229,160,0.2);
}
.user-info .name { font-weight: 500; font-size: 13px; }
.user-info .role { font-size: 11px; color: var(--muted); }

.nav-section { padding: 16px 0 8px; }
.nav-label {
    padding: 0 24px 8px;
    font-size: 10px; font-weight: 600;
    color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px;
}
.nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 24px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13.5px; font-weight: 400;
    transition: all .3s cubic-bezier(.4,0,.2,1);
    border-left: 3px solid transparent;
    position: relative;
}
.nav-item:hover {
    color: var(--text);
    background: var(--surface2);
    padding-left: 28px;
}
.nav-item.active {
    color: var(--accent);
    background: rgba(0,229,160,.06);
    border-left-color: var(--accent);
    font-weight: 500;
    box-shadow: inset 0 0 30px rgba(0,229,160,.03);
}
.nav-item i { width: 18px; text-align: center; font-size: 14px; }
.nav-badge {
    margin-left: auto;
    background: var(--danger);
    color: #fff; font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 20px;
    box-shadow: 0 2px 8px rgba(255,107,138,0.3);
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
}
.sidebar-footer a {
    display: flex; align-items: center; gap: 10px;
    color: var(--muted); text-decoration: none; font-size: 13px;
    transition: all .3s;
}
.sidebar-footer a:hover { color: var(--danger); }

/* ── MAIN CONTENT ───────────────────────────────────── */
.main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    position: relative;
    z-index: 1;
}

.topbar {
    background: rgba(10,17,40,0.7);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    border-bottom: 1px solid var(--border);
    padding: 16px 36px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 50;
}
.topbar-title {
    font-family: 'Poppins', sans-serif;
    font-size: 22px; font-weight: 700; color: var(--text);
    letter-spacing: -0.3px;
}
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-time { color: var(--muted); font-size: 13px; font-weight: 500; }

.content {
    padding: 36px;
    flex: 1;
    animation: fadeInContent .5s ease-out;
}
@keyframes fadeInContent {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── GLASS CARDS ────────────────────────────────────── */
.card {
    background: var(--glass);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    padding: 24px;
    box-shadow: 0 4px 24px rgba(0,0,0,.2);
    transition: border-color .3s, box-shadow .3s;
}
.card:hover {
    border-color: var(--border2);
    box-shadow: 0 8px 32px rgba(0,0,0,.3);
}
.card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px;
}
.card-title {
    font-family: 'Poppins', sans-serif;
    font-size: 16px; font-weight: 600;
    letter-spacing: -0.2px;
}

/* ── STAT CARDS ─────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--glass);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    padding: 22px 24px;
    display: flex; align-items: center; gap: 16px;
    transition: all .3s cubic-bezier(.4,0,.2,1);
}
.stat-card:hover {
    border-color: rgba(0,229,160,.25);
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(0,0,0,.3);
}
.stat-icon {
    width: 50px; height: 50px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.stat-icon.green  { background: rgba(0,229,160,.12); color: var(--accent); }
.stat-icon.blue   { background: rgba(56,189,248,.12); color: var(--accent2); }
.stat-icon.red    { background: rgba(255,107,138,.12); color: var(--danger); }
.stat-icon.yellow { background: rgba(251,191,36,.12); color: var(--warn); }
.stat-value {
    font-family: 'Poppins', sans-serif;
    font-size: 28px; font-weight: 800;
    line-height: 1; letter-spacing: -1px;
}
.stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; font-weight: 500; }

/* ── TABLES ─────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th {
    text-align: left; padding: 10px 16px;
    font-size: 10.5px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .8px;
    color: var(--muted); border-bottom: 1px solid var(--border);
}
tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,.04);
    font-size: 13.5px; color: var(--text);
}
tbody tr { transition: background .2s; }
tbody tr:hover { background: var(--surface2); }
tbody tr:last-child td { border-bottom: none; }

/* ── FORMS ──────────────────────────────────────────── */
.form-group { margin-bottom: 18px; }
.form-group label {
    display: block; margin-bottom: 6px;
    font-size: 11px; font-weight: 600;
    color: var(--muted); text-transform: uppercase; letter-spacing: .6px;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%; padding: 11px 16px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text); font-size: 14px;
    font-family: 'Inter', sans-serif;
    transition: all .3s;
    outline: none;
    backdrop-filter: blur(8px);
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0,229,160,.1), 0 0 20px rgba(0,229,160,.05);
}
.form-group select option { background: #0f1629; color: var(--text); }

/* ── BUTTONS ────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: var(--radius-sm);
    font-size: 13.5px; font-weight: 500;
    cursor: pointer; border: none;
    font-family: 'Inter', sans-serif;
    transition: all .3s cubic-bezier(.4,0,.2,1);
    text-decoration: none;
}
.btn-primary {
    background: var(--accent-g);
    color: #000; font-weight: 600;
    box-shadow: 0 4px 16px rgba(0,229,160,0.2);
}
.btn-primary:hover {
    box-shadow: 0 6px 24px rgba(0,229,160,0.35);
    transform: translateY(-1px);
}
.btn-secondary {
    background: var(--surface2);
    color: var(--text);
    border: 1px solid var(--border);
}
.btn-secondary:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(0,229,160,.05);
}
.btn-danger {
    background: rgba(255,107,138,0.15);
    color: var(--danger);
    border: 1px solid rgba(255,107,138,0.2);
}
.btn-danger:hover {
    background: var(--danger);
    color: #fff;
    box-shadow: 0 4px 16px rgba(255,107,138,0.3);
}
.btn-sm { padding: 7px 14px; font-size: 12px; }

/* ── BADGES ─────────────────────────────────────────── */
.badge {
    display: inline-block; padding: 3px 10px;
    border-radius: 20px; font-size: 11px; font-weight: 600;
}
.badge-green  { background: rgba(0,229,160,.12); color: var(--accent); }
.badge-red    { background: rgba(255,107,138,.12); color: var(--danger); }
.badge-yellow { background: rgba(251,191,36,.12); color: var(--warn); }
.badge-blue   { background: rgba(56,189,248,.12); color: var(--accent2); }

/* ── ALERTS ─────────────────────────────────────────── */
.alert {
    padding: 14px 18px; border-radius: var(--radius-sm);
    margin-bottom: 20px; font-size: 13.5px;
    display: flex; align-items: flex-start; gap: 12px;
    backdrop-filter: blur(8px);
}
.alert i { margin-top: 2px; flex-shrink: 0; }
.alert-success { background: rgba(0,229,160,.08); border: 1px solid rgba(0,229,160,.2); color: var(--accent); }
.alert-error   { background: rgba(255,107,138,.08); border: 1px solid rgba(255,107,138,.2); color: var(--danger); }
.alert-info    { background: rgba(56,189,248,.08); border: 1px solid rgba(56,189,248,.2); color: var(--accent2); }

/* ── GRID HELPERS ───────────────────────────────────── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
.mt-20  { margin-top: 20px; }
.mb-20  { margin-bottom: 20px; }
.gap-12 { gap: 12px; }
.flex   { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }

/* ── RESPONSIVE ─────────────────────────────────────── */
@media (max-width: 900px) {
    .sidebar {
        width: 64px;
        backdrop-filter: blur(20px);
    }
    .sidebar-logo p, .sidebar-logo h1,
    .nav-item span, .nav-label, .sidebar-user .user-info,
    .nav-badge { display: none; }
    .sidebar-logo .logo-mark { justify-content: center; }
    .nav-item { justify-content: center; padding: 14px; }
    .nav-item:hover { padding-left: 14px; }
    .main { margin-left: 64px; }
    .grid-2, .grid-3 { grid-template-columns: 1fr; }
    .content { padding: 20px; }
    .topbar { padding: 14px 20px; }
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
}

/* ── INTRO OVERLAY ────────────────────────────── */
.app-intro-overlay {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: var(--bg);
    z-index: 999999;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transition: opacity 0.4s ease, visibility 0.4s ease;
}
.intro-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.intro-icon {
    width: 90px; height: 90px;
    background: rgba(0, 229, 160, 0.1);
    border: 1px solid var(--accent);
    border-radius: 50%;
    display: flex; justify-content: center; align-items: center;
    animation: clickAnim 1.5s ease-in-out forwards;
}
.intro-name {
    font-family: 'Poppins', sans-serif;
    font-size: 36px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: 1px;
    opacity: 0;
    animation: fadeInUp 0.5s ease forwards 0.3s;
}
.intro-footer {
    position: absolute;
    bottom: 30px;
    right: 40px;
    display: flex;
    align-items: center;
    gap: 14px;
    opacity: 0;
    animation: fadeInUp 0.5s ease forwards 0.6s;
}
.intro-footer span {
    font-size: 13px;
    color: var(--muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.intro-studio-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    image-rendering: high-quality;
    border: 2px solid rgba(255,255,255,0.15);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

@keyframes clickAnim {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0,229,160, 0.4); }
    15% { transform: scale(0.85); }
    30% { transform: scale(1.15); box-shadow: 0 0 0 25px rgba(0,229,160, 0); }
    45% { transform: scale(1); }
    100% { transform: scale(1); }
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<!-- ── APP INTRO ──────────────────────────────────────── -->
<div id="app-intro" class="app-intro-overlay">
   <div class="intro-center">
       <div class="intro-icon">
           <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#00e5a0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
       </div>
       <div class="intro-name">SmartNav</div>
   </div>
   <div class="intro-footer">
       <span>developed by HASHARC Studio</span>
       <?php
       $img_path = __DIR__ . '/HASHARC Studio.jpg';
       $base64 = '';
       if (file_exists($img_path)) {
           $data = file_get_contents($img_path);
           $base64 = 'data:image/jpeg;base64,' . base64_encode($data);
       }
       ?>
       <img src="<?= $base64 ?>" alt="HASHARC Studio" class="intro-studio-img">
   </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const intro = document.getElementById('app-intro');
    // Only play intro once per session to avoid annoying users on every page load
    if (!sessionStorage.getItem('smartnav_intro_played')) {
        sessionStorage.setItem('smartnav_intro_played', 'true');
        setTimeout(() => {
            intro.style.opacity = '0';
            intro.style.visibility = 'hidden';
            setTimeout(() => intro.remove(), 400); // Wait for fade out
        }, 3000); // 3.0 seconds duration
    } else {
        intro.remove(); // Remove immediately if already played
    }
});
</script>

<!-- ── ANIMATED BACKGROUND ──────────────────────────── -->
<div class="bg-orbs"></div>

<!-- ── SIDEBAR ──────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">
      <div class="logo-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></div>
      <div>
        <h1>SmartNav</h1>
        <p>Dhaka Navigation</p>
      </div>
    </div>
  </div>

  <?php if (isLoggedIn()): ?>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <div class="user-info">
      <div class="name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="role"><?= ucfirst($user['role']) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <nav>
    <div class="nav-section">
      <div class="nav-label">Main</div>
      <a href="/smart_nav/index.php"              class="nav-item <?= ($activePage??'')==='home'      ? 'active':'' ?>"><i class="fa fa-house"></i><span>Dashboard</span></a>
      <a href="/smart_nav/pages/route_finder.php" class="nav-item <?= ($activePage??'')==='route'     ? 'active':'' ?>"><i class="fa fa-route"></i><span>Route Finder</span></a>
      <a href="/smart_nav/pages/chaos_map.php"    class="nav-item <?= ($activePage??'')==='chaos'     ? 'active':'' ?>"><i class="fa fa-triangle-exclamation"></i><span>Chaos Map</span>
        <?php
          $ic = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM incident_report WHERE status='Active' AND severity='High'"));
          if ($ic && $ic[0] > 0) echo '<span class="nav-badge">'.$ic[0].'</span>';
        ?>
      </a>
      <a href="/smart_nav/pages/traffic.php"      class="nav-item <?= ($activePage??'')==='traffic'   ? 'active':'' ?>"><i class="fa fa-gauge-high"></i><span>Traffic</span></a>
    </div>

    <div class="nav-section">
      <div class="nav-label">User</div>
      <a href="/smart_nav/pages/trip_history.php" class="nav-item <?= ($activePage??'')==='history'   ? 'active':'' ?>"><i class="fa fa-clock-rotate-left"></i><span>Trip History</span></a>
      <a href="/smart_nav/pages/ratings.php"      class="nav-item <?= ($activePage??'')==='ratings'   ? 'active':'' ?>"><i class="fa fa-star"></i><span>Ratings</span></a>
      <a href="/smart_nav/pages/report.php"       class="nav-item <?= ($activePage??'')==='report'    ? 'active':'' ?>"><i class="fa fa-flag"></i><span>Report Incident</span></a>
      <a href="/smart_nav/pages/preferences.php"  class="nav-item <?= ($activePage??'')==='prefs'     ? 'active':'' ?>"><i class="fa fa-sliders"></i><span>My Preferences</span></a>
    </div>

    <?php if (isAdmin()): ?>
    <div class="nav-section">
      <div class="nav-label">Admin</div>
      <a href="/smart_nav/pages/admin.php"         class="nav-item <?= ($activePage??'')==='admin'    ? 'active':'' ?>"><i class="fa fa-gauge"></i><span>Admin Panel</span></a>
      <a href="/smart_nav/pages/admin_routes.php"  class="nav-item <?= ($activePage??'')==='ar'       ? 'active':'' ?>"><i class="fa fa-map"></i><span>Manage Routes</span></a>
      <a href="/smart_nav/pages/admin_fares.php"   class="nav-item <?= ($activePage??'')==='af'       ? 'active':'' ?>"><i class="fa fa-tags"></i><span>Manage Fares</span></a>
      <a href="/smart_nav/pages/admin_transport.php" class="nav-item <?= ($activePage??'')==='at'     ? 'active':'' ?>"><i class="fa fa-bus"></i><span>Transport</span></a>
    </div>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <?php if (isLoggedIn()): ?>
      <a href="/smart_nav/pages/logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
    <?php else: ?>
      <a href="/smart_nav/pages/login.php"><i class="fa fa-right-to-bracket"></i> Login</a>
    <?php endif; ?>
  </div>
</aside>

<!-- ── MAIN ─────────────────────────────────────────────── -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    <div class="topbar-right">
      <span class="topbar-time" id="clock"></span>
      <?php if (!isLoggedIn()): ?>
        <a href="/smart_nav/pages/login.php" class="btn btn-primary btn-sm"><i class="fa fa-right-to-bracket"></i> Login</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="content">
