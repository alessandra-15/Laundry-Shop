<?php
header('Content-Type: application/json');
include 'db_connect.php';

$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($item_id > 0) {
    $result = $conn->query("SELECT * FROM inventory_items WHERE item_id = $item_id");
    if ($result && $result->num_rows > 0) {
        echo json_encode(['success' => true, 'item' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
}
$conn->close();
?>