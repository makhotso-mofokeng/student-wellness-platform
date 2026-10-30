<?php
// classifier.php - Naive Bayes text classifier.
// It learns from the labelled messages in the training_messages table.

// Very simple stemmer: strips common endings so "afford" and "affording" match
function stem_word($w) {
    if (strlen($w) > 5 && substr($w, -3) === 'ing') return substr($w, 0, -3);
    if (strlen($w) > 4 && substr($w, -2) === 'ed') return substr($w, 0, -2);
    if (strlen($w) > 3 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') return substr($w, 0, -1);
    return $w;
}

// Step A: break a message into clean words
function tokenize($text, $use_stemming = true) {
    $stopwords = ['i','am','is','are','was','a','an','the','to','of','and','in','on','it','my','me','that','this','for','with','but','so','at','be','have','has','had','do'];

    $text = strtolower($text);
    $text = preg_replace('/[^a-z\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $result = [];
    foreach ($words as $word) {
        if (!in_array($word, $stopwords) && strlen($word) > 1) {
            $result[] = $use_stemming ? stem_word($word) : $word;
        }
    }
    return $result;
}

// Step B: learn - count how often each word appears in each class
function train_model($conn, $label_column) {
    if (!in_array($label_column, ['category', 'urgency'])) {
        die("Invalid label column");
    }

    $result = $conn->query("SELECT message, $label_column AS label FROM training_messages WHERE split = 'train'");

    $model = ['class_counts' => [], 'word_counts' => [], 'class_totals' => [], 'vocab' => [], 'total_docs' => 0];

    while ($row = $result->fetch_assoc()) {
        $label = $row['label'];
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

// Step C: predict - each word "votes" for the classes it appeared in
function predict($model, $text) {
    if ($model['total_docs'] == 0) {
        return ['label' => 'unknown', 'confidence' => 0, 'probabilities' => []];
    }

    $words = tokenize($text);
    $vocab_size = count($model['vocab']);
    $scores = [];

    foreach ($model['class_counts'] as $label => $count) {
        $score = log($count / $model['total_docs']);
        foreach ($words as $word) {
            if (!isset($model['vocab'][$word])) {
                continue; // ignore words never seen in training
            }
            $word_count = $model['word_counts'][$label][$word] ?? 0;
            $score += log(($word_count + 1) / ($model['class_totals'][$label] + $vocab_size));
        }
        $scores[$label] = $score;
    }

    // turn the scores into percentages
    $max = max($scores);
    $sum = 0;
    $probs = [];
    foreach ($scores as $label => $s) {
        $probs[$label] = exp($s - $max);
        $sum += $probs[$label];
    }
    foreach ($probs as $label => $p) {
        $probs[$label] = $p / $sum;
    }
    arsort($probs);
    $best = array_key_first($probs);

    return ['label' => $best, 'confidence' => $probs[$best], 'probabilities' => $probs];
}
?>