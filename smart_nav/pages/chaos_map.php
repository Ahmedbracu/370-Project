<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

$pageTitle  = 'Chaos Map';
$activePage = 'chaos';

// ── UPDATE STATUS (admin) ──────────────────────────────
if (isAdmin() && isset($_POST['update_status'])) {
    $id     = (int)$_POST['incident_id'];
    $status = in_array($_POST['status'], ['Active','Resolved','Under Review']) ? $_POST['status'] : 'Active';
    mysqli_query($conn, "UPDATE incident_report SET status='$status' WHERE incident_id=$id");
}

// ── FETCH INCIDENTS ──────────────────────────
$filter_sev = $_GET['severity'] ?? 'all';
$filter_type= $_GET['type']     ?? 'all';

$where_inc = ["I.status = 'Active'"];
if ($filter_sev !== 'all') {
    $fs = mysqli_real_escape_string($conn, $filter_sev);
    $where_inc[] = "I.severity = '$fs'";
}
if ($filter_type !== 'all') {
    $ft = mysqli_real_escape_string($conn, $filter_type);
    $where_inc[] = "I.incident_type = '$ft'";
}
$wc_inc = implode(' AND ', $where_inc);

$incidents = mysqli_query($conn,
    "SELECT I.incident_id as id, I.incident_type, I.severity, I.timestamp, L.location_name, L.latitude, L.longitude, U.name AS reporter, I.status, 'incident' as source
     FROM incident_report I
     JOIN location L ON I.location_id = L.location_id
     JOIN user U     ON I.user_id     = U.user_id
     WHERE $wc_inc
     ORDER BY I.timestamp DESC");

$rows = [];
while ($r = mysqli_fetch_assoc($incidents)) $rows[] = $r;

// ── FETCH TRAFFIC DATA (Map as 'Traffic Jam') ─────────
$where_trf = ["TD.date = CURDATE()", "(TD.congestion_level = 'Heavy' OR TD.congestion_level = 'Gridlock')"];
if ($filter_sev !== 'all') {
    if ($filter_sev === 'High') {
        $where_trf[] = "TD.congestion_level = 'Gridlock'";
    } elseif ($filter_sev === 'Medium') {
        $where_trf[] = "TD.congestion_level = 'Heavy'";
    } else {
        $where_trf[] = "1=0"; // Low severity doesn't map to Heavy/Gridlock
    }
}
if ($filter_type !== 'all' && $filter_type !== 'Traffic Jam') {
    $where_trf[] = "1=0"; // If filtering by Accident, don't show traffic data
}
$wc_trf = implode(' AND ', $where_trf);

// Note: Suppress error if description column doesn't exist yet on live by not selecting it here, or we just rely on standard fields
$traffic_q = "SELECT 0 as id, 'Traffic Jam' as incident_type, 
             IF(TD.congestion_level='Gridlock', 'High', 'Medium') as severity,
             CONCAT(TD.date, ' ', TD.time_slot) as timestamp,
             L.location_name, L.latitude, L.longitude, COALESCE(U.name, 'System') AS reporter, 'Active' as status, 'traffic' as source
             FROM traffic_data TD
             JOIN location L ON TD.location_id = L.location_id
             LEFT JOIN user U ON TD.user_id = U.user_id
             WHERE $wc_trf";

$traffic = @mysqli_query($conn, $traffic_q);
if ($traffic) {
    while ($r = mysqli_fetch_assoc($traffic)) $rows[] = $r;
}

// Sort combined rows
usort($rows, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// For map markers JSON
$map_data = json_encode(array_map(fn($r) => [
    'lat'  => (float)$r['latitude'],
    'lng'  => (float)$r['longitude'],
    'name' => $r['location_name'],
    'type' => $r['incident_type'],
    'sev'  => $r['severity'],
    'time' => date('M d H:i', strtotime($r['timestamp'])),
], $rows));

// Summary counts
$cnt_high   = count(array_filter($rows, fn($r)=>$r['severity']==='High'));
$cnt_medium = count(array_filter($rows, fn($r)=>$r['severity']==='Medium'));
$cnt_low    = count(array_filter($rows, fn($r)=>$r['severity']==='Low'));

// Distinct types for filter
$types = mysqli_query($conn,"SELECT DISTINCT incident_type FROM incident_report ORDER BY incident_type");

include '../includes/layout.php';
?>

<!-- SUMMARY BADGES -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <div class="stat-card" style="padding:14px 20px;gap:12px">
    <div class="stat-icon red"><i class="fa fa-fire"></i></div>
    <div><div class="stat-value" style="font-size:22px"><?= $cnt_high ?></div><div class="stat-label">High Severity</div></div>
  </div>
  <div class="stat-card" style="padding:14px 20px;gap:12px">
    <div class="stat-icon yellow"><i class="fa fa-circle-exclamation"></i></div>
    <div><div class="stat-value" style="font-size:22px"><?= $cnt_medium ?></div><div class="stat-label">Medium</div></div>
  </div>
  <div class="stat-card" style="padding:14px 20px;gap:12px">
    <div class="stat-icon green"><i class="fa fa-circle-info"></i></div>
    <div><div class="stat-value" style="font-size:22px"><?= $cnt_low ?></div><div class="stat-label">Low</div></div>
  </div>
  <div class="stat-card" style="padding:14px 20px;gap:12px;margin-left:auto">
    <a href="/smart_nav/pages/report.php" class="btn btn-primary"><i class="fa fa-flag"></i> Report Incident</a>
  </div>
</div>

<!-- FILTERS -->
<div class="card" style="margin-bottom:20px;padding:16px 24px">
  <form method="GET" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Severity</label>
      <select name="severity" onchange="this.form.submit()" style="padding:7px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px">
        <option value="all" <?= $filter_sev==='all'?'selected':'' ?>>All</option>
        <option value="High"   <?= $filter_sev==='High'  ?'selected':'' ?>>High</option>
        <option value="Medium" <?= $filter_sev==='Medium'?'selected':'' ?>>Medium</option>
        <option value="Low"    <?= $filter_sev==='Low'   ?'selected':'' ?>>Low</option>
      </select>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <label style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Type</label>
      <select name="type" onchange="this.form.submit()" style="padding:7px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px">
        <option value="all">All Types</option>
        <?php 
          $all_types = [];
          $types_q = mysqli_query($conn,"SELECT DISTINCT incident_type FROM incident_report");
          while($tp = mysqli_fetch_assoc($types_q)) $all_types[] = $tp['incident_type'];
          if (!in_array('Traffic Jam', $all_types)) $all_types[] = 'Traffic Jam'; // Ensure it's an option
          sort($all_types);
          foreach($all_types as $tp):
        ?>
          <option value="<?= htmlspecialchars($tp) ?>" <?= $filter_type===$tp?'selected':'' ?>><?= htmlspecialchars($tp) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($filter_sev!=='all'||$filter_type!=='all'): ?>
      <a href="chaos_map.php" class="btn btn-secondary btn-sm">Clear Filters</a>
    <?php endif; ?>
  </form>
</div>

<div class="grid-2" style="align-items:start">

  <!-- MAP VISUAL -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa fa-map-pin" style="color:var(--danger);margin-right:8px"></i>Incident Map (Dhaka)</span></div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <div id="incident-map" style="width:100%;height:450px;border-radius:14px;overflow:hidden;background:var(--bg2,#0a1128);border:1px solid rgba(0,229,160,.15);position:relative;box-shadow:0 0 40px rgba(0,229,160,.04); z-index: 1;">
      <div style="position:absolute;bottom:20px;left:20px;display:flex;gap:14px;font-size:11px;background:rgba(6,11,24,0.85);backdrop-filter:blur(8px);padding:8px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.06); z-index: 1000;">
        <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#ff6b8a;border-radius:50%;display:inline-block;box-shadow:0 0 6px #ff6b8a"></span>High</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#fbbf24;border-radius:50%;display:inline-block;box-shadow:0 0 6px #fbbf24"></span>Medium</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#00e5a0;border-radius:50%;display:inline-block;box-shadow:0 0 6px #00e5a0"></span>Low</span>
      </div>
    </div>
  </div>

  <!-- INCIDENT LIST -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Active Incidents (<?= count($rows) ?>)</span>
    </div>
    <div style="max-height:420px;overflow-y:auto">
      <?php if (empty($rows)): ?>
        <div style="text-align:center;padding:40px;color:var(--muted)">
          <div style="margin-bottom:12px"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          No active incidents — roads are clear!
        </div>
      <?php endif; ?>

      <?php foreach ($rows as $inc): ?>
        <div style="padding:14px;border-bottom:1px solid var(--border);transition:background .15s" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px">
            <div>
              <span style="font-weight:600;font-size:13.5px"><?= htmlspecialchars($inc['incident_type']) ?></span>
              <span style="color:var(--muted);font-size:12px;margin-left:8px">@ <?= htmlspecialchars($inc['location_name']) ?></span>
            </div>
            <?php
              $sc = $inc['severity']==='High'?'badge-red':($inc['severity']==='Medium'?'badge-yellow':'badge-green');
            ?>
            <span class="badge <?= $sc ?>"><?= $inc['severity'] ?></span>
          </div>
          <div style="display:flex;gap:16px;font-size:11.5px;color:var(--muted)">
            <span><i class="fa fa-user"></i> <?= htmlspecialchars($inc['reporter']) ?></span>
            <span><i class="fa fa-clock"></i> <?= date('M d, H:i', strtotime($inc['timestamp'])) ?></span>
          </div>
          <?php if (isAdmin() && $inc['source'] === 'incident'): ?>
          <form method="POST" style="margin-top:8px;display:flex;gap:8px;align-items:center">
            <input type="hidden" name="incident_id" value="<?= $inc['id'] ?>">
            <select name="status" style="padding:5px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px">
              <?php foreach(['Active','Resolved','Under Review'] as $s): ?>
                <option value="<?= $s ?>" <?= $inc['status']===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary btn-sm" name="update_status">Update</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
// Plot incidents on Interactive Leaflet Map
const incidents = <?= $map_data ?>;

const map = L.map('incident-map', {
    zoomControl: false 
}).setView([23.8103, 90.4125], 11);

L.control.zoom({ position: 'topright' }).addTo(map);

// Add CartoDB Dark Matter tile layer for that clean, glassmorphism-friendly aesthetic
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
    subdomains: 'abcd',
    maxZoom: 19
}).addTo(map);

// Custom SVG Map Pin
const svgIcon = (color) => `
<svg width="32" height="32" viewBox="0 0 24 24" fill="${color}" stroke="#0a1128" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter:drop-shadow(0px 4px 6px rgba(0,0,0,0.4))">
  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
  <circle cx="12" cy="10" r="3" fill="#0a1128"></circle>
</svg>
`;

incidents.forEach(inc => {
    const color = inc.sev==='High' ? '#ff6b8a' : inc.sev==='Medium' ? '#fbbf24' : '#00e5a0';
    
    // Interactive SVG Marker
    const customIcon = L.divIcon({
        className: 'custom-svg-icon',
        html: svgIcon(color),
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
    });

    const marker = L.marker([inc.lat, inc.lng], {
        icon: customIcon
    }).addTo(map);

    // Glowing pulse effect behind the SVG for High severity
    if (inc.sev === 'High') {
        L.circleMarker([inc.lat, inc.lng], {
            radius: 24,
            fillColor: color,
            color: 'transparent',
            fillOpacity: 0.15,
            className: 'pulse-ring'
        }).addTo(map);
    }

    // Popup content
    const popupHtml = `
        <div style="font-family:'Inter',sans-serif;color:#111;min-width:180px">
            <div style="font-weight:700;font-size:14px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center">
                <span>${inc.type}</span>
                <span class="badge ${inc.sev==='High'?'badge-red':inc.sev==='Medium'?'badge-yellow':'badge-green'}" style="font-size:9px;padding:2px 6px">${inc.sev}</span>
            </div>
            <div style="font-size:12px;color:#555;margin-bottom:8px"><strong>Location:</strong> ${inc.name}</div>
            <div style="font-size:11px;color:#888;display:flex;justify-content:space-between;border-top:1px solid #eee;padding-top:8px">
                <span><i class="fa fa-user"></i> ${inc.reporter||'User'}</span>
                <span>${inc.time}</span>
            </div>
        </div>
    `;
    
    marker.bindPopup(popupHtml);
});
</script>

<style>
/* Leaflet UI Overrides */
.leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); padding: 2px; }
.leaflet-popup-content { margin: 14px; }
.leaflet-popup-tip { box-shadow: 0 10px 25px rgba(0,0,0,0.3); }

/* Pulse animation */
@keyframes incidentPulse { 0%,100%{stroke-width:0;fill-opacity:0.25} 50%{stroke-width:0;fill-opacity:0.05} }
.pulse-ring path { animation: incidentPulse 2s ease-in-out infinite; }
</style>

<?php include '../includes/layout_end.php'; ?>
