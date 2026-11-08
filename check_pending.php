<?php
session_start();
include "db.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    exit();
}

// 1. Get the total count
$pending_query = $conn->query("SELECT COUNT(*) as total FROM login WHERE role = 'student' AND status = 'Pending'");
$pendingCount = $pending_query->fetch_assoc()['total'];

// 2. Build the HTML for the table if there are pending students
$html = "";
if ($pendingCount > 0) {
    $html .= "<h2 style='color: #f59f00;'>Pending Approvals</h2>";
    $html .= "<table>";
    $html .= "<thead><tr><th>User</th><th>Section</th><th>Action</th></tr></thead>";
    $html .= "<tbody>";
    
    $students_query = $conn->query("SELECT id, un, sec FROM login WHERE role = 'student' AND status = 'Pending' ORDER BY id DESC");
    
    while($row = $students_query->fetch_assoc()) {
        $html .= "<tr>";
        $html .= "<td><strong>" . htmlspecialchars($row['un']) . "</strong></td>";
        $html .= "<td>" . htmlspecialchars($row['sec']) . "</td>";
        $html .= "<td>
            <div style='display: flex; gap: 10px;'>
                <form method='POST' style='margin:0;'>
                    <input type='hidden' name='user_id' value='" . $row['id'] . "'>
                    <button type='submit' name='approve_account' class='btn-submit' style='padding: 5px 10px; background: #2b8a3e;'>Approve</button>
                </form>
                <form method='POST' style='margin:0;' onsubmit=\"return confirm('Are you sure you want to reject and permanently delete this student account?');\">
                    <input type='hidden' name='user_id' value='" . $row['id'] . "'>
                    <button type='submit' name='reject_account' class='btn-submit' style='padding: 5px 10px; background: #c92a2a;'>Reject</button>
                </form>
            </div>
        </td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table>";
}

// 3. Send both the count and the HTML back to the dashboard
echo json_encode(['count' => $pendingCount, 'html' => $html]);
?>