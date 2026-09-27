<?php
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $message = $_POST['message'];
    $date = date("Y-m-d");

    $stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $student_id = $row['id'];

        $stmt2 = $conn->prepare("INSERT INTO support_requests (student_id, message, status, date_submitted) VALUES (?, ?, 'pending', ?)");
        $stmt2->bind_param("iss", $student_id, $message, $date);
        $stmt2->execute();

        echo "<p style='color: green; font-weight: bold;'>Support request submitted successfully!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>No student found with that email. Please register first.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Support Request</title>
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
        <h2>Submit a Support Request</h2>

        <form method="POST" action="support_request.php">
            <label>Your Email:</label><br>
            <input type="email" name="email" required><br><br>

            <label>Message:</label><br>
            <textarea name="message" rows="5" cols="40" required></textarea><br><br>

            <input type="submit" value="Submit Request">
        </form>
    </main>
</body>
</html>