<?php
session_start();
include "db.php";

// Admin Access Control
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: register.php"); 
    exit();
}

if (!isset($_GET['category_id'])) {
    header("Location: admindashboard.php");
    exit();
}

$category_id = $_GET['category_id'];
$message = "";

// Delete Question Logic
if (isset($_POST['delete_question'])) {
    $question_id = $_POST['question_id'];
    
    // Delete student answers tied to this specific question first to prevent foreign key errors
    $stmt1 = $conn->prepare("DELETE FROM user_answers WHERE question_id = ?");
    $stmt1->bind_param("i", $question_id);
    $stmt1->execute();

    // Delete the question
    $stmt2 = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt2->bind_param("i", $question_id);
    if ($stmt2->execute()) {
        $message = "<div class='alert success'>Question deleted successfully!</div>";
    }
}

// Fetch Quiz Info
$cat_stmt = $conn->prepare("SELECT title FROM quiz_categories WHERE id = ?");
$cat_stmt->bind_param("i", $category_id);
$cat_stmt->execute();
$quiz_info = $cat_stmt->get_result()->fetch_assoc();

if (!$quiz_info) {
    die("Quiz not found.");
}

// Fetch Questions
$q_stmt = $conn->prepare("SELECT * FROM questions WHERE category_id = ?");
$q_stmt->bind_param("i", $category_id);
$q_stmt->execute();
$questions = $q_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Questions | EDUQuiz</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #ff6b6b; --secondary: #7048e8; --text-dark: #2d3436; }
        body { font-family: 'Outfit', sans-serif; background: #f4f6f8; margin: 0; padding: 2rem; color: var(--text-dark); }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #eee; padding-bottom: 1rem; }
        .btn-back { background: #e5dbff; color: var(--secondary); padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 800; }
        .q-card { border: 1px solid #eee; padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; background: #fafafa; }
        .q-text { font-size: 1.2rem; font-weight: 800; margin-bottom: 10px; color: var(--secondary); }
        .options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; font-size: 0.95rem; }
        .correct-ans { background: #d3f9d8; padding: 4px 8px; border-radius: 6px; font-weight: 800; color: #2b8a3e; font-size: 0.8rem; }
        .btn-delete { background: #ffe3e3; color: #c92a2a; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn-delete:hover { background: #ffc9c9; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; }
        .success { background: #ebfbee; color: #2b8a3e; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-nav">
        <h2>Questions for: <?php echo htmlspecialchars($quiz_info['title']); ?></h2>
        <a href="admindashboard.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <?php if($message) echo $message; ?>

    <?php if ($questions->num_rows > 0): ?>
        <?php $num = 1; while($q = $questions->fetch_assoc()): ?>
            <div class="q-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex:1;">
                        <div class="q-text"><?php echo $num++; ?>. <?php echo htmlspecialchars($q['question']); ?></div>
                        <div class="options">
                            <div><strong>A:</strong> <?php echo htmlspecialchars($q['option_a']); ?></div>
                            <div><strong>B:</strong> <?php echo htmlspecialchars($q['option_b']); ?></div>
                            <div><strong>C:</strong> <?php echo htmlspecialchars($q['option_c']); ?></div>
                            <div><strong>D:</strong> <?php echo htmlspecialchars($q['option_d']); ?></div>
                        </div>
                        <span class="correct-ans">Correct Answer: Option <?php echo strtoupper($q['correct_answer']); ?></span>
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('Delete this question permanently?');">
                        <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                        <button type="submit" name="delete_question" class="btn-delete">🗑️ Delete</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: #888;">
            <h3>No questions found for this quiz.</h3>
            <p>Go back to the dashboard to add some!</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>