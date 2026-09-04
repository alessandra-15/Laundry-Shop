<?php
include 'db_connect.php';

$result = $conn->query("
    SELECT 
        b.*, 
        c.Customer_Name, 
        c.Contact_Number, 
        c.Address,
        a.Admin_Name,
        s.Schedule_Date
    FROM booking b
    LEFT JOIN customer_info c ON b.Customer_ID = c.Customer_ID
    LEFT JOIN admin a ON b.Admin_ID = a.Admin_ID
    LEFT JOIN schedule s ON b.Schedule_ID = s.Schedule_ID
    ORDER BY b.Booking_ID DESC
");

$bookings = [];

while ($row = $result->fetch_assoc()) {
    $row["add_ons"] = json_decode($row["add_ons"], true);
    $bookings[] = $row;
}

echo json_encode($bookings);
$conn->close();
?>
