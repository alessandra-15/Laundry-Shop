<?php
$host = "localhost";     // usually localhost kung XAMPP
$username = "root";      // default username sa XAMPP
$password = "";          // leave blank kung walang password
$database = "laundry_db"; // palitan ng pangalan ng database mo

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: display message for debugging
// echo "Connected successfully!";
?>
