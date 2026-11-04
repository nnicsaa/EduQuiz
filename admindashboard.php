<?php
session_start();
include "db.php";

// Admin Access Control
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: register.php"); 
    exit();
}

$admin_username = $_SESSION['user'];
$message = "";
$current_tab = isset($_POST['active_tab']) && !empty($_POST['active_tab']) ? $_POST['active_tab'] : "students"; 

$last_category_id = null; 

//profile update
if (isset($_POST['update_profile'])) {
    $nickname = $_POST['nickname'];
    $pic_updated = false;

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($ext), $allowed)) {
            if(!is_dir('uploads')) { mkdir('uploads'); }
            $new_filename = $admin_username . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $new_filename)) {
                $stmt = $conn->prepare("UPDATE login SET nickname = ?, profile_pic = ? WHERE un = ?");
                $stmt->bind_param("sss", $nickname, $new_filename, $admin_username);
                $stmt->execute();
                $pic_updated = true;
                $message = "<div class='alert success'>Profile updated successfully!</div>";
                $current_tab = "edit-profile"; 
            }
        } else {
            $message = "<div class='alert error'>Invalid image format.</div>";
            $current_tab = "edit-profile";
        }
    }

    if (!$pic_updated) {
        $stmt = $conn->prepare("UPDATE login SET nickname = ? WHERE un = ?");
        $stmt->bind_param("ss", $nickname, $admin_username);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Profile updated!</div>";
            $current_tab = "edit-profile";
        }
    }
}

//acc. management
if (isset($_POST['approve_account'])) {
    $target_id = $_POST['user_id'];
    $approve_stmt = $conn->prepare("UPDATE login SET status = 'Active', failed_attempts = 0 WHERE id = ?");
    $approve_stmt->bind_param("i", $target_id);
    if ($approve_stmt->execute()) { $message = "<div class='alert success'>Account approved!</div>"; }
}

if (isset($_POST['approve_account'])) {
    $target_id = $_POST['user_id'];
    $approve_stmt = $conn->prepare("UPDATE login SET status = 'Active', failed_attempts = 0 WHERE id = ?");
    $approve_stmt->bind_param("i", $target_id);
    if ($approve_stmt->execute()) { $message = "<div class='alert success'>Account approved!</div>"; }
}

// NEW: Reject and Delete Pending Account
if (isset($_POST['reject_account'])) {
    $target_id = $_POST['user_id'];
    $reject_stmt = $conn->prepare("DELETE FROM login WHERE id = ?");
    $reject_stmt->bind_param("i", $target_id);
    if ($reject_stmt->execute()) { 
        $message = "<div class='alert success'>Pending account rejected and removed!</div>"; 
    }
}

if (isset($_POST['reactivate'])) {
    $target_id = $_POST['user_id'];
    $react_stmt = $conn->prepare("UPDATE login SET status = 'Active', failed_attempts = 0 WHERE id = ?");
    $react_stmt->bind_param("i", $target_id);
    if ($react_stmt->execute()) { $message = "<div class='alert success'>Account reactivated!</div>"; }
}

