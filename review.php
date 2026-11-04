<?php
session_start();
include "db.php";

if (!isset($_GET['score_id'])) { header("Location: dashboard.php"); exit(); }

$score_id = $_GET['score_id'];
$currentUser = $_SESSION['user'];

//fetch the quiz attempt info
$stmt = $conn->prepare("SELECT qs.*, qc.title FROM quiz_scores qs JOIN quiz_categories qc ON qs.category_id = qc.id WHERE qs.id = ? AND qs.username = ?");
$stmt->bind_param("is", $score_id, $currentUser);
$stmt->execute();
$quizInfo = $stmt->get_result()->fetch_assoc();

if (!$quizInfo) { die("Error: Quiz attempt not found in database."); }

//fetch the specific questions and answers for THIS attempt
$query = "SELECT 
            q.question, 
            q.option_a, q.option_b, q.option_c, q.option_d, 
            q.correct_answer, 
            ua.user_choice 
          FROM user_answers ua
          INNER JOIN questions q ON ua.question_id = q.id 
          WHERE ua.score_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $score_id);
$stmt->execute();
$results = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Results</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --success: #2b8a3e; --error: #c92a2a; --secondary: #7048e8; }
        body { font-family: 'Outfit', sans-serif; background: #f0f2f5; padding: 2rem; }
        .container { max-width: 850px; margin: 0 auto; background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .q-card { padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #ddd; }
        .wrong-card { border-left: 8px solid var(--error); background: #fff5f5; }
        .correct-card { border-left: 8px solid var(--success); background: #f4fff4; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 0.8rem; margin-bottom: 10px; }
        .badge-wrong { background: #ffe3e3; color: var(--error); }
        .badge-correct { background: #d3f9d8; color: var(--success); }
        .ans-detail { display: flex; gap: 30px; margin-top: 10px; font-size: 0.95rem; }
        .label { display: block; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; color: #888; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" style="text-decoration:none; color:var(--secondary); font-weight:600;">← Back to Dashboard</a>
    <h1 style="margin-top:10px;"><?php echo htmlspecialchars($quizInfo['title']); ?> Review</h1>
    <p>Final Score: <strong><?php echo $quizInfo['score']; ?> / <?php echo $quizInfo['total_items']; ?></strong></p>
    <hr style="border:0; border-top:1px solid #eee; margin: 2rem 0;">

    <?php 
    if ($results->num_rows == 0): 
        echo "<div style='text-align:center; padding:40px; color:#666;'>
                <p>⚠️ No detailed answer data found for this attempt.</p>
                <p> Please try taking a new quiz!</p>
              </div>";
    else:
        $num = 1;
        while($row = $results->fetch_assoc()): 
            $isCorrect = ($row['user_choice'] == $row['correct_answer']);
            $user_text = $row['option_' . $row['user_choice']] ?? "No Answer";
            $correct_text = $row['option_' . $row['correct_answer']];
    ?>
        <div class="q-card <?php echo $isCorrect ? 'correct-card' : 'wrong-card'; ?>">
            <span class="badge <?php echo $isCorrect ? 'badge-correct' : 'badge-wrong'; ?>">
                QUESTION #<?php echo $num++; ?> — <?php echo $isCorrect ? 'CORRECT' : 'WRONG'; ?>
            </span>
            
            <p style="font-size: 1.1rem; margin: 0 0 10px 0;"><strong><?php echo htmlspecialchars($row['question']); ?></strong></p>
            
            <div class="ans-detail">
                <div>
                    <span class="label">Your Selection</span>
                    <span style="color:<?php echo $isCorrect ? 'var(--success)' : 'var(--error)'; ?>; font-weight:600;">
                        (<?php echo strtoupper($row['user_choice']); ?>) <?php echo htmlspecialchars($user_text); ?>
                    </span>
                </div>
                
                <?php if(!$isCorrect): ?>
                <div>
                    <span class="label">Correct Answer</span>
                    <span style="color:var(--success); font-weight:600;">
                        (<?php echo strtoupper($row['correct_answer']); ?>) <?php echo htmlspecialchars($correct_text); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php 
        endwhile; 
    endif;
    ?>
</div>

</body>
</html>