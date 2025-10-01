<?php
date_default_timezone_set('Asia/Manila');
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "capstone_management";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>