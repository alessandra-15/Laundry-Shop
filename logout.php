<?php
session_start();
include 'db_connect.php';

// If activity_id is stored in session, update that specific row
$activityId = $_SESSION['activity_id'] ?? null;
$customerId = $_SESSION['customer_id'] ?? null;

if ($activityId) {
    if ($stmt = $conn->prepare("UPDATE user_activity SET logout_time = NOW(), status = 'Offline' WHERE id = ?")) {
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("logout: failed to prepare update by id: " . $conn->error);
    }
} elseif ($customerId) {
    // Best-effort: update the most recent activity row for this customer that has no logout_time
    $sql = "UPDATE user_activity SET logout_time = NOW(), status = 'Offline' WHERE customer_id = ? AND (logout_time IS NULL OR logout_time = '') ORDER BY id DESC LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $stmt->close();
    } else {
        // Last-resort: update any row without logout_time (may affect multiple rows) - only if prepare with ORDER/LIMIT fails
        if ($stmt2 = $conn->prepare("UPDATE user_activity SET logout_time = NOW(), status = 'Offline' WHERE customer_id = ? AND (logout_time IS NULL OR logout_time = '')")) {
            $stmt2->bind_param('i', $customerId);
            $stmt2->execute();
            $stmt2->close();
        } else {
            error_log("logout: failed to update user_activity for customer_id: " . $conn->error);
        }
    }
}

// Clear session
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
