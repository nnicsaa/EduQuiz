<?php
session_start();
include "db.php";

$message = "";

// Registration logic
if(isset($_POST['register'])){
    $un   = $_POST['username']; 
    $pw   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $sec  = $_POST['section'];
    $role = 'student'; 
    $status = 'Pending'; 

    $stmt = $conn->prepare("INSERT INTO login(un, pw, sec, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $un, $pw, $sec, $role, $status);
    if($stmt->execute()){
        $message = "<div class='alert success'>Account created! Please wait for admin approval before logging in.</div>";
    } else {
        $message = "<div class='alert error'>Username is already taken or database error!</div>";
    }
}

// Forgot Password Logic
if(isset($_POST['forgot_password'])){
    $un = $_POST['username'];
    $sec = $_POST['section'];
    $new_pw = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    // Verify if user exists AND section matches (acts as a security check)
    $stmt = $conn->prepare("SELECT pw FROM login WHERE un = ? AND sec = ? AND role = 'student'");
    $stmt->bind_param("ss", $un, $sec);
    $stmt->execute();
    
    if($stmt->get_result()->num_rows > 0){
        // Verification passed, update the password
        $update = $conn->prepare("UPDATE login SET pw = ? WHERE un = ? AND role = 'student'");
        $update->bind_param("ss", $new_pw, $un);
        $update->execute();
        $message = "<div class='alert success'>Password reset successfully! You can now log in.</div>";
    } else {
        $message = "<div class='alert error'>Incorrect Username or Section. (Note: Only student accounts can be reset here).</div>";
    }
}

// Login logic
if(isset($_POST['login'])){
    $un   = $_POST['username'];
    $pw   = $_POST['password'];
    $role = $_POST['role']; 

    $stmt = $conn->prepare("SELECT pw, status, failed_attempts FROM login WHERE un = ? AND role = ?");
    $stmt->bind_param("ss", $un, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){
        if($row['status'] === 'Pending') {
            $message = "<div class='alert highlight' style='background: #fff3bf; color: #f59f00; border: 1px solid #ffec99;'>⏳ Your account is currently pending admin approval. Please try again later.</div>";
        } 
        elseif($row['status'] === 'Deactivated') {
            $message = "<div class='alert error'>Account deactivated due to multiple failed login attempts.</div>";
        } 
        else {
            if(password_verify($pw, $row['pw'])){
                
                //streak logic
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));

                $streakStmt = $conn->prepare("SELECT last_login_date, current_streak FROM login WHERE un = ?");
                $streakStmt->bind_param("s", $un);
                $streakStmt->execute();
                $streakData = $streakStmt->get_result()->fetch_assoc();

                $newStreak = $streakData['current_streak'];

                if ($streakData['last_login_date'] == $yesterday) {
                    $newStreak++;
                } elseif ($streakData['last_login_date'] != $today) {
                    $newStreak = 1;
                }
            
                $updateStreak = $conn->prepare("UPDATE login SET last_login_date = ?, current_streak = ?, failed_attempts = 0 WHERE un = ?");
                $updateStreak->bind_param("sis", $today, $newStreak, $un);
                $updateStreak->execute();

                $_SESSION['user'] = $un;
                $_SESSION['role'] = $role; 
                
                if ($role === 'admin') { header("Location: admindashboard.php"); } 
                else { header("Location: dashboard.php"); }
                exit();
                
            } else {
                $attempts = $row['failed_attempts'] + 1;
                
                if($attempts >= 2) {
                    $deactStmt = $conn->prepare("UPDATE login SET status = 'Deactivated', failed_attempts = ? WHERE un = ?");
                    $deactStmt->bind_param("is", $attempts, $un);
                    $deactStmt->execute();
                    $message = "<div class='alert error'>Account deactivated! Too many failed attempts.</div>";
                } else {
                    $updateStmt = $conn->prepare("UPDATE login SET failed_attempts = ? WHERE un = ?");
                    $updateStmt->bind_param("is", $attempts, $un);
                    $updateStmt->execute();
                    $message = "<div class='alert error'>Incorrect password. Attempt $attempts of 2.</div>";
                }
            }
        }
    } else {
        $message = "<div class='alert error'>User not found or incorrect role selected.</div>";
    }
}
?>

