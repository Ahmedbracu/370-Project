<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle  = 'Admin Panel';
$activePage = 'admin';

// ── SYSTEM STATS ─────────────────────────────────────
$stats = [
    'users'      => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM user"))[0],
    'routes'     => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM route"))[0],
    'segments'   => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM route_segment"))[0],
    'trips'      => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM trip_history"))[0],
    'incidents'  => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM incident_report WHERE status='Active'"))[0],
    'traffic'    => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM traffic_data"))[0],
    'reviews'    => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM transport_reviews"))[0],
    'cached'     => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM cached_routes"))[0],
];

// ── RECENT USERS ─────────────────────────────────────
$recent_users = mysqli_query($conn,
    "SELECT user_id, name, email, preferred_budget, comfort_level FROM user ORDER BY user_id DESC LIMIT 6");

// ── REVENUE ANALYTICS ────────────────────────────────
$revenue = mysqli_query($conn,
    "SELECT DATE(trip_date) AS d, SUM(travel_cost) AS rev, COUNT(*) AS cnt
     FROM trip_history GROUP BY DATE(trip_date) ORDER BY d DESC LIMIT 7");
$rev_labels=[]; $rev_data=[]; $rev_trips=[];
while($r=mysqli_fetch_assoc($revenue)){
    array_unshift($rev_labels, date('M d',strtotime($r['d'])));
    array_unshift($rev_data,   (float)$r['rev']);
    array_unshift($rev_trips,  (int)$r['cnt']);
}

include '../includes/layout.php';
?>

<!-- ADMIN STATS GRID -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
  <?php
    $items = [
      ['Users',         $stats['users'],    'fa-users',        'green'],
      ['Routes',        $stats['routes'],   'fa-route',        'blue'],
      ['Trip Logs',     $stats['trips'],    'fa-clock-rotate-left','yellow'],
      ['Active Issues', $stats['incidents'],'fa-triangle-exclamation','red'],
      ['Segments',      $stats['segments'], 'fa-bezier-curve', 'blue'],
      ['Traffic Recs',  $stats['traffic'],  'fa-gauge-high',   'green'],
      ['Reviews',       $stats['reviews'],  'fa-star',         'yellow'],
      ['Cached Routes', $stats['cached'],   'fa-database',     'green'],
    ];
    foreach($items as [$label,$val,$icon,$color]):
  ?>
    <div class="stat-card">
      <div class="stat-icon <?= $color ?>"><i class="fa <?= $icon ?>"></i></div>
      <div><div class="stat-value" style="font-size:24px"><?= $val ?></div><div class="stat-label"><?= $label ?></div></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- QUICK LINKS -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <a href="admin_routes.php"    class="btn btn-secondary"><i class="fa fa-route"></i> Manage Routes</a>
  <a href="admin_fares.php"     class="btn btn-secondary"><i class="fa fa-tags"></i> Manage Fares</a>
  <a href="admin_transport.php" class="btn btn-secondary"><i class="fa fa-bus"></i> Transport Modes</a>
  <a href="chaos_map.php"       class="btn btn-secondary"><i class="fa fa-map"></i> Chaos Map</a>
  <a href="traffic.php"         class="btn btn-secondary"><i class="fa fa-gauge-high"></i> Traffic Data</a>
</div>

<div class="grid-2" style="align-items:start">

  <!-- REVENUE CHART -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-coins" style="color:var(--warn);margin-right:8px"></i>Revenue (Last 7 Days)</span></div>
    <?php if(empty($rev_labels)): ?>
      <p style="color:var(--muted);text-align:center;padding:32px">No trip data yet.</p>
    <?php else: ?>
      <canvas id="revChart" height="200"></canvas>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
      <script>
      new Chart(document.getElementById('revChart'),{
        type:'bar',
        data:{
          labels:<?= json_encode($rev_labels) ?>,
          datasets:[
            {label:'Revenue (BDT)',data:<?= json_encode($rev_data) ?>,backgroundColor:'rgba(0,201,167,0.5)',borderColor:'#00C9A7',borderWidth:1,borderRadius:6,yAxisID:'y'},
            {label:'Trips',data:<?= json_encode($rev_trips) ?>,type:'line',borderColor:'#0EA5E9',backgroundColor:'transparent',tension:0.4,yAxisID:'y1'}
          ]
        },
        options:{
          plugins:{legend:{labels:{color:'#8B949E'}}},
          scales:{
            x:{ticks:{color:'#8B949E'},grid:{color:'#1C2333'}},
            y:{ticks:{color:'#8B949E'},grid:{color:'#1C2333'},beginAtZero:true},
            y1:{ticks:{color:'#8B949E'},position:'right',grid:{display:false},beginAtZero:true}
          }
        }
      });
      </script>
    <?php endif; ?>
  </div>

  <!-- RECENT USERS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-users" style="color:var(--accent2);margin-right:8px"></i>Registered Users</span>
      <span style="color:var(--muted);font-size:13px"><?= $stats['users'] ?> total</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Budget</th><th>Comfort</th></tr></thead>
        <tbody>
        <?php while($u=mysqli_fetch_assoc($recent_users)): ?>
          <tr>
            <td style="color:var(--muted)"><?= $u['user_id'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge badge-green">৳<?= number_format($u['preferred_budget'],0) ?></span></td>
            <td><?= str_repeat('★',$u['comfort_level']??0) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- DB TABLE OVERVIEW -->
<div class="card" style="margin-top:24px">
  <div class="card-header"><span class="card-title"><i class="fa fa-database" style="color:var(--accent2);margin-right:8px"></i>Database Overview</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Table</th><th>Records</th><th>Purpose</th><th>Action</th></tr></thead>
      <tbody>
        <?php
          $tables = [
            ['user',                 $stats['users'],    'Registered users & preferences'],
            ['route',                $stats['routes'],   'Available routes between locations'],
            ['route_segment',        $stats['segments'], 'Multimodal segments per route'],
            ['trip_history',         $stats['trips'],    'User travel logs & analytics'],
            ['incident_report',      $stats['incidents'],'Crowdsourced Chaos Map data'],
            ['traffic_data',         $stats['traffic'],  'Historical traffic patterns'],
            ['transport_reviews',    $stats['reviews'],  'User ratings & feedback'],
            ['cached_routes',        $stats['cached'],   'Route query cache for performance'],
          ];
          foreach($tables as [$tbl,$cnt,$desc]):
        ?>
        <tr>
          <td><code style="color:var(--accent2);font-size:12px"><?= $tbl ?></code></td>
          <td><span class="badge badge-blue"><?= $cnt ?></span></td>
          <td style="color:var(--muted);font-size:13px"><?= $desc ?></td>
          <td>
            <a href="http://localhost/phpmyadmin/index.php?route=/table/browse&db=smart_navigation_db&table=<?= $tbl ?>"
               target="_blank" class="btn btn-secondary btn-sm">
              <i class="fa fa-arrow-up-right-from-square"></i> phpMyAdmin
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
