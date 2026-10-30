<?php
include 'connect.php';
include 'classifier.php';

// ---------- helper functions ----------
function train_from_rows($rows, $key) {
    $model = ['class_counts' => [], 'word_counts' => [], 'class_totals' => [], 'vocab' => [], 'total_docs' => 0];
    foreach ($rows as $row) {
        $label = $row[$key];
        $model['total_docs']++;
        if (!isset($model['class_counts'][$label])) {
            $model['class_counts'][$label] = 0;
            $model['word_counts'][$label] = [];
            $model['class_totals'][$label] = 0;
        }
        $model['class_counts'][$label]++;
        foreach (tokenize($row['message']) as $word) {
            if (!isset($model['word_counts'][$label][$word])) {
                $model['word_counts'][$label][$word] = 0;
            }
            $model['word_counts'][$label][$word]++;
            $model['class_totals'][$label]++;
            $model['vocab'][$word] = true;
        }
    }
    return $model;
}

function majority_label($rows, $key) {
    $counts = [];
    foreach ($rows as $r) {
        $counts[$r[$key]] = ($counts[$r[$key]] ?? 0) + 1;
    }
    arsort($counts);
    return array_key_first($counts);
}

// Baseline: hand-written keyword rules (no learning)
function keyword_baseline($text) {
    $keywords = [
        'academic'  => ['exam', 'exams', 'test', 'module', 'assignment', 'assignments', 'study', 'studying', 'marks', 'lecturer', 'lectures', 'course', 'deadline', 'deadlines', 'class', 'classes', 'project'],
        'stress'    => ['anxious', 'anxiety', 'panic', 'overwhelmed', 'sleep', 'slept', 'stress', 'stressed', 'worrying', 'worried', 'cry', 'burnt', 'exhausted', 'pressure', 'nervous', 'relax'],
        'financial' => ['money', 'fees', 'afford', 'bursaries', 'bursary', 'nsfas', 'pay', 'rent', 'funding', 'aid', 'airtime', 'data', 'transport', 'food', 'owe', 'textbooks'],
        'health'    => ['fever', 'cough', 'doctor', 'nurse', 'headache', 'headaches', 'dizzy', 'sick', 'pain', 'pains', 'painful', 'rash', 'injured', 'ankle', 'vaccination', 'medication', 'stomach', 'check'],
        'social'    => ['lonely', 'friends', 'friend', 'homesick', 'bullying', 'roommates', 'excluded', 'society', 'family', 'unsafe', 'isolated', 'following']
    ];
        $words = tokenize($text, false);
    $best = 'unknown';
    $best_score = 0;
    foreach ($keywords as $label => $list) {
        $score = 0;
        foreach ($words as $w) {
            if (in_array($w, $list)) {
                $score++;
            }
        }
        if ($score > $best_score) {
            $best_score = $score;
            $best = $label;
        }
    }
    return $best;
}

function accuracy($pairs) {
    if (count($pairs) == 0) {
        return 0;
    }
    $ok = 0;
    foreach ($pairs as $p) {
        if ($p[0] === $p[1]) {
            $ok++;
        }
    }
    return $ok / count($pairs);
}

function pct($x) {
    return round($x * 100) . "%";
}

// ---------- load data ----------
$all = [];
$res = $conn->query("SELECT message, category, urgency, split FROM training_messages");
while ($r = $res->fetch_assoc()) {
    $all[] = $r;
}
$train = array_values(array_filter($all, function ($r) { return $r['split'] === 'train'; }));
$test  = array_values(array_filter($all, function ($r) { return $r['split'] === 'test'; }));

// ---------- 1) hold-out evaluation (train split -> test split) ----------
$cat_model = train_from_rows($train, 'category');
$urg_model = train_from_rows($train, 'urgency');
$maj_cat = majority_label($train, 'category');
$maj_urg = majority_label($train, 'urgency');

$h = ['nb_cat' => [], 'nb_urg' => [], 'kw_cat' => [], 'maj_cat' => [], 'maj_urg' => []];
foreach ($test as $row) {
    $h['nb_cat'][]  = [$row['category'], predict($cat_model, $row['message'])['label']];
    $h['nb_urg'][]  = [$row['urgency'],  predict($urg_model, $row['message'])['label']];
    $h['kw_cat'][]  = [$row['category'], keyword_baseline($row['message'])];
    $h['maj_cat'][] = [$row['category'], $maj_cat];
    $h['maj_urg'][] = [$row['urgency'],  $maj_urg];
}

// ---------- 2) leave-one-out cross-validation (all rows) ----------
$l = ['nb_cat' => [], 'nb_urg' => [], 'kw_cat' => [], 'maj_cat' => [], 'maj_urg' => []];
$cats = ['academic', 'stress', 'financial', 'health', 'social'];
$confusion = [];
foreach ($cats as $a) {
    foreach ($cats as $p) {
        $confusion[$a][$p] = 0;
    }
}
$errors = [];

