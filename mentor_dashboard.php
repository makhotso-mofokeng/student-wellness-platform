<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['mentor_id'])) {
    header("Location: mentor_login.php");
    exit();
}

$faculty = $_SESSION['mentor_faculty'];
$mentor_name = $_SESSION['mentor_name'];

// Handle marking a request as resolved
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $stmt = $conn->prepare("UPDATE support_requests SET status = 'resolved' WHERE id = ? AND faculty = ?");
    $stmt->bind_param("is", $request_id, $faculty);
    $stmt->execute();
}

// Get counts
$stmt = $conn->prepare("SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending FROM support_requests WHERE faculty = ?");
$stmt->bind_param("s", $faculty);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Get requests, pending first
$stmt2 = $conn->prepare("
    SELECT sr.id, sr.message, sr.status, sr.date_submitted, sr.category, sr.urgency, s.name
    FROM support_requests sr
    LEFT JOIN students s ON sr.student_id = s.id
    WHERE sr.faculty = ?
    ORDER BY (sr.status = 'pending') DESC, FIELD(sr.urgency, 'low', 'medium', 'high') DESC, sr.date_submitted DESC
");
$stmt2->bind_param("s", $faculty);
$stmt2->execute();
$requests = $stmt2->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mentor Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Student Wellness Platform</h1>
    </header>

    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="register.php">Register</a></li>
            <li><a href="resources.php">Wellness Resources</a></li>
            <li><a href="mentors.php">Peer Mentors</a></li>
            <li><a href="support_request.php">Support Request</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="mentor_dashboard.php">Mentor Dashboard</a></li>
        </ul>
    </nav>

    <main>
                <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Welcome, <?php echo htmlspecialchars($mentor_name); ?> (<?php echo htmlspecialchars($faculty); ?>)</h2>
            <a href="mentor_logout.php" style="color: #b71c2b; font-weight: bold;">Log Out</a>
        </div>
        <p><?php echo $counts['total']; ?> total requests — <?php echo $counts['pending']; ?> pending</p>   
             <p><a href="mentor_analytics.php" style="font-weight: bold; color: #b71c2b;">View analytics for <?php echo htmlspecialchars($faculty); ?> →</a></p>


        <?php while ($row = $requests->fetch_assoc()) { ?>
            <div class="card">
                                <p><strong><?php echo $row['name'] ? htmlspecialchars($row['name']) : "Anonymous"; ?></strong>
                — <?php echo htmlspecialchars($row['status']); ?>
                — <?php echo htmlspecialchars($row['date_submitted']); ?></p>
                <p>
                    <span style="background: <?php echo $row['urgency'] === 'high' ? '#b71c2b' : ($row['urgency'] === 'medium' ? '#f7941d' : '#6c757d'); ?>; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                        <?php echo strtoupper(htmlspecialchars($row['urgency'] ?: 'unrated')); ?> URGENCY
                    </span>
                    <span style="background: #eee; padding: 3px 10px; border-radius: 12px; font-size: 12px;">
                        <?php echo htmlspecialchars($row['category'] ?: 'unclassified'); ?>
                    </span>
                </p>
                <p><?php echo htmlspecialchars($row['message']); ?></p>

                <?php if ($row['status'] === 'pending') { ?>
                    <form method="POST" action="mentor_dashboard.php">
                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                        <input type="submit" value="Mark as Resolved">
                    </form>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</body>
</html>