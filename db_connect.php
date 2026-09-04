<?php
$servername = "localhost";
$username = "root";  // Default for XAMPP
$password = "";      // Leave blank if using default XAMPP settings
$database = "laundry_db";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
