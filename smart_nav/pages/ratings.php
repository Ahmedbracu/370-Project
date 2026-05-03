<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle  = 'Transport Ratings';
$activePage = 'ratings';

$uid = (int)$_SESSION['user_id'];
$msg = '';
$err = '';

// ── SUBMIT REVIEW ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_review'])) {
    if ($uid === 0) {
        $err = 'Demo admin cannot submit reviews. Please log in as a regular user.';
    } else {
        $tid       = (int)$_POST['transport_id'];
        $route_val = !empty($_POST['route_id']) ? (int)$_POST['route_id'] : 'NULL';
        $rating    = max(1,min(5,(int)$_POST['rating']));
        $comment   = mysqli_real_escape_string($conn, trim($_POST['comment']));
        mysqli_query($conn,
            "INSERT INTO transport_reviews (user_id, transport_id, route_id, rating, comment, timestamp)
             VALUES ($uid, $tid, $route_val, $rating, '$comment', NOW())");
        $msg = 'Review submitted! Thank you.';
    }
}

// ── OVERALL RATINGS ───────────────────────────────────
$overall = mysqli_query($conn,
    "SELECT TM.transport_id, TM.transport_type, TM.base_fare, TM.average_speed,
            AVG(TR.rating) AS avg_r, COUNT(TR.review_id) AS cnt
     FROM transport_mode TM
     LEFT JOIN transport_reviews TR ON TM.transport_id = TR.transport_id
     GROUP BY TM.transport_id ORDER BY avg_r DESC");

// ── RECENT REVIEWS ────────────────────────────────────
$reviews = mysqli_query($conn,
    "SELECT TR.rating, TR.comment, TR.timestamp,
            U.name, TM.transport_type,
            SL.location_name AS src, DL.location_name AS dst
     FROM transport_reviews TR
     JOIN user U ON TR.user_id = U.user_id
     JOIN transport_mode TM ON TR.transport_id = TM.transport_id
     LEFT JOIN route R ON TR.route_id = R.route_id
     LEFT JOIN location SL ON R.source_location_id = SL.location_id
     LEFT JOIN location DL ON R.destination_location_id = DL.location_id
     ORDER BY TR.timestamp DESC LIMIT 10");

// ── TRANSPORTS FOR DROPDOWN ───────────────────────────
$transports = mysqli_query($conn,"SELECT transport_id, transport_type FROM transport_mode ORDER BY transport_type");

// ── ROUTES FOR DROPDOWN ───────────────────────────────
$routes_list = mysqli_query($conn,
    "SELECT R.route_id, SL.location_name AS s, DL.location_name AS d 
     FROM route R
     JOIN location SL ON R.source_location_id = SL.location_id
     JOIN location DL ON R.destination_location_id = DL.location_id
     ORDER BY s, d");

include '../includes/layout.php';
?>

<div class="grid-2" style="align-items:start">

  <!-- RATINGS OVERVIEW -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><span class="card-title"><i class="fa fa-star" style="color:var(--warn);margin-right:8px"></i>Transport Ratings Overview</span></div>
      <?php while($r=mysqli_fetch_assoc($overall)): ?>
        <?php $pct = $r['avg_r'] ? ($r['avg_r']/5)*100 : 0; ?>
        <div style="margin-bottom:20px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div>
              <span style="font-weight:600;font-size:14px"><?= htmlspecialchars($r['transport_type']) ?></span>
              <span style="color:var(--muted);font-size:12px;margin-left:8px">৳<?= $r['base_fare'] ?> base · <?= $r['average_speed'] ?> km/h</span>
            </div>
            <div style="text-align:right">
              <span style="color:var(--accent);font-weight:700"><?= $r['avg_r'] ? number_format($r['avg_r'],1) : '—' ?></span>
              <span style="color:var(--muted);font-size:12px"> / 5 (<?= $r['cnt'] ?> reviews)</span>
            </div>
          </div>
          <div style="background:var(--bg);border-radius:20px;height:8px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:20px;transition:width .4s ease"></div>
          </div>
          <!-- Stars display -->
          <div style="margin-top:6px;display:flex;gap:2px">
            <?php for($s=1;$s<=5;$s++): ?>
              <span style="color:<?= $s<=round($r['avg_r']??0)?'#FFC947':'#30363D' ?>;font-size:13px">★</span>
            <?php endfor; ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- RECENT REVIEWS FEED -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa fa-comments" style="color:var(--accent2);margin-right:8px"></i>Recent Reviews</span></div>
      <?php while($rv=mysqli_fetch_assoc($reviews)): ?>
        <div style="padding:14px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#000">
                <?= strtoupper(substr($rv['name'],0,1)) ?>
              </div>
              <div>
                <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($rv['name']) ?></span>
                <span class="badge badge-blue" style="margin-left:8px;font-size:10px"><?= $rv['transport_type'] ?></span>
                <?php if ($rv['src'] && $rv['dst']): ?>
                  <span class="badge badge-yellow" style="margin-left:4px;font-size:10px;background:rgba(255,201,71,0.1);color:#FFC947;border:1px solid rgba(255,201,71,0.3)">
                    <?= htmlspecialchars($rv['src']) ?> → <?= htmlspecialchars($rv['dst']) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:1px">
              <?php for($s=1;$s<=5;$s++): ?>
                <span style="color:<?= $s<=$rv['rating']?'#FFC947':'#30363D' ?>;font-size:12px">★</span>
              <?php endfor; ?>
            </div>
          </div>
          <?php if ($rv['comment']): ?>
            <p style="color:var(--muted);font-size:13px;margin-top:4px">"<?= htmlspecialchars($rv['comment']) ?>"</p>
          <?php endif; ?>
          <div style="color:var(--muted);font-size:11px;margin-top:4px"><?= date('M d Y, H:i', strtotime($rv['timestamp'])) ?></div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- SUBMIT REVIEW FORM -->
  <div class="card">
    <div class="card-header"><span class="card-title">Write a Review</span></div>
    <?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= $err ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Transport Mode</label>
        <select name="transport_id" required>
          <option value="">— Choose transport —</option>
          <?php while($t=mysqli_fetch_assoc($transports)): ?>
            <option value="<?= $t['transport_id'] ?>"><?= $t['transport_type'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Route / Location (optional)</label>
        <select name="route_id">
          <option value="">— General review (No specific route) —</option>
          <?php while($rt=mysqli_fetch_assoc($routes_list)): ?>
            <option value="<?= $rt['route_id'] ?>"><?= htmlspecialchars($rt['s']) ?> → <?= htmlspecialchars($rt['d']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Your Rating</label>
        <div id="star-rating" style="display:flex;gap:8px;margin-top:6px">
          <?php for($s=1;$s<=5;$s++): ?>
            <label style="cursor:pointer">
              <input type="radio" name="rating" value="<?= $s ?>" style="display:none" <?= $s===3?'checked':'' ?>>
              <span class="star-btn" data-val="<?= $s ?>" style="font-size:32px;color:#FFC947;cursor:pointer;transition:transform .15s" onclick="setRating(<?= $s ?>)">★</span>
            </label>
          <?php endfor; ?>
        </div>
        <div id="rating-label" style="color:var(--muted);font-size:12px;margin-top:6px">3 — Good</div>
      </div>

      <div class="form-group">
        <label>Comment (optional)</label>
        <textarea name="comment" rows="4" placeholder="Share your experience..." style="resize:vertical"></textarea>
      </div>

      <button class="btn btn-primary" name="submit_review" style="width:100%;justify-content:center">
        <i class="fa fa-star"></i> Submit Review
      </button>
    </form>

    <!-- MY REVIEWS -->
    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
      <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px">My Reviews</div>
      <?php
        $my_reviews = mysqli_query($conn,
          "SELECT TR.rating, TR.comment, TR.timestamp, TM.transport_type,
                  SL.location_name AS src, DL.location_name AS dst
           FROM transport_reviews TR
           JOIN transport_mode TM ON TR.transport_id = TM.transport_id
           LEFT JOIN route R ON TR.route_id = R.route_id
           LEFT JOIN location SL ON R.source_location_id = SL.location_id
           LEFT JOIN location DL ON R.destination_location_id = DL.location_id
           WHERE TR.user_id = $uid ORDER BY TR.timestamp DESC LIMIT 5");
        $rcount = 0;
        while($mr=mysqli_fetch_assoc($my_reviews)):
          $rcount++;
      ?>
        <div style="padding:10px 0;border-bottom:1px solid rgba(48,54,61,.4);font-size:13px">
          <div style="display:flex;justify-content:space-between">
            <span>
              <?= $mr['transport_type'] ?>
              <?php if ($mr['src'] && $mr['dst']): ?>
                <span style="font-size:11px;color:var(--muted);margin-left:6px">(<?= htmlspecialchars($mr['src']) ?> → <?= htmlspecialchars($mr['dst']) ?>)</span>
              <?php endif; ?>
            </span>
            <span>
              <?php for($s=1;$s<=5;$s++): ?>
                <span style="color:<?= $s<=$mr['rating']?'#FFC947':'#30363D' ?>">★</span>
              <?php endfor; ?>
            </span>
          </div>
          <?php if($mr['comment']): ?>
            <p style="color:var(--muted);margin-top:3px">"<?= htmlspecialchars($mr['comment']) ?>"</p>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
      <?php if(!$rcount): ?>
        <p style="color:var(--muted)">You haven't reviewed anything yet.</p>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
const labels = ['','Terrible','Poor','Good','Very Good','Excellent'];
function setRating(val) {
    document.querySelectorAll('.star-btn').forEach((s,i) => {
        s.style.color   = i < val ? '#FFC947' : '#30363D';
        s.style.transform = i < val ? 'scale(1.1)' : 'scale(1)';
    });
    document.getElementById('rating-label').textContent = val + ' — ' + labels[val];
}
setRating(3);
</script>

<?php include '../includes/layout_end.php'; ?>
