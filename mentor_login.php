<?php
session_start();
include 'connect.php';
$errors = [];

$max_attempts = 5;
$lock_minutes = 15;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $faculty = $_POST['faculty'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password_hash, failed_attempts, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked, TIMESTAMPDIFF(MINUTE, NOW(), locked_until) AS mins_left FROM mentors WHERE email = ? AND faculty = ?");
    $stmt->bind_param("ss", $email, $faculty);
    $stmt->execute();
    $mentor = $stmt->get_result()->fetch_assoc();

    if ($mentor && $mentor['is_locked']) {
        $errors[] = "Too many failed attempts. Please try again in " . ((int)$mentor['mins_left'] + 1) . " minute(s).";
    } elseif ($mentor && !empty($mentor['password_hash']) && password_verify($password, $mentor['password_hash'])) {
        // Success: reset the counter and start a fresh session
        $reset = $conn->prepare("UPDATE mentors SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
        $reset->bind_param("i", $mentor['id']);
        $reset->execute();

        session_regenerate_id(true);
        $_SESSION['mentor_id'] = $mentor['id'];
        $_SESSION['mentor_name'] = $mentor['name'];
        $_SESSION['mentor_faculty'] = $faculty;
        header("Location: mentor_dashboard.php");
        exit();
    } else {
        // Wrong details: count the failure if the mentor exists
        if ($mentor) {
            $attempts = $mentor['failed_attempts'] + 1;
            if ($attempts >= $max_attempts) {
                $lock = $conn->prepare("UPDATE mentors SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL $lock_minutes MINUTE) WHERE id = ?");
                $lock->bind_param("i", $mentor['id']);
                $lock->execute();
            } else {
                $count = $conn->prepare("UPDATE mentors SET failed_attempts = ? WHERE id = ?");
                $count->bind_param("ii", $attempts, $mentor['id']);
                $count->execute();
            }
        }
        // Same message either way, so it does not reveal which part was wrong
        $errors[] = "Incorrect email, faculty or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mentor Login</title>
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
            <li><a href="mentor_login.php">Mentor Login</a></li>
        </ul>
    </nav>

    <main>
        <h2>Mentor Login</h2>
        <p>Enter your mentor email, faculty and password to view support requests from your faculty.</p>

        <?php if (!empty($errors)) { ?>
            <div style="color: red; font-weight: bold;">
                <?php foreach ($errors as $error) { echo "<p>" . htmlspecialchars($error) . "</p>"; } ?>
            </div>
        <?php } ?>

        <form method="POST" action="mentor_login.php">
            <label>Email:</label><br>
            <input type="email" name="email" required><br><br>

            <label>Faculty:</label><br>
            <select name="faculty" required>
                <option value="">-- Select Faculty --</option>
                <option value="NAS">NAS - Natural and Applied Sciences</option>
                <option value="EMS">EMS - Economic and Management Sciences</option>
                <option value="HUM">HUM - Humanities</option>
                <option value="EDU">EDU - Education</option>
            </select><br><br>

            <label>Password:</label><br>
            <input type="password" name="password" required><br><br>

            <input type="submit" value="Log In">
        </form>
    </main>
</body>
</html>
