<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

$pageTitle  = 'Traffic Analytics';
$activePage = 'traffic';

// ── QUERY: latest live updates ─────────────────────────
$live = mysqli_query($conn,
    "SELECT L.location_name, L.area_zone, TD.congestion_level, TD.avg_speed, TD.time_slot
     FROM traffic_data TD
     JOIN location L ON TD.location_id = L.location_id
     ORDER BY TD.date DESC, TD.time_slot DESC LIMIT 3");

// ── QUERY: worst roads today ──────────────────────────
$worst = mysqli_query($conn,
    "SELECT L.location_name, AVG(TD.avg_speed) AS avg_spd,
            TD.congestion_level, COUNT(*) AS records
     FROM traffic_data TD
     JOIN location L ON TD.location_id = L.location_id
     WHERE TD.date = CURDATE()
     GROUP BY TD.location_id, TD.congestion_level
     ORDER BY avg_spd ASC LIMIT 5");

// ── QUERY: hourly average speed (analytics chart) ────
$hourly = mysqli_query($conn,
    "SELECT HOUR(time_slot) AS hr, AVG(avg_speed) AS spd
     FROM traffic_data
     WHERE date = CURDATE()
     GROUP BY HOUR(time_slot)
     ORDER BY hr");
$chart_labels = [];
$chart_data   = [];
while ($h = mysqli_fetch_assoc($hourly)) {
    $chart_labels[] = $h['hr'] . ':00';
    $chart_data[]   = round($h['spd'], 1);
}

// ── QUERY: all traffic data paginated ────────────────
$page = max(1,(int)($_GET['page']??1));
$per  = 12;
$off  = ($page-1)*$per;
$total_td = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM traffic_data"))[0];
$total_pages = ceil($total_td/$per);

$all_traffic = mysqli_query($conn,
    "SELECT TD.*, L.location_name, U.name AS reporter FROM traffic_data TD
     JOIN location L ON TD.location_id = L.location_id
     LEFT JOIN user U ON TD.user_id = U.user_id
     ORDER BY TD.date DESC, TD.time_slot DESC
     LIMIT $per OFFSET $off");

// ── ADD TRAFFIC DATA (Crowdsourced) ───────────────────
$succ = '';
if (isLoggedIn() && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_traffic'])) {
    $uid   = (int)$_SESSION['user_id'];
    $lid   = (int)$_POST['location_id'];
    $cong  = mysqli_real_escape_string($conn, $_POST['congestion_level']);
    $spd   = (float)$_POST['avg_speed'];
    $date  = mysqli_real_escape_string($conn, $_POST['date']);
    $slot  = mysqli_real_escape_string($conn, $_POST['time_slot']);
    $desc  = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    // Fallback if description column isn't created yet
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM traffic_data LIKE 'description'");
    if (mysqli_num_rows($chk) > 0) {
        mysqli_query($conn,
            "INSERT INTO traffic_data (user_id, location_id, congestion_level, avg_speed, date, time_slot, description)
             VALUES ($uid, $lid,'$cong',$spd,'$date','$slot','$desc')");
    } else {
        mysqli_query($conn,
            "INSERT INTO traffic_data (location_id, congestion_level, avg_speed, date, time_slot)
             VALUES ($lid,'$cong',$spd,'$date','$slot')");
    }
    $succ = 'Traffic report submitted! Thank you for helping the community.';
}

$locations = mysqli_query($conn,"SELECT location_id, location_name FROM location ORDER BY location_name");

include '../includes/layout.php';
?>

<!-- LIVE SNAPSHOT -->
<div class="stats-grid" style="margin-bottom:24px">
  <?php
    $live_rows = [];
    while ($r=mysqli_fetch_assoc($live)) $live_rows[] = $r;
    if (empty($live_rows)):
  ?>
    <div class="card" style="grid-column:1/-1;text-align:center;padding:20px;color:var(--muted)">
      No real-time traffic data for the current hour. Add records below.
    </div>
  <?php else: foreach($live_rows as $lr): ?>
    <div class="stat-card">
      <div class="stat-icon <?= $lr['congestion_level']==='Gridlock'?'red':($lr['congestion_level']==='Heavy'?'yellow':'green') ?>">
        <i class="fa fa-gauge-high"></i>
      </div>
      <div>
        <div class="stat-value" style="font-size:20px"><?= $lr['avg_speed'] ?> <small style="font-size:12px">km/h</small></div>
        <div class="stat-label"><?= $lr['location_name'] ?></div>
        <span class="badge <?= $lr['congestion_level']==='Gridlock'?'badge-red':($lr['congestion_level']==='Heavy'?'badge-yellow':'badge-green') ?>" style="margin-top:4px"><?= $lr['congestion_level'] ?></span>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<div class="grid-2" style="align-items:start">

  <!-- CHART -->
  <div class="card">
    <div class="card-header" style="flex-direction:column;align-items:flex-start;gap:6px">
      <span class="card-title"><i class="fa fa-chart-line" style="color:var(--accent);margin-right:8px"></i>Hourly Avg Speed Today</span>
      <span style="font-size:12px;color:var(--muted)">This graph tracks how the city's overall average speed drops during rush hour and rises when roads are clear.</span>
    </div>
    <?php if (empty($chart_data)): ?>
      <p style="color:var(--muted);text-align:center;padding:40px">No data for today yet.</p>
    <?php else: ?>
      <canvas id="speedChart" height="220"></canvas>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
      <script>
      new Chart(document.getElementById('speedChart'), {
        type: 'line',
        data: {
          labels: <?= json_encode($chart_labels) ?>,
          datasets: [{
            label: 'Avg Speed (km/h)',
            data: <?= json_encode($chart_data) ?>,
            borderColor: '#00C9A7',
            backgroundColor: 'rgba(0,201,167,0.08)',
            fill: true, tension: 0.4, pointBackgroundColor: '#00C9A7'
          }]
        },
        options: {
          plugins: { legend: { labels: { color: '#8B949E', font: { family: 'DM Sans' } } } },
          scales: {
            x: { ticks: { color: '#8B949E' }, grid: { color: '#1C2333' } },
            y: { ticks: { color: '#8B949E' }, grid: { color: '#1C2333' } }
          }
        }
      });
      </script>
    <?php endif; ?>
  </div>

  <!-- WORST ROADS + ADD FORM -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><span class="card-title"><i class="fa fa-traffic-light" style="color:var(--danger);margin-right:8px"></i>Worst Roads Today</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Location</th><th>Avg Speed</th><th>Congestion</th></tr></thead>
          <tbody>
          <?php while($w=mysqli_fetch_assoc($worst)): ?>
            <tr>
              <td><?= htmlspecialchars($w['location_name']) ?></td>
              <td><?= round($w['avg_spd'],1) ?> km/h</td>
              <td><span class="badge <?= $w['congestion_level']==='Gridlock'?'badge-red':($w['congestion_level']==='Heavy'?'badge-yellow':'badge-green') ?>"><?= $w['congestion_level'] ?></span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (isLoggedIn()): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa fa-flag" style="color:var(--accent);margin-right:8px"></i>Report Traffic Issue</span></div>
      <p style="font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.4">Help other drivers by reporting live traffic conditions. Your report directly powers the analytics above.</p>
      <?php if ($succ): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $succ ?></div><?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label>Location</label>
          <select name="location_id" required>
            <?php mysqli_data_seek($locations,0); while($l=mysqli_fetch_assoc($locations)): ?>
              <option value="<?= $l['location_id'] ?>"><?= $l['location_name'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="grid-2" style="gap:12px">
          <div class="form-group"><label>Congestion</label>
            <select name="congestion_level">
              <option>Clear</option><option>Moderate</option><option>Heavy</option><option>Gridlock</option>
            </select>
          </div>
          <div class="form-group"><label>Avg Speed (km/h)</label>
            <input type="number" name="avg_speed" step="0.1" required placeholder="e.g. 15">
          </div>
          <div class="form-group"><label>Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group"><label>Time Slot</label>
            <input type="time" name="time_slot" value="<?= date('H:00') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Issue Description</label>
          <textarea name="description" rows="2" placeholder="e.g., Accident at a school, construction roadblock..." required></textarea>
        </div>
        <button class="btn btn-primary" name="add_traffic" style="width:100%;justify-content:center">Submit Report</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- ALL TRAFFIC DATA TABLE -->
<div class="card" style="margin-top:24px">
  <div class="card-header">
    <span class="card-title">All Traffic Records</span>
    <span style="color:var(--muted);font-size:13px"><?= $total_td ?> total</span>
  </div>
  <div class="table-wrap">
    <table style="table-layout: fixed; width: 100%;">
      <thead><tr>
        <th style="width:20%">Location</th>
        <th style="width:12%">Time</th>
        <th style="width:38%">Issue Description</th>
        <th style="width:15%">Speed</th>
        <th style="width:15%">Status</th>
      </tr></thead>
      <tbody>
      <?php while($td=mysqli_fetch_assoc($all_traffic)): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($td['location_name']) ?></td>
          <td style="color:var(--muted);font-size:12px"><?= date('M d', strtotime($td['date'])) ?><br><?= $td['time_slot'] ?></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($td['description'] ?? 'No description') ?>">
            <?php if (!empty($td['description'])): ?>
              <i class="fa fa-comment-dots" style="color:var(--accent);margin-right:6px"></i><?= htmlspecialchars($td['description']) ?>
            <?php else: ?>
              <i>Automated Data</i>
            <?php endif; ?>
            <br><small style="color:rgba(255,255,255,0.2)">by <?= htmlspecialchars($td['reporter'] ?? 'System') ?></small>
          </td>
          <td><?= $td['avg_speed'] ?> <small>km/h</small></td>
          <td><span class="badge <?= $td['congestion_level']==='Gridlock'?'badge-red':($td['congestion_level']==='Heavy'?'badge-yellow':($td['congestion_level']==='Moderate'?'badge-blue':'badge-green')) ?>"><?= $td['congestion_level'] ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
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