foreach ($all as $i => $row) {
    $rest = $all;
    unset($rest[$i]);
    $rest = array_values($rest);

    $cm = train_from_rows($rest, 'category');
    $um = train_from_rows($rest, 'urgency');
    $pc = predict($cm, $row['message'])['label'];
    $pu = predict($um, $row['message'])['label'];

    $l['nb_cat'][]  = [$row['category'], $pc];
    $l['nb_urg'][]  = [$row['urgency'],  $pu];
    $l['kw_cat'][]  = [$row['category'], keyword_baseline($row['message'])];
    $l['maj_cat'][] = [$row['category'], majority_label($rest, 'category')];
    $l['maj_urg'][] = [$row['urgency'],  majority_label($rest, 'urgency')];

    if (isset($confusion[$row['category']][$pc])) {
        $confusion[$row['category']][$pc]++;
    }
    if ($pc !== $row['category'] || $pu !== $row['urgency']) {
        $errors[] = ['message' => $row['message'], 'cat' => $row['category'], 'pred_cat' => $pc, 'urg' => $row['urgency'], 'pred_urg' => $pu];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Classifier Evaluation</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table.eval { border-collapse: collapse; background: white; margin-bottom: 25px; }
        table.eval th, table.eval td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; font-size: 14px; }
        table.eval th { background: #b71c2b; color: white; }
    </style>
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

    <main style="max-width: 1000px;">
        <h2>Classifier Evaluation</h2>
        <p>Dataset: <strong><?php echo count($all); ?></strong> labelled messages
           (<?php echo count($train); ?> train, <?php echo count($test); ?> test).</p>

        <h3>Accuracy comparison</h3>
        <table class="eval">
            <tr><th>Evaluation</th><th>Method</th><th>Category accuracy</th><th>Urgency accuracy</th></tr>
            <tr><td rowspan="3">Hold-out test (n=<?php echo count($test); ?>)</td>
                <td>Naive Bayes (trained)</td><td><?php echo pct(accuracy($h['nb_cat'])); ?></td><td><?php echo pct(accuracy($h['nb_urg'])); ?></td></tr>
            <tr><td>Keyword rules baseline</td><td><?php echo pct(accuracy($h['kw_cat'])); ?></td><td>n/a</td></tr>
            <tr><td>Majority-class baseline</td><td><?php echo pct(accuracy($h['maj_cat'])); ?></td><td><?php echo pct(accuracy($h['maj_urg'])); ?></td></tr>
            <tr><td rowspan="3">Leave-one-out (n=<?php echo count($all); ?>)</td>
                <td>Naive Bayes (trained)</td><td><?php echo pct(accuracy($l['nb_cat'])); ?></td><td><?php echo pct(accuracy($l['nb_urg'])); ?></td></tr>
            <tr><td>Keyword rules baseline</td><td><?php echo pct(accuracy($l['kw_cat'])); ?></td><td>n/a</td></tr>
            <tr><td>Majority-class baseline</td><td><?php echo pct(accuracy($l['maj_cat'])); ?></td><td><?php echo pct(accuracy($l['maj_urg'])); ?></td></tr>
        </table>

        <h3>Confusion matrix (category, leave-one-out)</h3>
        <p>Rows = true category, columns = predicted category.</p>
        <table class="eval">
            <tr><th></th><?php foreach ($cats as $p) { echo "<th>$p</th>"; } ?></tr>
            <?php foreach ($cats as $a) { ?>
                <tr><th><?php echo $a; ?></th>
                <?php foreach ($cats as $p) { ?>
                    <td style="<?php echo $a === $p ? 'background:#e8f5e9; font-weight:bold;' : ''; ?>"><?php echo $confusion[$a][$p]; ?></td>
                <?php } ?>
                </tr>
            <?php } ?>
        </table>

        <h3>Messages the model got wrong (leave-one-out)</h3>
        <table class="eval">
            <tr><th>Message</th><th>True category</th><th>Predicted</th><th>True urgency</th><th>Predicted</th></tr>
            <?php foreach ($errors as $e) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['message']); ?></td>
                    <td><?php echo $e['cat']; ?></td>
                    <td style="<?php echo $e['cat'] !== $e['pred_cat'] ? 'color:#b71c2b; font-weight:bold;' : ''; ?>"><?php echo $e['pred_cat']; ?></td>
                    <td><?php echo $e['urg']; ?></td>
                    <td style="<?php echo $e['urg'] !== $e['pred_urg'] ? 'color:#b71c2b; font-weight:bold;' : ''; ?>"><?php echo $e['pred_urg']; ?></td>
                </tr>
            <?php } ?>
        </table>
    </main>
</body>
</html>