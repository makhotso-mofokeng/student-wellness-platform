<?php
session_start();
include 'connect.php';

// Only logged-in mentors can see analytics
if (!isset($_SESSION['mentor_id'])) {
    header("Location: mentor_login.php");
    exit();
}

$faculty = $_SESSION['mentor_faculty'];
$mentor_name = $_SESSION['mentor_name'];

// ---- Totals for this faculty ----
$stmt = $conn->prepare("SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending, SUM(status = 'resolved') AS resolved, SUM(urgency = 'high' AND status = 'pending') AS high_pending, SUM(student_id IS NULL) AS anonymous FROM support_requests WHERE faculty = ?");
$stmt->bind_param("s", $faculty);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
foreach (['total', 'pending', 'resolved', 'high_pending', 'anonymous'] as $k) {
    $t[$k] = (int)($t[$k] ?? 0);
}
$resolved_pct = $t['total'] > 0 ? round($t['resolved'] / $t['total'] * 100) : 0;

// ---- Counts grouped by a column ----
function fetch_counts($conn, $column, $faculty) {
    $allowed = ['category', 'urgency', 'status'];
    if (!in_array($column, $allowed)) {
        die("Invalid column");
    }
    $stmt = $conn->prepare("SELECT COALESCE($column, 'unclassified') AS label, COUNT(*) AS n FROM support_requests WHERE faculty = ? GROUP BY label ORDER BY n DESC");
    $stmt->bind_param("s", $faculty);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

$by_category = fetch_counts($conn, 'category', $faculty);
$by_urgency = fetch_counts($conn, 'urgency', $faculty);

// ---- Requests per day, last 14 days ----
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
$start = array_key_first($days);
$stmt = $conn->prepare("SELECT date_submitted, COUNT(*) AS n FROM support_requests WHERE faculty = ? AND date_submitted >= ? GROUP BY date_submitted");
$stmt->bind_param("ss", $faculty, $start);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    if (isset($days[$r['date_submitted']])) {
        $days[$r['date_submitted']] = (int)$r['n'];
    }
}
$day_max = max($days);

// ---- Feedback ratings (platform-wide, no names shown) ----
$fb = $conn->query("SELECT COUNT(rating) AS n, AVG(rating) AS avg_rating FROM feedback WHERE rating IS NOT NULL")->fetch_assoc();
$ratings = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$res = $conn->query("SELECT rating, COUNT(*) AS n FROM feedback WHERE rating IS NOT NULL GROUP BY rating");
while ($r = $res->fetch_assoc()) {
    if (isset($ratings[(int)$r['rating']])) {
        $ratings[(int)$r['rating']] = (int)$r['n'];
    }
}
$rating_rows = [];
foreach ($ratings as $k => $n) {
    $rating_rows[] = ['label' => $k . ($k == 1 ? ' star' : ' stars'), 'n' => $n];
}
$avg_text = ((int)$fb['n'] > 0) ? number_format($fb['avg_rating'], 1) . " / 5" : "No ratings yet";

// ---- Simple bar chart helper (pure HTML/CSS) ----
function bar_chart($rows, $default_color, $color_map = []) {
    $max = 0;
    foreach ($rows as $r) {
        if ($r['n'] > $max) {
            $max = $r['n'];
        }
    }
    if ($max == 0) {
        echo "<p style='color:#666;'>No data yet.</p>";
        return;
    }
    foreach ($rows as $r) {
        $w = round(($r['n'] / $max) * 100);
        $color = $color_map[$r['label']] ?? $default_color;
        echo "<div style='margin-bottom:10px;'>";
        echo "<div style='display:flex; justify-content:space-between; font-size:13px;'><span>" . htmlspecialchars($r['label']) . "</span><strong>" . (int)$r['n'] . "</strong></div>";
        echo "<div style='background:#eee; border-radius:6px; height:14px;'><div style='width:{$w}%; background:{$color}; height:14px; border-radius:6px;'></div></div>";
        echo "</div>";
    }
}

function tile($value, $label, $color) {
    echo "<div style='flex:1; min-width:140px; background:white; border-radius:8px; border-top:4px solid {$color}; padding:14px; box-shadow:0 2px 5px rgba(0,0,0,0.05);'>";
    echo "<div style='font-size:28px; font-weight:bold; color:{$color};'>" . htmlspecialchars((string)$value) . "</div>";
    echo "<div style='font-size:13px; color:#555;'>" . htmlspecialchars($label) . "</div>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mentor Analytics</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Student Wellness Platform</h1>
    </header>

    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="mentor_dashboard.php">Mentor Dashboard</a></li>
            <li><a href="mentor_analytics.php">Analytics</a></li>
            <li><a href="mentor_logout.php">Log Out</a></li>
        </ul>
    </nav>

    <main style="max-width: 950px;">
        <h2>Support Analytics: <?php echo htmlspecialchars($faculty); ?> faculty</h2>
        <p style="color:#555;">Only mentors can see this page. It shows requests from your own faculty only, with no student names.</p>

        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:25px;">
            <?php
            tile($t['total'], 'Total requests', '#b71c2b');
            tile($t['pending'], 'Pending', '#f7941d');
            tile($t['resolved'] . ' (' . $resolved_pct . '%)', 'Resolved', '#2e7d32');
            tile($t['high_pending'], 'High urgency, still pending', '#b71c2b');
            tile($t['anonymous'], 'Anonymous requests', '#6c757d');
            ?>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <div class="card">
                <h3>Requests by category</h3>
                <?php bar_chart($by_category, '#b71c2b'); ?>
            </div>
            <div class="card">
                <h3>Requests by urgency</h3>
                <?php bar_chart($by_urgency, '#f7941d', ['high' => '#b71c2b', 'medium' => '#f7941d', 'low' => '#6c757d']); ?>
            </div>
        </div>

        <div class="card">
            <h3>Requests per day (last 14 days)</h3>
            <div style="display:flex; align-items:flex-end; gap:6px; height:130px;">
                <?php foreach ($days as $d => $n) {
                    $h = $day_max > 0 ? round($n / $day_max * 100) : 0; ?>
                    <div style="flex:1; text-align:center; font-size:11px; color:#555;" title="<?php echo $d . ': ' . $n; ?>">
                        <div style="font-weight:bold;"><?php echo $n > 0 ? $n : ''; ?></div>
                        <div style="background:#f7941d; height:<?php echo $h; ?>px; min-height:2px; border-radius:4px 4px 0 0;"></div>
                        <div><?php echo substr($d, 8, 2); ?></div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card">
            <h3>Student satisfaction (all feedback)</h3>
            <p>Average rating: <strong><?php echo $avg_text; ?></strong> from <?php echo (int)$fb['n']; ?> responses</p>
            <?php bar_chart($rating_rows, '#2e7d32'); ?>
        </div>
    </main>
</body>
</html>