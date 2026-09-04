<?php
include 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid data"]);
    exit;
}

$claimNumber = $data['claimNumber'];
$name = $data['customer']['name'];
$contact = $data['customer']['contact'];
$address = $data['customer']['address'];
$total = $data['total'];
$status = $data['status'];
$date = $data['date'];
$time = $data['time'];
$delivery = $data['delivery'];

$stmt = $conn->prepare("INSERT INTO orders (claim_number, customer_name, contact, address, total, status, delivery, order_date, order_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssissss", $claimNumber, $name, $contact, $address, $total, $status, $delivery, $date, $time);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
