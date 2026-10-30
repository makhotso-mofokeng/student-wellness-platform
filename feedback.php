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

               $rating = $_POST['rating'];
        $stmt2 = $conn->prepare("INSERT INTO feedback (student_id, message, rating, date_submitted) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isis", $student_id, $message, $rating, $date);
        $stmt2->execute();

        echo "<p style='color: green; font-weight: bold;'>Feedback submitted successfully!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>No student found with that email. Please register first.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Feedback</title>
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
        <h2>Submit Feedback</h2>

        <form method="POST" action="feedback.php">
            <label>Your Email:</label><br>
            <input type="email" name="email" required><br><br>

                   <label>Message:</label><br>
        <textarea name="message" rows="5" cols="40" required></textarea><br><br>

        <label>How would you rate your experience using this platform?</label><br>
        <select name="rating" required>
            <option value="">-- Select a Rating --</option>
            <option value="5">5 - Excellent</option>
            <option value="4">4 - Good</option>
            <option value="3">3 - Average</option>
            <option value="2">2 - Poor</option>
            <option value="1">1 - Very Poor</option>
        </select><br><br>

        <input type="submit" value="Submit Feedback">

            <input type="submit" value="Submit Feedback">
        </form>
    </main>
</body>
</html>