<?php
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $faculty = $_POST['faculty'];
    $date = date("Y-m-d");

    $stmt = $conn->prepare("INSERT INTO students (name, email, faculty, date_registered) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $faculty, $date);
    $stmt->execute();

    echo "<p style='color: green; font-weight: bold;'>Registration successful!</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
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
        <h2>Student Registration</h2>

        <form method="POST" action="register.php">
            <label>Name:</label><br>
            <input type="text" name="name" required><br><br>

            <label>Email:</label><br>
            <input type="email" name="email" required><br><br>

            <label>Faculty:</label><br>
            <input type="text" name="faculty" required><br><br>

            <input type="submit" value="Register">
        </form>
    </main>
</body>
</html>