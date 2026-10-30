<?php
include 'connect.php';

$result = $conn->query("SELECT * FROM mentors");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Peer Mentors</title>
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
        <h2>Peer Mentors</h2>

        <?php while ($row = $result->fetch_assoc()) { ?>
            <div class="card">
                <h3><?php echo $row['name']; ?></h3>
                <p><strong>Faculty:</strong> <?php echo $row['faculty']; ?></p>
                <p><?php echo $row['bio']; ?></p>
                <p><strong>Email:</strong> <?php echo $row['email']; ?></p>
            </div>
        <?php } ?>
    </main>
</body>
</html>