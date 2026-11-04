<?php
session_start();
session_unset();  
session_destroy(); 

//back to login
header("Location: register.php"); 
exit();
?>