<?php 
$page_title = "EDUQuiz | Login";
include "header.php"; 
?>
    <style>
        :root { 
            --accent: #ff6b6b; 
            --secondary: #7048e8; 
            --highlight: #fcc419; 
            --text-dark: #2d3436; 
            --white: #ffffff; 
        }
        
        body { 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            font-family: 'Outfit', sans-serif; 
            overflow: hidden; 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        }

        .bg-shapes { 
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            filter: blur(70px);
            opacity: 0.5;
            animation: float 20s infinite linear;
            border-radius: 50%;
        }

        .shape:nth-child(1) { width: 450px; height: 450px; background: rgba(112, 72, 232, 0.5); top: -10%; left: -10%; animation-duration: 25s; }
        .shape:nth-child(2) { width: 350px; height: 350px; background: rgba(255, 107, 107, 0.4); bottom: -10%; right: -5%; animation-duration: 20s; animation-direction: reverse; }
        .shape:nth-child(3) { width: 300px; height: 300px; background: rgba(35, 166, 213, 0.4); top: 30%; left: 50%; animation-duration: 30s; }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            33% { transform: translate(40px, -60px) rotate(10deg) scale(1.1); }
            66% { transform: translate(-30px, 30px) rotate(-5deg) scale(0.9); }
            100% { transform: translate(0, 0) rotate(0deg) scale(1); }
        }

        .auth-container { 
            position: relative; 
            z-index: 10;
            background: rgba(255, 255, 255, 0.92); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px);
            padding: 3rem 2rem; 
            border-radius: 24px; 
            box-shadow: 0 25px 60px rgba(0,0,0,0.4); 
            width: 100%; 
            max-width: 380px; 
            text-align: center; 
            border: 1px solid rgba(255,255,255,0.2); 
            transition: transform 0.3s ease;
        }

        .show-pw-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-top: -5px;
            margin-bottom: 10px;
            padding-left: 5px;
        }
        
        .show-pw-container input[type="checkbox"] {
            width: auto;
            margin: 0;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .show-pw-container label {
            font-size: 0.85rem;
            color: #636e72;
            cursor: pointer;
        }

        .auth-container:hover { transform: translateY(-4px); }

        .logo { font-weight: 800; font-size: 2.2rem; letter-spacing: -1px; margin-bottom: 1.5rem; background: linear-gradient(to right, var(--accent), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        h2 { color: var(--text-dark); margin-bottom: 1.2rem; font-size: 1.4rem; }
        
        .alert { padding: 12px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 1rem; font-weight: 600; }
        .success { background: #ebfbee; color: #2b8a3e; border: 1px solid #b2f2bb; }
        .error { background: #fff5f5; color: #c92a2a; border: 1px solid #ffc9c9; }
        
        input, select { 
            width: 100%; padding: 14px 18px; margin: 10px 0; border: 2px solid #f1f3f5; border-radius: 14px; 
            box-sizing: border-box; font-size: 1rem; transition: all 0.3s ease; background: rgba(248, 249, 250, 0.8); font-family: inherit; 
        }
        select { color: #495057; cursor: pointer; appearance: none; }
        input:focus, select:focus { outline: none; border-color: var(--secondary); background: #fff; box-shadow: 0 0 0 4px rgba(112, 72, 232, 0.15); }
        
        button { 
            width: 100%; padding: 14px; margin-top: 1.5rem; border: none; border-radius: 14px; 
            background: linear-gradient(135deg, var(--secondary) 0%, #512da8 100%); color: white; font-weight: 700; 
            font-size: 1.1rem; cursor: pointer; box-shadow: 0 8px 15px rgba(112, 72, 232, 0.3); transition: transform 0.2s, box-shadow 0.2s; 
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 12px 20px rgba(112, 72, 232, 0.4); }
        
        .toggle-link { margin-top: 2rem; font-size: 0.95rem; color: #495057; cursor: pointer; transition: color 0.2s;}
        .toggle-link:hover { color: var(--text-dark); }
        .toggle-link span { color: var(--secondary); font-weight: 800; text-decoration: underline; }
        
        .forgot-link { text-align: right; margin-top: -5px; font-size: 0.85rem; color: var(--secondary); cursor: pointer; text-decoration: underline; margin-bottom: 10px; }
        .hidden { display: none; }

@media screen and (max-width: 768px) {
    .dashboard-container { flex-direction: column; margin: 15px; max-width: 100%; border-radius: 16px; }
    .sidebar { width: 100%; box-sizing: border-box; border-right: none; border-bottom: 1px solid var(--border-glass); padding: 1.5rem 1rem; }
    .main-content { padding: 1.5rem 1rem; box-sizing: border-box; }
    .quiz-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; }
    .table-container { padding: 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .modern-table { min-width: 550px; }
    .form-group input, .btn-submit { box-sizing: border-box; width: 100%; }
    .nav-btn { text-align: center; padding: 15px; }
}
    </style>
</head>
<body>

<div class="bg-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
</div>

<div class="auth-container">
    <div class="logo">EDUQuiz!</div>

    <?php if($message) echo $message; ?>

    <!-- LOGIN FORM -->
    <div id="login-form">
        <h2>Ready for a quiz?</h2>
        <form method="POST">
            <select name="role" required>
                <option value="" disabled selected>Select your role</option>
                <option value="student">Student</option>
                <option value="admin">Admin</option>
            </select>
            <input type="text" name="username" placeholder="Enter Username" required>
            <input type="password" id="login-pw" name="password" placeholder="Enter Password" required>
            
            <div class="show-pw-container">
                <input type="checkbox" id="show-login" onclick="togglePassword('login-pw')">
                <label for="show-login">Show Password</label>
            </div>
            
            <div class="forgot-link" onclick="showForgot()">Forgot Password?</div>
            
            <button type="submit" name="login">Let's Start ➔</button>
        </form>
        <div class="toggle-link" onclick="showRegister()">New student? <span>Sign up here</span></div>
    </div>

    <!-- REGISTER FORM -->
    <div id="register-form" class="hidden">
        <h2>Join the community!</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Pick a Username" required>
            <input type="password" id="reg-pw" name="password" placeholder="Create Password" required>
            <div class="show-pw-container">
                <input type="checkbox" id="show-reg" onclick="togglePassword('reg-pw')">
                <label for="show-reg">Show Password</label>
            </div>
            <input type="text" name="section" placeholder="Your Section (e.g. 10-A)" required>
            <button type="submit" name="register">Create Account</button>
        </form>
        <div class="toggle-link" onclick="showLogin()">Already joined? <span>Login instead</span></div>
    </div>

    <!-- FORGOT PASSWORD FORM -->
    <div id="forgot-form" class="hidden">
        <h2>Reset Password</h2>
        <p style="font-size: 0.85rem; color: #636e72; margin-bottom: 15px;">Verify your section to reset your password.</p>
        <form method="POST">
            <input type="text" name="username" placeholder="Enter Username" required>
            <input type="text" name="section" placeholder="Enter Your Section (e.g. 10-A)" required>
            <input type="password" id="forgot-pw" name="new_password" placeholder="Enter New Password" required>
            <div class="show-pw-container">
                <input type="checkbox" id="show-forgot" onclick="togglePassword('forgot-pw')">
                <label for="show-forgot">Show Password</label>
            </div>
            <button type="submit" name="forgot_password">Reset Password</button>
        </form>
        <div class="toggle-link" onclick="showLogin()">Back to <span>Login</span></div>
    </div>

</div>

<script>
    function showLogin() {
        document.getElementById('login-form').classList.remove('hidden');
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('forgot-form').classList.add('hidden');
    }

    function showRegister() {
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
        document.getElementById('forgot-form').classList.add('hidden');
    }

    function showForgot() {
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('forgot-form').classList.remove('hidden');
    }

    function togglePassword(inputId) {
        var pwInput = document.getElementById(inputId);
        if (pwInput.type === "password") {
            pwInput.type = "text";
        } else {
            pwInput.type = "password";
        }
    }
</script>

<?php include "footer.php"; ?>