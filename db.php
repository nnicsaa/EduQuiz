<?php
$host = "sql305.infinityfree.com"; 
$user = "if0_43356618";           
$pass = "Bj5XnIx0heW4wD2";     
$dbname = "if0_43356618_eduquiz";  
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>