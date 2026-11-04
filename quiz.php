<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) { header("Location: register.php"); exit(); }

$category_id = $_GET['category_id'] ?? null;
$username = $_SESSION['user'];
$error_message = "";
$error_icon = "🔒";

//quiz status and if already took the quiz
$check_stmt = $conn->prepare("
    SELECT qc.title, qc.status, 
    (SELECT COUNT(*) FROM quiz_scores WHERE username = ? AND category_id = ?) as attempts 
    FROM quiz_categories qc WHERE qc.id = ?
");
$check_stmt->bind_param("sii", $username, $category_id, $category_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result()->fetch_assoc();

if (!$check_result) {
    $error_message = "Quiz not found.";
    $error_icon = "❓";
} elseif ($check_result['status'] == 'Closed') {
    $error_message = "This quiz is currently closed by the Admin.";
    $error_icon = "🔒";
} elseif ($check_result['attempts'] > 0) {
    $error_message = "You have already completed <strong>" . htmlspecialchars($check_result['title']) . "</strong>.";
    $error_icon = "✅";
}

if ($error_message): 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied | EDUQuiz!</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #ff6b6b; --secondary: #7048e8; --text-dark: #2d3436; --bg-glass: rgba(255, 255, 255, 0.9); }
        body { margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Outfit', sans-serif; background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradient 15s ease infinite; }
        @keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .error-card { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 3rem; border-radius: 24px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-width: 450px; width: 90%; border: 1px solid rgba(255,255,255,0.4); }
        .icon { font-size: 4rem; margin-bottom: 1rem; display: block; }
        h2 { color: var(--text-dark); margin-bottom: 1rem; font-weight: 800; }
        p { color: #636e72; font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem; }
        .btn-back { display: inline-block; background: var(--secondary); color: white; padding: 12px 30px; border-radius: 12px; text-decoration: none; font-weight: 800; transition: 0.3s; }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="icon"><?php echo $error_icon; ?></span>
        <h2>Access Denied!</h2>
        <p><?php echo $error_message; ?></p>
        <a href="dashboard.php" class="btn-back">Return to Dashboard</a>
    </div>
</body>
</html>
<?php exit(); endif; ?>

<?php
//quiz info
$cat_stmt = $conn->prepare("SELECT title, time_limit FROM quiz_categories WHERE id = ?");
$cat_stmt->bind_param("i", $category_id);
$cat_stmt->execute();
$cat_data = $cat_stmt->get_result()->fetch_assoc();
$quiz_title = $cat_data['title'];
$time_limit_seconds = $cat_data['time_limit'] * 60;

$quizSubmitted = false;
$score = 0;
$totalQuestions = 0;
$new_score_id = 0;


if (isset($_POST['submit_quiz'])) {
    $quizSubmitted = true;
    
    $ans_stmt = $conn->prepare("SELECT id, correct_answer FROM questions WHERE category_id = ?");
    $ans_stmt->bind_param("i", $category_id);
    $ans_stmt->execute();
    $answersQuery = $ans_stmt->get_result();
    
    $correctAnswers = [];
    while ($row = $answersQuery->fetch_assoc()) {
        $correctAnswers[$row['id']] = $row['correct_answer'];
        $totalQuestions++;
    }

    if (isset($_POST['answers'])) {
        foreach ($_POST['answers'] as $question_id => $user_answer) {
            if (isset($correctAnswers[$question_id]) && $correctAnswers[$question_id] == $user_answer) {
                $score++;
            }
        }
    }


    $stmt = $conn->prepare("INSERT INTO quiz_scores (username, category_id, score, total_items) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siii", $username, $category_id, $score, $totalQuestions);
    $stmt->execute();
    
    $new_score_id = $conn->insert_id;


    if (isset($_POST['answers'])) {
        foreach ($_POST['answers'] as $question_id => $user_answer) {
            $save_stmt = $conn->prepare("INSERT INTO user_answers (score_id, question_id, user_choice) VALUES (?, ?, ?)");
            $save_stmt->bind_param("iis", $new_score_id, $question_id, $user_answer);
            $save_stmt->execute();
        }
    }
}

$questionsQuery = $conn->prepare("SELECT * FROM questions WHERE category_id = ? ORDER BY RAND()");
$questionsQuery->bind_param("i", $category_id);
$questionsQuery->execute();
$questionsResult = $questionsQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Quiz | <?php echo htmlspecialchars($quiz_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #ff6b6b; --secondary: #7048e8; --text-dark: #2d3436; --text-muted: #636e72; --bg-glass: rgba(255, 255, 255, 0.9); --border-glass: rgba(255, 255, 255, 0.4); }
        body { margin: 0; display: flex; min-height: 100vh; font-family: 'Outfit', sans-serif; background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradient 15s ease infinite; color: var(--text-dark); justify-content: center; align-items: center; padding: 2rem; }
        @keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .timer-bar { position: fixed; top: 20px; right: 20px; background: var(--bg-glass); backdrop-filter: blur(10px); padding: 15px 25px; border-radius: 16px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); border: 2px solid var(--secondary); z-index: 1000; display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--secondary); }
        .quiz-container { width: 100%; max-width: 800px; background: var(--bg-glass); backdrop-filter: blur(15px); border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--border-glass); padding: 2.5rem; max-height: 90vh; overflow-y: auto; margin-top: 50px; }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 2px solid rgba(0,0,0,0.05); padding-bottom: 1rem; }
        .header h1 { margin: 0; color: var(--secondary); font-weight: 800; }
        .question-card { background: rgba(255, 255, 255, 0.7); border: 1px solid var(--border-glass); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .question-text { font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; }
        .options-list { list-style: none; padding: 0; margin: 0; }
        .options-list label { display: flex; align-items: center; padding: 10px 15px; background: white; border: 1px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .options-list input[type="radio"] { margin-right: 10px; accent-color: var(--secondary); }
        .btn-submit { background: var(--secondary); color: white; border: none; padding: 12px 30px; border-radius: 12px; font-size: 1.1rem; font-weight: 800; font-family: 'Outfit', sans-serif; cursor: pointer; width: 100%; transition: all 0.3s; }
        .score-circle { width: 150px; height: 150px; border-radius: 50%; background: var(--secondary); color: white; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; margin: 0 auto 1.5rem auto; }
        

        .btn-review { display: block; background: #2b8a3e; color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-weight: 800; margin-top: 10px; }
        .btn-back { display: inline-block; margin-top: 1.5rem; text-decoration: none; color: var(--secondary); font-weight: 600; border: 2px solid var(--secondary); padding: 8px 20px; border-radius: 8px; }
    </style>
</head>
<body>

<?php if (!$quizSubmitted && $questionsResult->num_rows > 0): ?>
    <div class="timer-bar" id="timer-display">⏱️ Time: <span id="time-string">--:--</span></div>
<?php endif; ?>

<div class="quiz-container">
    <?php if ($quizSubmitted): ?>
        <div style="text-align:center;">
            <h1>Quiz Completed!</h1>
            <p>Well done, <?php echo htmlspecialchars($username); ?>!</p>
            <div class="score-circle"><?php echo $score; ?> / <?php echo $totalQuestions; ?></div>
            
            <a href="review.php?score_id=<?php echo $new_score_id; ?>" class="btn-review">🔍 Review Answers & Mistakes</a>
            <br>
            <a href="dashboard.php" class="btn-back">Return to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="header">
            <h1><?php echo htmlspecialchars($quiz_title); ?></h1>
        </div>

        <form id="quiz-form" method="POST" onsubmit="return confirm('Are you sure you want to submit your answers? You cannot change them once submitted.');">
            <?php 
            $qNumber = 1;
            while ($q = $questionsResult->fetch_assoc()): 
            ?>
                <div class="question-card">
                    <div class="question-text"><?php echo $qNumber++ . ". " . htmlspecialchars($q['question']); ?></div>
                    <ul class="options-list">
                        <li><label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="a" required> <?php echo htmlspecialchars($q['option_a']); ?></label></li>
                        <li><label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="b"> <?php echo htmlspecialchars($q['option_b']); ?></label></li>
                        <li><label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="c"> <?php echo htmlspecialchars($q['option_c']); ?></label></li>
                        <li><label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="d"> <?php echo htmlspecialchars($q['option_d']); ?></label></li>
                    </ul>
                </div>
            <?php endwhile; ?>
            <button type="submit" name="submit_quiz" class="btn-submit">Submit Answers</button>
        </form>
    <?php endif; ?>
</div>

<script>
    const timeLimitInSeconds = <?php echo $time_limit_seconds; ?>;
    let timeLeft = timeLimitInSeconds;
    const timerDisplay = document.getElementById('time-string');
    const quizForm = document.getElementById('quiz-form');

    if (quizForm && timerDisplay) {
        const countdown = setInterval(() => {
            let mins = Math.floor(timeLeft / 60);
            let secs = timeLeft % 60;
            timerDisplay.innerHTML = `${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                quizForm.submit();
            }
            timeLeft--;
        }, 1000);
    }
</script>
</body>
</html>