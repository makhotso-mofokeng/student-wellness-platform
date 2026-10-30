<?php
include 'connect.php';
include 'classifier.php';

$errors = [];
$success = false;
$reply = null;

// Crisis safety layer: simple rules, independent of the classifier
function detect_crisis($text) {
    $phrases = ['kill myself', 'end my life', 'suicide', 'suicidal', 'want to die', 'hurt myself', 'harm myself', 'self harm', 'no reason to live', 'better off dead', 'end it all'];
    $text = strtolower($text);
    foreach ($phrases as $p) {
        if (strpos($text, $p) !== false) {
            return true;
        }
    }
    return false;
}

// Urgent-wording safety rule: raises urgency to high, whatever the model says
function detect_urgent_cues($text) {
    $cues = ['urgent', 'urgently', 'emergency', 'immediately', 'immediate', 'unsafe', 'threatened', 'threatening', 'severe', 'chest pain', 'difficulty breathing', 'cannot breathe', 'bleeding'];
    $text = strtolower($text);
    foreach ($cues as $c) {
        if (strpos($text, $c) !== false) {
            return true;
        }
    }
    return false;
}

// Which resource categories to suggest for each predicted category
$category_map = [
    'academic'  => ['Academic'],
    'stress'    => ['Stress', 'Sleep', 'Counselling', 'Mental Health'],
    'financial' => ['Financial'],
    'health'    => ['Health'],
    'social'    => ['Peer Support', 'Counselling'],
    'unsure'    => ['Counselling', 'Peer Support']
];

