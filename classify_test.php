<?php
include 'connect.php';
include 'classifier.php';

$message = '';
$cat = null;
$urg = null;

$train_count = $conn->query("SELECT COUNT(*) AS n FROM training_messages WHERE split = 'train'")->fetch_assoc()['n'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $cat = predict(train_model($conn, 'category'), $message);
        $urg = predict(train_model($conn, 'urgency'), $message);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Classifier Test</title>
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
        </ul>
    </nav>

    <main>
        <h2>Classifier Test</h2>
        <p>Trained on <strong><?php echo $train_count; ?></strong> messages.</p>

        <form method="POST" action="classify_test.php">
            <label>Type a student message:</label><br>
            <textarea name="message" rows="4" cols="40" required><?php echo htmlspecialchars($message); ?></textarea><br><br>
            <input type="submit" value="Classify">
        </form>

        <?php if ($cat !== null) { ?>
            <div class="card">
                <h3>Result</h3>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($cat['label']); ?>
                   (<?php echo round($cat['confidence'] * 100); ?>% confident)</p>
                <p><strong>Urgency:</strong> <?php echo htmlspecialchars($urg['label']); ?>
                   (<?php echo round($urg['confidence'] * 100); ?>% confident)</p>

                <p><strong>All category scores:</strong></p>
                <?php foreach ($cat['probabilities'] as $label => $p) { ?>
                    <p><?php echo htmlspecialchars($label); ?>: <?php echo round($p * 100); ?>%</p>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</body>
</html>