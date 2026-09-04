<?php
header('Content-Type: application/json');
include 'db_connect.php';

$po_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($po_id > 0) {
    $poResult = $conn->query("SELECT * FROM purchase_orders WHERE po_id = $po_id");
    if ($poResult && $poResult->num_rows > 0) {
        $po = $poResult->fetch_assoc();
        $itemsResult = $conn->query("SELECT poi.*, i.item_name FROM purchase_order_items poi JOIN inventory_items i ON poi.item_id = i.item_id WHERE poi.po_id = $po_id");
        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }
        echo json_encode(['success' => true, 'po' => $po, 'items' => $items]);
    } else {
        echo json_encode(['success' => false, 'message' => 'PO not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
}
$conn->close();
?>