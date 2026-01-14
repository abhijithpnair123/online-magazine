<?php
// db_connect.php

$servername = "localhost"; // Your database host
$username = "root";      // Your database username
$password = "";          // Your database password
$dbname = "online_mag";  // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->query("SET time_zone = '+05:30'");
// Optional: Set character set to utf8mb4
$conn->set_charset("utf8mb4");
?>