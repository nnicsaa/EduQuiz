<?php
$host = "sql305.infinityfree.com"; // Replace with your exact MySQL Hostname
$user = "if0_43356618";             // Replace with your exact MySQL Username
$pass = "Bj5XnIx0heW4wD2";     // Replace with your exact MySQL Password
$dbname = "if0_43356618_eduquiz";   // Replace with your exact Database Name

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>