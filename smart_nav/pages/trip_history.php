<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle  = 'Trip History';
$activePage = 'history';

$uid = (int)$_SESSION['user_id'];

// ── STATS ────────────────────────────────────────────
$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total, SUM(travel_cost) AS total_cost,
            AVG(travel_time) AS avg_time, AVG(travel_cost) AS avg_cost
     FROM trip_history WHERE user_id = $uid"));

// ── ANALYTICS: trips per day this month ──────────────
$monthly = mysqli_query($conn,
    "SELECT DATE(trip_date) AS d, COUNT(*) AS cnt, SUM(travel_cost) AS cost
     FROM trip_history
     WHERE user_id=$uid AND MONTH(trip_date)=MONTH(CURDATE())
     GROUP BY DATE(trip_date) ORDER BY d");
$m_labels=[]; $m_trips=[]; $m_costs=[];
while($m=mysqli_fetch_assoc($monthly)){
    $m_labels[] = date('M d', strtotime($m['d']));
    $m_trips[]  = $m['cnt'];
    $m_costs[]  = $m['cost'];
}

// ── MOST USED ROUTES ─────────────────────────────────
$fav_routes = mysqli_query($conn,
    "SELECT SL.location_name AS src, DL.location_name AS dst,
            COUNT(*) AS cnt, AVG(TH.travel_cost) AS avg_cost, MIN(TH.travel_time) AS best_time
     FROM trip_history TH
     JOIN route R    ON TH.route_id = R.route_id
     JOIN location SL ON R.source_location_id      = SL.location_id
     JOIN location DL ON R.destination_location_id = DL.location_id
     WHERE TH.user_id = $uid
     GROUP BY R.route_id ORDER BY cnt DESC LIMIT 4");

// ── FULL HISTORY ─────────────────────────────────────
$page = max(1,(int)($_GET['page']??1));
$per  = 10;
$off  = ($page-1)*$per;
$total_trips = $stats['total'] ?? 0;
$total_pages = ceil($total_trips/$per);

$trips = mysqli_query($conn,
    "SELECT TH.trip_id, TH.travel_time, TH.travel_cost, TH.trip_date,
            SL.location_name AS src, DL.location_name AS dst,
            GROUP_CONCAT(DISTINCT TM.transport_type SEPARATOR ' → ') AS transport_chain
     FROM trip_history TH
     JOIN route R    ON TH.route_id = R.route_id
     JOIN location SL ON R.source_location_id      = SL.location_id
     JOIN location DL ON R.destination_location_id = DL.location_id
     LEFT JOIN route_segment RS ON RS.route_id      = R.route_id
     LEFT JOIN transport_mode TM ON RS.transport_id = TM.transport_id
     WHERE TH.user_id = $uid
     GROUP BY TH.trip_id
     ORDER BY TH.trip_date DESC
     LIMIT $per OFFSET $off");

include '../includes/layout.php';
?>

<!-- PERSONAL STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-route"></i></div>
    <div><div class="stat-value"><?= $stats['total'] ?? 0 ?></div><div class="stat-label">Total Trips</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa fa-bangladeshi-taka-sign"></i></div>
    <div><div class="stat-value">৳<?= number_format($stats['total_cost']??0,0) ?></div><div class="stat-label">Total Spent</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa fa-clock"></i></div>
    <div><div class="stat-value"><?= round($stats['avg_time']??0) ?><small style="font-size:14px">m</small></div><div class="stat-label">Avg Trip Time</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa fa-chart-line"></i></div>
    <div><div class="stat-value">৳<?= number_format($stats['avg_cost']??0,0) ?></div><div class="stat-label">Avg Trip Cost</div></div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:24px;align-items:start">

  <!-- MONTHLY CHART -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-calendar-days" style="color:var(--accent2);margin-right:8px"></i>This Month's Trips</span></div>
    <?php if (empty($m_labels)): ?>
      <p style="color:var(--muted);text-align:center;padding:32px">No trips this month yet.</p>
    <?php else: ?>
      <canvas id="tripChart" height="200"></canvas>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
      <script>
      new Chart(document.getElementById('tripChart'), {
        type: 'bar',
        data: {
          labels: <?= json_encode($m_labels) ?>,
          datasets: [{
            label: 'Trips', data: <?= json_encode($m_trips) ?>,
            backgroundColor: 'rgba(0,201,167,0.5)', borderColor: '#00C9A7', borderWidth: 1, borderRadius: 6
          }]
        },
        options: {
          plugins: { legend: { labels: { color: '#8B949E' } } },
          scales: {
            x: { ticks:{color:'#8B949E'}, grid:{color:'#1C2333'} },
            y: { ticks:{color:'#8B949E'}, grid:{color:'#1C2333'}, beginAtZero:true }
          }
        }
      });
      </script>
    <?php endif; ?>
  </div>

  <!-- FAVOURITE ROUTES -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-heart" style="color:var(--danger);margin-right:8px"></i>My Favourite Routes</span></div>
    <?php while($fr=mysqli_fetch_assoc($fav_routes)): ?>
      <div style="padding:14px 0;border-bottom:1px solid var(--border)">
        <div style="font-weight:600;font-size:13.5px;margin-bottom:4px">
          <?= htmlspecialchars($fr['src']) ?> → <?= htmlspecialchars($fr['dst']) ?>
        </div>
        <div style="display:flex;gap:16px;font-size:12px;color:var(--muted)">
          <span><i class="fa fa-repeat" style="color:var(--accent)"></i> <?= $fr['cnt'] ?> trips</span>
          <span><i class="fa fa-bangladeshi-taka-sign" style="color:var(--accent2)"></i> ৳<?= number_format($fr['avg_cost'],0) ?> avg</span>
          <span><i class="fa fa-clock" style="color:var(--warn)"></i> <?= $fr['best_time'] ?>m best</span>
        </div>
      </div>
    <?php endwhile; ?>
    <?php if(mysqli_num_rows($fav_routes)===0): ?>
      <p style="color:var(--muted);text-align:center;padding:32px">Complete trips to see your favourites.</p>
    <?php endif; ?>
  </div>

</div>

<!-- FULL HISTORY TABLE -->
<div class="card">
  <div class="card-header">
    <span class="card-title">All Trips</span>
    <span style="color:var(--muted);font-size:13px"><?= $total_trips ?> total</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Route</th><th>Transport</th><th>Time</th><th>Cost</th><th>Date</th></tr></thead>
      <tbody>
      <?php while($t=mysqli_fetch_assoc($trips)): ?>
        <tr>
          <td style="color:var(--muted)"><?= $t['trip_id'] ?></td>
          <td>
            <div style="font-size:13px"><?= htmlspecialchars($t['src']) ?> → <?= htmlspecialchars($t['dst']) ?></div>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($t['transport_chain'] ?? '—') ?></td>
          <td><?= $t['travel_time'] ?> min</td>
          <td><span class="badge badge-green">৳<?= number_format($t['travel_cost'],0) ?></span></td>
          <td style="color:var(--muted);font-size:12px"><?= date('M d Y, H:i', strtotime($t['trip_date'])) ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if($total_trips===0): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted)">
          No trips yet. <a href="route_finder.php" style="color:var(--accent)">Find a route →</a>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total_pages > 1): ?>
  <div style="display:flex;gap:8px;margin-top:16px;justify-content:center">
    <?php for($p=1;$p<=$total_pages;$p++): ?>
      <a href="?page=<?= $p ?>" style="padding:6px 12px;border-radius:6px;font-size:13px;text-decoration:none;
        background:<?= $p===$page?'var(--accent)':'var(--surface2)' ?>;
        color:<?= $p===$page?'#000':'var(--text)' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include '../includes/layout_end.php'; ?>
