<?php
include "db.php";
die("Seeder locked for security. Remove this line to run again.");

// Set your desired admin credentials here
$admin_user = "admin"; 
$raw_password = "admin123"; // Change this to whatever you want your admin password to be
$hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
$role = "admin";
$status = "Active";

echo "<h2>Database Seeder</h2>";

// Check if the admin account already exists
$check = $conn->prepare("SELECT id FROM login WHERE un = ? AND role = 'admin'");
$check->bind_param("s", $admin_user);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // If it exists, update the password to the hashed version
    $update = $conn->prepare("UPDATE login SET pw = ?, status = 'Active', failed_attempts = 0 WHERE un = ? AND role = 'admin'");
    $update->bind_param("ss", $hashed_password, $admin_user);
    if ($update->execute()) {
        echo "<p style='color: green;'>✅ Admin password successfully hashed and updated!</p>";
    }
} else {
    // If it doesn't exist, create a brand new admin account
    $insert = $conn->prepare("INSERT INTO login (un, pw, role, status) VALUES (?, ?, ?, ?)");
    $insert->bind_param("ssss", $admin_user, $hashed_password, $role, $status);
    if ($insert->execute()) {
        echo "<p style='color: green;'>✅ Admin account successfully created with a hashed password!</p>";
    }
}
?>
<br>
<a href="register.php">Go to Login Page</a>