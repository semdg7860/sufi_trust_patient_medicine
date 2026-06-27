<?php
$host = "localhost";
$db_user = "root";      // Apne database ka username likhein
$db_pass = "";          // Apne database ka password likhein
$db_name = "trust"; // Apne database ka naam likhein

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

// Connection check karein
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>