<?php
include 'db_connect.php';
include_once 'user_notif_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback_id = intval($_POST['feedback_id']);
    $response = trim($_POST['response']);

    if ($feedback_id && !empty($response)) {
        $stmt = $conn->prepare("UPDATE feedback SET admin_response = ?, responded_at = NOW() WHERE feedback_id = ?");
        $stmt->bind_param("si", $response, $feedback_id);

        if ($stmt->execute()) {
            // ✅ Kunin ang customer_id ng feedback at i-notify
            $fRow = $conn->query("SELECT customer_id FROM feedback WHERE feedback_id = $feedback_id LIMIT 1")->fetch_assoc();
            if ($fRow && !empty($fRow['customer_id'])) {
                notifyUserFeedbackResponse($conn, $feedback_id, (int)$fRow['customer_id']);
            }
            echo "success";
        } else {
            echo "error";
        }

        $stmt->close();
    } else {
        echo "error";
    }
} else {
    echo "error";
}
?>