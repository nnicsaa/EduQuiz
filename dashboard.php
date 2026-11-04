<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) { header("Location: register.php"); exit(); }
$currentUser = $_SESSION['user'];
$message = "";

//edit profile
if (isset($_POST['update_profile'])) {
    $nickname = $_POST['nickname'];
    $pic_updated = false;

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($ext), $allowed)) {
            if(!is_dir('uploads')) { mkdir('uploads'); }
            $new_filename = $currentUser . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $new_filename)) {
                $stmt = $conn->prepare("UPDATE login SET nickname = ?, profile_pic = ? WHERE un = ?");
                $stmt->bind_param("sss", $nickname, $new_filename, $currentUser);
                $stmt->execute();
                $pic_updated = true;
                $message = "<div class='alert success'>Profile updated successfully!</div>";
            }
        } else {
            $message = "<div class='alert error'>Invalid image format!</div>";
        }
    }

    if (!$pic_updated) {
        $stmt = $conn->prepare("UPDATE login SET nickname = ? WHERE un = ?");
        $stmt->bind_param("ss", $nickname, $currentUser);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Profile updated!</div>";
        }
    }
}

//leaderboard stats
$conn->query("UPDATE login SET total_points = (SELECT IFNULL(SUM(score), 0) FROM quiz_scores WHERE username = login.un) WHERE role = 'student'");

//data of students
$stmt = $conn->prepare("SELECT un, name, sec, nickname, profile_pic, current_streak FROM login WHERE un = ?");
$stmt->bind_param("s", $currentUser);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

$display_pic = !empty($userData['profile_pic']) ? "uploads/" . $userData['profile_pic'] : "https://ui-avatars.com/api/?name=" . urlencode($userData['un']) . "&background=7048e8&color=fff";
$display_name = !empty($userData['nickname']) ? $userData['nickname'] : $userData['un'];

//top ten leaderboard
$leaderboardQuery = $conn->query("SELECT nickname, un, total_points, profile_pic FROM login WHERE role = 'student' ORDER BY total_points DESC LIMIT 10");

//available quizzes
$quizzes = $conn->query("SELECT * FROM quiz_categories ORDER BY created_at DESC");

//history of user
$historyQuery = $conn->prepare("SELECT qs.id, qs.score, qs.total_items, qs.date_taken, qc.title FROM quiz_scores qs LEFT JOIN quiz_categories qc ON qs.category_id = qc.id WHERE qs.username = ? ORDER BY qs.date_taken DESC");
$historyQuery->bind_param("s", $currentUser);
$historyQuery->execute();
$historyResult = $historyQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EDUQuiz! | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>

        /* Modern History Table & Cards */
.table-container {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    border: 1px solid #eee;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px; /* Gives a "card" look to rows */
}

.modern-table th {
    text-align: left;
    padding: 12px 15px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 1px;
}

