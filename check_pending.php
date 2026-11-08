<?php
session_start();
include "db.php";

// Only allow admins to check this
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    exit();
}

$pending_query = $conn->query("SELECT COUNT(*) as total FROM login WHERE role = 'student' AND status = 'Pending'");
$pendingCount = $pending_query->fetch_assoc()['total'];

// Output the count in JSON format so JavaScript can easily read it
echo json_encode(['count' => $pendingCount]);
?>