// If the model is less confident than this, it does not guess a category
$confidence_threshold = 0.5;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $is_anonymous = isset($_POST['anonymous']);
    $message = trim($_POST['message']);
    $faculty = $_POST['faculty'];
    $date = date("Y-m-d");
    $student_id = null;

    $allowed_faculties = ["NAS", "EMS", "HUM", "EDU"];
    if (!in_array($faculty, $allowed_faculties)) {
        $errors[] = "Please select a valid faculty.";
    }

    if (empty($message)) {
        $errors[] = "Please enter a message.";
    }

    if (!$is_anonymous) {
        $student_id_number = trim($_POST['student_id_number'] ?? '');
        if (!preg_match("/^[0-9]{9}$/", $student_id_number)) {
            $errors[] = "Please enter your 9-digit student number, or check 'Submit anonymously'.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM students WHERE student_id_number = ?");
            $stmt->bind_param("s", $student_id_number);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $student_id = $row['id'];
            } else {
                $errors[] = "No student found with that student number. Please register first, or submit anonymously.";
            }
        }
    }

    if (empty($errors)) {
        // Classify the message with our own trained model
        $cat_result = predict(train_model($conn, 'category'), $message);
        $urg_result = predict(train_model($conn, 'urgency'), $message);
        $category = $cat_result['label'];
        $urgency = $urg_result['label'];

        // Confidence threshold: do not guess when the model is unsure
        if ($cat_result['confidence'] < $confidence_threshold) {
            $category = 'unsure';
        }

        // Safety layers override the model
        $crisis = detect_crisis($message);
        if ($crisis || detect_urgent_cues($message)) {
            $urgency = 'high';
        }

        $stmt2 = $conn->prepare("INSERT INTO support_requests (student_id, faculty, message, category, urgency, status, date_submitted) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt2->bind_param("isssss", $student_id, $faculty, $message, $category, $urgency, $date);
        $stmt2->execute();
        $success = true;

        // Find resources that match the category
        $resources = [];
        $cats = $category_map[$category] ?? [];
        if (!empty($cats)) {
            $placeholders = implode(',', array_fill(0, count($cats), '?'));
            $stmt3 = $conn->prepare("SELECT title, description, link FROM wellness_resources WHERE category IN ($placeholders)");
            $stmt3->bind_param(str_repeat('s', count($cats)), ...$cats);
            $stmt3->execute();
            $res = $stmt3->get_result();
            while ($r = $res->fetch_assoc()) {
                $resources[] = $r;
            }
        }

        $reply = [
            'category' => $category,
            'urgency' => $urgency,
            'crisis' => $crisis,
            'resources' => $resources,
            'anonymous' => $is_anonymous
        ];
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
            <li><a href="mentor_login.php">Mentor Login</a></li>
        </ul>
    </nav>

    <main>
        <h2>Submit a Support Request</h2>
        <p>Only mentors from your faculty can see these requests. Anonymous submissions are never linked to your identity.</p>

        <?php if (!empty($errors)) { ?>
            <div style="color: red; font-weight: bold;">
                <?php foreach ($errors as $error) { echo "<p>" . htmlspecialchars($error) . "</p>"; } ?>
            </div>
        <?php } ?>

        <?php if ($success && $reply) { ?>
            <p style="color: green; font-weight: bold;">Support request submitted successfully!</p>

                        <?php if ($reply['crisis']) { ?>
                <div class="card" style="border-left-color: #b71c2b; background: #fff5f5;">
                    <h3 style="color: #b71c2b;">If you need help right now</h3>
                    <p><strong>SADAG Suicide Crisis Helpline (24 hours):</strong> 0800 567 567</p>
                    <p><strong>SADAG Counselling Line (8am-8pm):</strong> 011 234 4837</p>
                    <p><strong>Higher Health (for students):</strong> 0800 36 36 36</p>
                    <p>To book campus support, email <a href="mailto:health.well@spu.ac.za">health.well@spu.ac.za</a>.</p>
                </div>
            <?php } elseif ($reply['urgency'] === 'high') { ?>
                <div class="card" style="border-left-color: #f7941d; background: #fff8ee;">
                    <h3 style="color: #b71c2b;">We have marked your request as high priority</h3>
                    <p>A mentor will see it first. If you need help sooner, email <a href="mailto:health.well@spu.ac.za">health.well@spu.ac.za</a> or call Higher Health (for students) on 0800 36 36 36.</p>
                </div>
            <?php } ?>

            <div class="card">
                <h3>Suggested next steps</h3>

                <?php if ($reply['category'] === 'unsure') { ?>
                    <p>We could not tell exactly what this is about, so a mentor will review it
                       (priority: <?php echo htmlspecialchars($reply['urgency']); ?>). Here are some general support options:</p>
                <?php } else { ?>
                    <p>Your message looks like it is about: <strong><?php echo htmlspecialchars($reply['category']); ?></strong>
                       (priority: <?php echo htmlspecialchars($reply['urgency']); ?>).</p>
                <?php } ?>

                <?php if (!empty($reply['resources'])) { ?>
                    <p>These resources may help:</p>
                    <?php foreach ($reply['resources'] as $r) { ?>
                        <p><strong><?php echo htmlspecialchars($r['title']); ?></strong><br>
                           <?php echo htmlspecialchars($r['description']); ?><br>
                           <a href="<?php echo htmlspecialchars($r['link']); ?>"><?php echo htmlspecialchars($r['link']); ?></a></p>
                    <?php } ?>
                <?php } ?>

                <p style="font-size: 13px; color: #666;">This is an automated suggestion, not professional advice.
                <?php if ($reply['anonymous']) { ?>
                    Because you submitted anonymously, a mentor can read your message but cannot reply to you personally.
                <?php } else { ?>
                    A mentor from your faculty will review your request.
                <?php } ?>
                </p>
            </div>
        <?php } ?>

        <form method="POST" action="support_request.php">
            <label>Your Student Number (leave blank if submitting anonymously):</label><br>
            <input type="text" name="student_id_number" maxlength="9"><br><br>

            <label><input type="checkbox" name="anonymous"> Submit anonymously</label>
            <p style="font-size: 13px; color: #666; margin-top: -8px;">Anonymous submissions cannot receive a personal reply. If you'd like a mentor to follow up with you directly, don't select this option.</p>
            <br>

            <label>Faculty:</label><br>
            <select name="faculty" required>
                <option value="">-- Select Faculty --</option>
                <option value="NAS">NAS - Natural and Applied Sciences</option>
                <option value="EMS">EMS - Economic and Management Sciences</option>
                <option value="HUM">HUM - Humanities</option>
                <option value="EDU">EDU - Education</option>
            </select><br><br>

            <label>Message:</label><br>
            <textarea name="message" rows="5" cols="40" required></textarea><br><br>

            <input type="submit" value="Submit Request">
        </form>
    </main>
</body>
</html>