.modern-table tbody tr {
    background: #fdfdfd;
    transition: transform 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.modern-table tbody tr:hover {
    transform: scale(1.01);
    background: #fff;
}

.modern-table td {
    padding: 15px;
    border-top: 1px solid #f1f1f1;
    border-bottom: 1px solid #f1f1f1;
}

.modern-table td:first-child {
    border-left: 1px solid #f1f1f1;
    border-top-left-radius: 12px;
    border-bottom-left-radius: 12px;
    font-weight: 600;
}

.modern-table td:last-child {
    border-right: 1px solid #f1f1f1;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    text-align: center;
}

/* Specific Badge improvements */
.status-badge-small {
    display: inline-block;
    min-width: 60px;
    text-align: center;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
}

.quiz-info-sub {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 4px;
}

        :root { --accent: #ff6b6b; --secondary: #7048e8; --highlight: #fcc419; --text-dark: #2d3436; --text-muted: #636e72; --bg-glass: rgba(255, 255, 255, 0.9); --border-glass: rgba(255, 255, 255, 0.4); }
        body { margin: 0; display: flex; min-height: 100vh; font-family: 'Outfit', sans-serif; background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradient 15s ease infinite; color: var(--text-dark); }
        @keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .dashboard-container { display: flex; width: 100%; max-width: 1100px; margin: 2rem auto; background: var(--bg-glass); backdrop-filter: blur(15px); border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--border-glass); overflow: hidden; }
        .sidebar { width: 260px; background: rgba(255, 255, 255, 0.5); padding: 2rem; border-right: 1px solid var(--border-glass); display: flex; flex-direction: column; align-items: center; }
        .logo { font-weight: 800; font-size: 2rem; background: linear-gradient(to right, var(--accent), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 1.5rem; }
        
        .sidebar-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--secondary); box-shadow: 0 4px 10px rgba(112, 72, 232, 0.2); }
        .streak-badge { background: #fff3bf; color: #e67e22; padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 0.85rem; margin-top: -10px; z-index: 2; border: 2px solid #fab005; }
        
        .user-greeting { font-size: 1.1rem; font-weight: 600; margin: 1rem 0 2rem 0; text-align: center; width: 100%; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 1rem;}
        .user-greeting span { color: var(--secondary); font-weight: 800; display: block; font-size: 1.2rem; }
        
        .nav-btn { width: 100%; background: transparent; border: none; text-align: left; padding: 12px 16px; margin-bottom: 8px; border-radius: 12px; font-size: 1rem; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: 0.3s; font-family: 'Outfit'; }
        .nav-btn:hover, .nav-btn.active { background: var(--secondary); color: white; transform: translateX(5px); }
        .logout-btn { margin-top: auto; color: #c92a2a; background: #ffe3e3; }

        .main-content { flex: 1; padding: 2.5rem; overflow-y: auto; }
        .content-section { display: none; animation: fadeIn 0.4s ease; }
        .content-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Leaderboard */
        .lb-card { display: flex; align-items: center; background: white; padding: 12px 20px; border-radius: 16px; margin-bottom: 10px; border: 1px solid #eee; }
        .lb-rank { font-weight: 800; font-size: 1.2rem; width: 30px; color: var(--secondary); }
        .lb-avatar { width: 40px; height: 40px; border-radius: 50%; margin: 0 15px; object-fit: cover; }
        .lb-info { flex: 1; }
        .lb-points { font-weight: 800; color: var(--accent); }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; font-family: inherit; }
        .btn-submit { background: var(--secondary); color: white; padding: 12px 25px; border: none; border-radius: 10px; font-weight: 800; cursor: pointer; }

        .alert { padding: 12px; border-radius: 10px; margin-bottom: 1rem; font-weight: 600; }
        .success { background: #ebfbee; color: #2b8a3e; }
        .error { background: #fff5f5; color: #c92a2a; }
        
        .quiz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; }
        .quiz-card { background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid #eee; text-align:center; }
        .btn-take { display: block; background: var(--secondary); color: white; padding: 10px; border-radius: 10px; text-decoration: none; font-weight: 800; margin-top: 10px; }
        .status-badge-small { padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: 800; }
        .pass { background: #ebfbee; color: #2b8a3e; }
        .fail { background: #fff5f5; color: #c92a2a; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="logo">EDUQuiz!</div>
        
        <img src="<?php echo $display_pic; ?>" class="sidebar-avatar">
        <div class="streak-badge">🔥 <?php echo $userData['current_streak']; ?> Day Streak</div>
        
        <div class="user-greeting">Welcome, <span><?php echo htmlspecialchars($display_name); ?></span></div>
        
        <button class="nav-btn active" onclick="switchTab(event, 'overview')">Overview</button>
        <button class="nav-btn" onclick="switchTab(event, 'leaderboard')">Leaderboard</button>
        <button class="nav-btn" onclick="switchTab(event, 'quiz-history')">My Progress</button>
        <button class="nav-btn" onclick="switchTab(event, 'edit-profile')">Edit Profile</button>
        
        <a href="logout.php" style="margin-top: auto; width:100%; text-decoration:none;">
            <button class="nav-btn logout-btn">Log Out</button>
        </a>
    </div>

    <div class="main-content">
        <?php if($message) echo $message; ?>

        <div id="overview" class="content-section active">
            <h2 class="section-header">Available Quizzes</h2>
            <div class="quiz-grid">
                <?php while($q = $quizzes->fetch_assoc()): ?>
                    <div class="quiz-card" style="<?php echo $q['status'] == 'Closed' ? 'opacity: 0.6;' : ''; ?>">
    <h4><?php echo htmlspecialchars($q['title']); ?></h4>
    <?php if($q['status'] == 'Open'): ?>
        <a href="quiz.php?category_id=<?php echo $q['id']; ?>" class="btn-take">Start Quiz</a>
    <?php else: ?>
        <button class="btn-take" style="background: #adb5bd; cursor: not-allowed;" disabled>Closed</button>
    <?php endif; ?>
</div>
                <?php endwhile; ?>
            </div>
        </div>

        <div id="leaderboard" class="content-section">
            <h2 class="section-header">Students' Leaderboard</h2>
            <?php 
            $rank = 1;
            while($lb = $leaderboardQuery->fetch_assoc()): 
                $lb_pic = !empty($lb['profile_pic']) ? "uploads/" . $lb['profile_pic'] : "https://ui-avatars.com/api/?name=" . urlencode($lb['un']) . "&background=random";
                $lb_name = !empty($lb['nickname']) ? $lb['nickname'] : $lb['un'];
            ?>
                <div class="lb-card">
                    <div class="lb-rank">#<?php echo $rank++; ?></div>
                    <img src="<?php echo $lb_pic; ?>" class="lb-avatar">
                    <div class="lb-info">
                        <strong><?php echo htmlspecialchars($lb_name); ?></strong>
                        <span style="font-size:0.8rem; color:#888;">@<?php echo htmlspecialchars($lb['un']); ?></span>
                    </div>
                    <div class="lb-points"><?php echo number_format($lb['total_points']); ?> pts</div>
                </div>
            <?php endwhile; ?>
        </div>

        <div id="quiz-history" class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 class="section-header" style="margin:0;">My Progress</h2>
        <span style="font-size: 0.9rem; color: var(--text-muted);">Showing last <?php echo $historyResult->num_rows; ?> attempts</span>
    </div>

    <div class="table-container">
        <?php if ($historyResult->num_rows > 0): ?>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Quiz Module</th>
                        <th>Attempt Date</th>
                        <th>Raw Score</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $historyResult->fetch_assoc()): 
                        $pct = ($row['total_items'] > 0) ? round(($row['score'] / $row['total_items']) * 100) : 0;
                        $isPassing = $pct >= 75;
                    ?>
                        <tr>
                            <td>
                                <span><?php echo htmlspecialchars($row['title']); ?></span>
                                <small class="quiz-info-sub">Multiple Choice</small>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($row['date_taken'])); ?></div>
                                <div style="font-size: 0.7rem; color: #aaa;"><?php echo date('h:i A', strtotime($row['date_taken'])); ?></div>
                            </td>
                            <td style="font-family: monospace; font-size: 1.1rem;">
                                <strong><?php echo $row['score']; ?></strong><span style="color:#ccc;">/<?php echo $row['total_items']; ?></span>
                            </td>
                            <td>
    <span class="status-badge-small <?php echo $isPassing ? 'pass' : 'fail'; ?>">
        <?php echo $pct; ?>%
    </span>
    <a href="review.php?score_id=<?php echo $row['id']; ?>" style="display:block; font-size:0.7rem; margin-top:5px; color:var(--secondary); text-decoration:none; font-weight:600;">View Details →</a>
</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align:center; padding: 3rem; color: var(--text-muted);">
                <p>No quiz history yet. Time to take your first quiz!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

        <div id="edit-profile" class="content-section">
            <h2 class="section-header">Edit Profile</h2>
            <div class="table-container">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Username (Cannot be edited)</label>
                        <input type="text" value="<?php echo htmlspecialchars($userData['un']); ?>" disabled style="background:#f1f1f1; cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Nickname</label>
                        <input type="text" name="nickname" value="<?php echo htmlspecialchars($userData['nickname'] ?? ''); ?>" placeholder="What should we call you?">
                    </div>
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_pic" accept="image/*">
                    </div>
                    <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(evt, sectionId) {
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(sectionId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }
</script>
</body>
</html>