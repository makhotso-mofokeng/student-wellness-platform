<?php
include 'connect.php';
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $student_id_number = trim($_POST['student_id_number']);
    $faculty = $_POST['faculty'];
    $date = date("Y-m-d");

    // Email is derived directly from the student number
    $email = $student_id_number . "@spu.ac.za";

    // Validation checks
        if (!preg_match("/^[A-Za-z ]+$/", $name)) {
        $errors[] = "Name must contain letters only (no numbers or symbols).";
    } elseif (count(array_filter(explode(" ", $name))) < 2) {
        $errors[] = "Please enter both a name and a surname.";
    }

    if (!preg_match("/^[0-9]{9}$/", $student_id_number)) {
        $errors[] = "Student number must be exactly 9 digits.";
    }

    $allowed_faculties = ["NAS", "EMS", "HUM", "EDU"];
    if (!in_array($faculty, $allowed_faculties)) {
        $errors[] = "Please select a valid faculty.";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO students (name, student_id_number, email, faculty, date_registered) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $student_id_number, $email, $faculty, $date);
        $stmt->execute();

        echo "<p style='color: green; font-weight: bold;'>Registration successful! Your email on record is <strong>$email</strong>.</p>";
    }
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
            <li><a href="mentor_login.php">Mentor Login</a></li>
        </ul>
    </nav>

    <main>
        <h2>Student Registration (SPU Students Only)</h2>
        <p>Only current SPU students may register. Your email is automatically generated from your student number (e.g. <strong>123456789@spu.ac.za</strong>).</p>

        <?php if (!empty($errors)) { ?>
            <div style="color: red; font-weight: bold;">
                <?php foreach ($errors as $error) { echo "<p>$error</p>"; } ?>
            </div>
        <?php } ?>

        <form method="POST" action="register.php">
            <label>Full Name:</label><br>
            <input type="text" name="name" required><br><br>

            <label>SPU Student Number (9 digits):</label><br>
            <input type="text" name="student_id_number" maxlength="9" required><br><br>

            <label>Faculty:</label><br>
            <select name="faculty" required>
                <option value="">-- Select Faculty --</option>
                <option value="NAS">NAS - Natural and Applied Sciences</option>
                <option value="EMS">EMS - Economic and Management Sciences</option>
                <option value="HUM">HUM - Humanities</option>
                <option value="EDU">EDU - Education</option>
            </select><br><br>

            <input type="submit" value="Register">
        </form>
    </main>
</body>
</html>