//quiz management
if (isset($_POST['create_category'])) {
    $title = $_POST['quiz_title'];
    $desc = $_POST['quiz_desc'];
    $time = $_POST['time_limit']; 
    $stmt = $conn->prepare("INSERT INTO quiz_categories (title, description, time_limit) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $desc, $time);
    if ($stmt->execute()) { 
        $message = "<div class='alert success'>Quiz Set Created!</div>"; 
        $current_tab = "quizzes"; 
    }
}

if (isset($_POST['add_question'])) {
    $cat_id = $_POST['category_id'];
    $q_text = $_POST['question_text'];
    $opt_a = $_POST['opt_a'];
    $opt_b = $_POST['opt_b'];
    $opt_c = $_POST['opt_c'];
    $opt_d = $_POST['opt_d'];
    $correct = $_POST['correct_answer'];
    
    $stmt = $conn->prepare("INSERT INTO questions (category_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $cat_id, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct);
    if ($stmt->execute()) { 
        $message = "<div class='alert success'>Question saved!</div>"; 
        $current_tab = "quizzes"; 
        $last_category_id = $cat_id; 
    }
}

if (isset($_POST['toggle_quiz_status'])) {
    $cat_id = $_POST['category_id'];
    $new_status = $_POST['current_status'] == 'Open' ? 'Closed' : 'Open';
    
    $stmt = $conn->prepare("UPDATE quiz_categories SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $cat_id);
    $stmt->execute();
    $current_tab = "quizzes"; 
}

if (isset($_POST['delete_quiz'])) {
    $cat_id = $_POST['category_id'];
    
    // 1. Delete all user answers tied to scores of this quiz
    $stmt1 = $conn->prepare("DELETE ua FROM user_answers ua INNER JOIN quiz_scores qs ON ua.score_id = qs.id WHERE qs.category_id = ?");
    $stmt1->bind_param("i", $cat_id);
    $stmt1->execute();

    // 2. Delete all student scores for this quiz
    $stmt2 = $conn->prepare("DELETE FROM quiz_scores WHERE category_id = ?");
    $stmt2->bind_param("i", $cat_id);
    $stmt2->execute();

    // 3. Delete all questions in this quiz
    $stmt3 = $conn->prepare("DELETE FROM questions WHERE category_id = ?");
    $stmt3->bind_param("i", $cat_id);
    $stmt3->execute();

    // 4. Finally, delete the quiz category itself
    $stmt4 = $conn->prepare("DELETE FROM quiz_categories WHERE id = ?");
    $stmt4->bind_param("i", $cat_id);
    if ($stmt4->execute()) {
        $message = "<div class='alert success'>Quiz and all related data deleted permanently!</div>";
    }
    $current_tab = "quizzes"; 
}

//reports
$selected_report_quiz = isset($_POST['report_quiz_id']) ? $_POST['report_quiz_id'] : null;
$report_results = null;

if ($selected_report_quiz) {
    $current_tab = "reports";
    $report_stmt = $conn->prepare("
        SELECT l.un, l.nickname, l.sec, MAX(qs.score) as max_score, qs.total_items, MAX(qs.date_taken) as last_taken 
        FROM login l
        JOIN quiz_scores qs ON l.un = qs.username
        WHERE qs.category_id = ? AND l.role = 'student'
        GROUP BY l.un
        ORDER BY max_score DESC
    ");
    $report_stmt->bind_param("i", $selected_report_quiz);
    $report_stmt->execute();
    $report_results = $report_stmt->get_result();
}

//data fetching
$stmt = $conn->prepare("SELECT un, nickname, profile_pic FROM login WHERE un = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$adminData = $stmt->get_result()->fetch_assoc();

$display_pic = !empty($adminData['profile_pic']) ? "uploads/" . $adminData['profile_pic'] : "https://ui-avatars.com/api/?name=" . urlencode($adminData['un']) . "&background=7048e8&color=fff";
$display_name = !empty($adminData['nickname']) ? $adminData['nickname'] : $adminData['un'];

$activeCount = $conn->query("SELECT COUNT(*) as total FROM login WHERE status = 'Active' AND role = 'student'")->fetch_assoc()['total']; 
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM login WHERE status = 'Pending' AND role = 'student'")->fetch_assoc()['total']; 
$deactivatedCount = $conn->query("SELECT COUNT(*) as total FROM login WHERE status = 'Deactivated' AND role = 'student'")->fetch_assoc()['total']; 

$pending_students_query = $conn->query("SELECT id, un, nickname, profile_pic, sec FROM login WHERE role = 'student' AND status = 'Pending' ORDER BY id DESC");
$students_query = $conn->query("SELECT id, un, nickname, profile_pic, sec, status, failed_attempts FROM login WHERE role = 'student' AND status != 'Pending' ORDER BY id DESC");

$categories_query = $conn->query("SELECT * FROM quiz_categories ORDER BY created_at DESC");
$categories_for_dropdown = $conn->query("SELECT * FROM quiz_categories ORDER BY title ASC");

// Single Student Records
$selected_student = isset($_GET['view_student']) ? $_GET['view_student'] : null;
$student_records = null;
$overall_stats = null;

if ($selected_student) {
    $current_tab = "students"; 
    $stmt = $conn->prepare("SELECT qs.score, qs.total_items, qs.date_taken, qc.title FROM quiz_scores qs LEFT JOIN quiz_categories qc ON qs.category_id = qc.id WHERE qs.username = ? ORDER BY qs.date_taken DESC");
    $stmt->bind_param("s", $selected_student);
    $stmt->execute();
    $student_records = $stmt->get_result();
    
    if ($student_records->num_rows > 0) {
        $total_quizzes = $student_records->num_rows;
        $total_percentage_sum = 0; $passed_quizzes = 0;
        while($row = $student_records->fetch_assoc()) {
            $percentage = ($row['total_items'] > 0) ? ($row['score'] / $row['total_items']) * 100 : 0;
            $total_percentage_sum += $percentage;
            if ($percentage >= 75) { $passed_quizzes++; }
        }
        $overall_stats = [
            'total_taken' => $total_quizzes,
            'average_score' => round($total_percentage_sum / $total_quizzes),
            'pass_rate' => round(($passed_quizzes / $total_quizzes) * 100)
        ];
        $student_records->data_seek(0);
    }
}
?>

<?php 
$page_title = "Admin Dashboard | EDUQuiz";
include "header.php"; 
?>

    <style>
        :root { --accent: #ff6b6b; --secondary: #7048e8; --text-dark: #2d3436; --bg-light: #f8f9fa; }
        body { margin: 0; font-family: 'Outfit', sans-serif; background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradient 15s ease infinite; display: flex; min-height: 100vh; }
        @keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .layout { display: flex; width: 100%; max-width: 1300px; margin: 2rem auto; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; }

        .sidebar { width: 250px; background: rgba(255,255,255,0.5); padding: 2rem; border-right: 1px solid rgba(0,0,0,0.1); display: flex; flex-direction: column; align-items: center;}
        .logo { font-weight: 800; font-size: 1.8rem; background: linear-gradient(to right, var(--accent), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 2rem; width: 100%; text-align: center;}
        .sidebar-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--secondary); margin-bottom: 10px; }
        .user-greeting { font-size: 1rem; font-weight: 600; margin-bottom: 2rem; text-align: center; color: #636e72;}
        .user-greeting span { color: var(--secondary); font-weight: 800; display: block; font-size: 1.3rem; }

        .nav-btn { width: 100%; background: transparent; border: none; text-align: left; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; font-weight: 600; color: #636e72; cursor: pointer; transition: 0.3s; }
        .nav-btn:hover, .nav-btn.active { background: var(--secondary); color: white; }
        .logout { margin-top: auto; color: #c92a2a; }

        .main-content { flex: 1; padding: 2.5rem; overflow-y: auto; max-height: 95vh; }
        .admin-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2.5rem; }
        .admin-stat-card { background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid #f1f3f5; text-align: center; }
        .admin-stat-num { font-size: 2.5rem; font-weight: 800; color: var(--secondary); }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; margin-bottom: 2rem; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f1f3f5; }
        th { background: var(--secondary); color: white; }
        
        .status { padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-active { background: #ebfbee; color: #2b8a3e; } 
        .status-locked { background: #fff5f5; color: #c92a2a; }
        .status-pending { background: #fff3bf; color: #f59f00; }

        .btn-view { background: #e5dbff; color: var(--secondary); padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn-submit { background: var(--secondary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .form-card { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #f1f3f5; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 8px; box-sizing: border-box; font-family: inherit; }

        .section { display: none; }
        .section.active { display: block; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; }
        .success { background: #ebfbee; color: #2b8a3e; }
        .error { background: #fff5f5; color: #c92a2a; }
    </style>
</head>
<body>

<div class="layout">
    <div class="sidebar">
        <div class="logo">Admin Panel</div>
        <img src="<?php echo $display_pic; ?>" class="sidebar-avatar">
        <div class="user-greeting">Welcome, <span><?php echo htmlspecialchars($display_name); ?></span></div>

        <button class="nav-btn" id="btn-students" onclick="switchTab('students', this)">Students</button>
        <button class="nav-btn" id="btn-quizzes" onclick="switchTab('quizzes', this)">Manage Quizzes</button>
        <button class="nav-btn" id="btn-reports" onclick="switchTab('reports', this)">Quiz Reports</button>
        <button class="nav-btn" id="btn-edit-profile" onclick="switchTab('edit-profile', this)">Edit Profile</button>
        
        <a href="logout.php" style="text-decoration:none; margin-top: auto; width:100%;"><button class="nav-btn logout">Logout</button></a>
    </div>

    <div class="main-content">
        <?php if($message) echo $message; ?>

        <div class="admin-stats-grid">
            <div class="admin-stat-card"><div class="admin-stat-num"><?php echo $activeCount; ?></div><div class="admin-stat-label">Active</div></div>
            <div class="admin-stat-card" style="border: 2px solid #fcc419;"><div class="admin-stat-num" style="color: #f59f00;"><?php echo $pendingCount; ?></div><div class="admin-stat-label">Pending</div></div>
            <div class="admin-stat-card"><div class="admin-stat-num"><?php echo $deactivatedCount; ?></div><div class="admin-stat-label">Deactivated</div></div>
        </div>

        <div id="students" class="section">
            <?php if($pending_students_query->num_rows > 0): ?>
                <h2 style="color: #f59f00;">Pending Approvals</h2>
                <table>
                    <thead><tr><th>User</th><th>Section</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while($row = $pending_students_query->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $row['un']; ?></strong></td>
                                <td><?php echo $row['sec']; ?></td>
                                <td>
    <div style="display: flex; gap: 10px;">
        <form method="POST" style="margin:0;">
            <input type='hidden' name='user_id' value='<?php echo $row['id']; ?>'>
            <button type='submit' name='approve_account' class='btn-submit' style="padding: 5px 10px; background: #2b8a3e;">Approve</button>
        </form>
        
        <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to reject and permanently delete this student account?');">
            <input type='hidden' name='user_id' value='<?php echo $row['id']; ?>'>
            <button type='submit' name='reject_account' class='btn-submit' style="padding: 5px 10px; background: #c92a2a;">Reject</button>
        </form>
    </div>
</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Registered Students</h2>
            <table>
                <thead><tr><th>User</th><th>Section</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php while($row = $students_query->fetch_assoc()): 
                        $isLocked = ($row['status'] === 'Deactivated' || $row['failed_attempts'] >= 2);
                    ?>
                        <tr>
                            <td><strong><?php echo $row['un']; ?></strong></td>
                            <td><?php echo $row['sec']; ?></td>
                            <td><span class="status <?php echo $isLocked ? 'status-locked' : 'status-active'; ?>"><?php echo $isLocked ? 'Deactivated' : 'Active'; ?></span></td>
                            <td>
                                <a href="admindashboard.php?view_student=<?php echo urlencode($row['un']); ?>" class="btn-view">View History</a>
                                <?php if($isLocked): ?>
                                    <form method='POST' style='display:inline;'><input type='hidden' name='user_id' value='<?php echo $row['id']; ?>'><button type='submit' name='reactivate' class='btn-submit' style="padding: 5px 10px; background:#fcc419; color: #000;">Reactivate</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($selected_student): ?>
                <div id="student-results" class="form-card" style="border: 2px solid var(--secondary);">
                    <h3>Record: <?php echo htmlspecialchars($selected_student); ?></h3>
                    <?php if ($overall_stats): ?>
                        <p>Avg Score: <?php echo $overall_stats['average_score']; ?>% | Pass Rate: <?php echo $overall_stats['pass_rate']; ?>%</p>
                        <table>
                            <thead><tr><th>Quiz</th><th>Score</th><th>Result</th></tr></thead>
                            <tbody>
                                <?php while($sr = $student_records->fetch_assoc()): 
                                    $p = ($sr['total_items'] > 0) ? round(($sr['score'] / $sr['total_items']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo $sr['title']; ?></td>
                                        <td><?php echo $sr['score']; ?>/<?php echo $sr['total_items']; ?></td>
                                        <td><span class="status <?php echo $p >= 75 ? 'status-active' : 'status-locked'; ?>"><?php echo $p; ?>%</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <a href="admindashboard.php" class="btn-view" style="background:#ff6b6b; color:white;">Close</a>
                </div>
            <?php endif; ?>
        </div>

        <div id="quizzes" class="section">
            <h2>Quiz Management</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-card">
                    <h3>Create Set</h3>
                    <form method="POST">
                        <input type="hidden" name="active_tab" id="active_tab_<?php echo uniqid(); ?>" class="active-tab-input" value="">
                        <input type="text" name="quiz_title" placeholder="Quiz Title" required><br><br>
                        <textarea name="quiz_desc" placeholder="Description"></textarea><br><br>
                        <input type="number" name="time_limit" value="10" placeholder="Minutes"><br><br>
                        <button type="submit" name="create_category" class="btn-submit">Save Set</button>
                    </form>
                </div>
                <div class="form-card">
                    <h3>Add Question</h3>
                    <form method="POST">
                        <input type="hidden" name="active_tab" id="active_tab_<?php echo uniqid(); ?>" class="active-tab-input" value="">
                        <select name="category_id" required>
                            <?php 
                            $categories_for_dropdown->data_seek(0);
                            while($c = $categories_for_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['title']; ?></option>
                            <?php endwhile; ?>
                        </select><br><br>
                        <textarea name="question_text" placeholder="Question Text" required></textarea><br><br>
                        <input type="text" name="opt_a" placeholder="Option A" required><br><br>
                        <input type="text" name="opt_b" placeholder="Option B" required><br><br>
                        <input type="text" name="opt_c" placeholder="Option C" required><br><br>
                        <input type="text" name="opt_d" placeholder="Option D" required><br><br>
                        <select name="correct_answer">
                            <option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option>
                        </select><br><br>
                        <button type="submit" name="add_question" class="btn-submit">Add Question</button>
                    </form>
                </div>
            </div>

            <ul style="background: white; padding: 1rem 2rem; border-radius: 12px; margin-top: 20px;">
                <?php while($cat = $categories_query->fetch_assoc()): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <div>
                            <strong><?php echo htmlspecialchars($cat['title']); ?></strong>
                            <span class="status <?php echo $cat['status'] == 'Open' ? 'status-active' : 'status-locked'; ?>" style="margin-left: 10px;">
                                <?php echo $cat['status']; ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 10px;">
    <!-- Manage Questions Button -->
    <a href="manage_questions.php?category_id=<?php echo $cat['id']; ?>" class="btn-view" style="background: var(--secondary); color: white; text-decoration: none; display: flex; align-items: center;">
        📝 Manage
    </a>

    <!-- Toggle Status Button -->
    <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to change the status of this quiz?');">
        <input type="hidden" name="active_tab" value="quizzes">
        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
        <input type="hidden" name="current_status" value="<?php echo $cat['status']; ?>">
        <button type="submit" name="toggle_quiz_status" class="btn-view" style="background: <?php echo $cat['status'] == 'Open' ? '#f59f00' : '#20c997'; ?>; color: white; border: none; cursor: pointer;">
            <?php echo $cat['status'] == 'Open' ? '🔒 Close' : '🔓 Open'; ?>
        </button>
    </form>
    
    <!-- Delete Quiz Button -->
    <form method="POST" style="margin:0;" onsubmit="return confirm('⚠️ Are you absolutely sure? This will permanently delete the quiz, all its questions, and all student scores.');">
        <input type="hidden" name="active_tab" value="quizzes">
        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
        <button type="submit" name="delete_quiz" class="btn-view" style="background: #c92a2a; color: white; border: none; cursor: pointer;">
            🗑️ Delete
        </button>
    </form>
</div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div id="reports" class="section">
            <h2>Quiz Performance Reports</h2>
            <div class="form-card">
                <form method="POST">
                    <input type="hidden" name="active_tab" id="active_tab_<?php echo uniqid(); ?>" class="active-tab-input" value="">
                    <select name="report_quiz_id" required>
                        <option value="">-- Select Quiz --</option>
                        <?php 
                        $categories_for_dropdown->data_seek(0);
                        while($c = $categories_for_dropdown->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($selected_report_quiz == $c['id']) ? 'selected' : ''; ?>><?php echo $c['title']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn-submit" style="margin-top:10px;">Get Quiz Results</button>
                </form>
            </div>

            <?php if ($report_results): ?>
                <table>
                    <thead><tr><th>Student</th><th>Best Score</th><th>Result</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while($row = $report_results->fetch_assoc()): 
                            $pct = round(($row['max_score'] / $row['total_items']) * 100);
                        ?>
                            <tr>
                                <td><strong><?php echo $row['nickname'] ?? $row['un']; ?></strong></td>
                                <td><?php echo $row['max_score']; ?> / <?php echo $row['total_items']; ?></td>
                                <td><span class="status <?php echo $pct >= 75 ? 'status-active' : 'status-locked'; ?>"><?php echo $pct; ?>%</span></td>
                                <td><?php echo date('M d, Y', strtotime($row['last_taken'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div id="edit-profile" class="section">
            <h2>Admin Settings</h2>
            <div class="form-card" style="max-width: 500px;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="active_tab" id="active_tab_<?php echo uniqid(); ?>" class="active-tab-input" value="">
                    <label>Display Name</label>
                    <input type="text" name="nickname" value="<?php echo htmlspecialchars($adminData['nickname'] ?? ''); ?>"><br><br>
                    <label>Profile Picture</label>
                    <input type="file" name="profile_pic"><br><br>
                    <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(id, btn) {

    document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if(btn) btn.classList.add('active');

    document.querySelectorAll('.active-tab-input').forEach(input => {
        input.value = id;
    });
}


window.onload = function() {
    let activeTab = "<?php echo $current_tab; ?>";
    document.querySelectorAll('.active-tab-input').forEach(input => {
        input.value = activeTab;
    });
};


    <?php if ($current_tab === 'quizzes'): ?> switchTab('quizzes', document.getElementById('btn-quizzes'));
    <?php elseif ($current_tab === 'reports'): ?> switchTab('reports', document.getElementById('btn-reports'));
    <?php elseif ($current_tab === 'edit-profile'): ?> switchTab('edit-profile', document.getElementById('btn-edit-profile'));
    <?php else: ?> switchTab('students', document.getElementById('btn-students'));
    <?php endif; ?>
</script>
<?php include "footer.php"; ?>