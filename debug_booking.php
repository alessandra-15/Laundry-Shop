<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    die("Not logged in");
}

$customerId = (int)$_SESSION['customer_id'];

echo "<h1>Debug Booking Status</h1>";
echo "<p>Current User Customer ID: " . $customerId . "</p>";

// Check all bookings for this customer
echo "<h2>Your Bookings:</h2>";
$stmt = $conn->prepare("SELECT id, customer_id, status, payment_status, total_amount, completed_at, timestamp FROM booking_online WHERE customer_id = ? ORDER BY id DESC LIMIT 10");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Customer ID</th><th>Status</th><th>Payment Status</th><th>Amount</th><th>Completed At</th><th>Timestamp</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['customer_id'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['payment_status'] . "</td>";
    echo "<td>" . $row['total_amount'] . "</td>";
    echo "<td>" . ($row['completed_at'] ?? 'N/A') . "</td>";
    echo "<td>" . $row['timestamp'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$stmt->close();

// Count active and completed
$active_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM booking_online WHERE customer_id = ? AND status = 'Active'");
$active_stmt->bind_param("i", $customerId);
$active_stmt->execute();
$active_result = $active_stmt->get_result();
$active_row = $active_result->fetch_assoc();
$activeLaundry = $active_row['cnt'];
$active_stmt->close();

$completed_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM booking_online WHERE customer_id = ? AND completed_at IS NOT NULL");
$completed_stmt->bind_param("i", $customerId);
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();
$completed_row = $completed_result->fetch_assoc();
$completedLaundry = $completed_row['cnt'];
$completed_stmt->close();

echo "<h2>Counts:</h2>";
echo "<p><strong>Active Laundry:</strong> " . $activeLaundry . "</p>";
echo "<p><strong>Completed Laundry:</strong> " . $completedLaundry . "</p>";

// Check database structure
echo "<h2>Database Structure:</h2>";
$columns = $conn->query("DESCRIBE booking_online");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($col = $columns->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . $col['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
