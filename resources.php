<?php
include 'connect.php';

$result = $conn->query("SELECT * FROM wellness_resources");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Wellness Resources</title>
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
        <h2>Wellness Resources</h2>

        <?php while ($row = $result->fetch_assoc()) { ?>
            <div class="card">
                <h3><?php echo $row['title']; ?></h3>
                <p><?php echo $row['description']; ?></p>
                <p><strong>Category:</strong> <?php echo $row['category']; ?></p>
                <a href="<?php echo $row['link']; ?>"><?php echo $row['link']; ?></a>
            </div>
        <?php } ?>
    </main>
</body>
</html>