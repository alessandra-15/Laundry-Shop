<?php
// delete_record.php
// Deletes transaction + tracking + schedule. Accepts POST (recommended) or GET with confirm=1 for backward compatibility.
// Returns JSON for AJAX (X-Requested-With) or redirects for normal POST.

include 'db_connect.php';
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES); }

$method = $_SERVER['REQUEST_METHOD'];
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Accept POST or GET with confirm=1 for compatibility
if ($method === 'POST' || ($method === 'GET' && isset($_GET['confirm']) && $_GET['confirm'] === '1')) {
    // get id (POST preferred)
    $transactionId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
    if ($transactionId <= 0) {
        if ($isAjax) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'Invalid transaction id.']);
        } else {
            header('Location: digital_record.php?error=invalid_id');
        }
        exit;
    }

    try {
        $conn->begin_transaction();

        // fetch Schedule_ID
        $sql = "SELECT Schedule_ID FROM `transaction` WHERE Transaction_ID = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $conn->rollback();
            if ($isAjax) {
                header('Content-Type: application/json', true, 404);
                echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
            } else {
                header('Location: digital_record.php?error=notfound');
            }
            exit;
        }

        $scheduleId = (int)$row['Schedule_ID'];

        // delete tracking
        $sql = "DELETE FROM tracking WHERE Schedule_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $scheduleId);
        $stmt->execute();
        $stmt->close();

        // delete transaction
        $sql = "DELETE FROM `transaction` WHERE Transaction_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $stmt->close();

        // delete schedule (optional)
        $sql = "DELETE FROM schedule WHERE Schedule_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $scheduleId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Transaction deleted.']);
        } else {
            header('Location: digital_record.php?deleted=1');
        }
        exit;
    } catch (Exception $ex) {
        $conn->rollback();
        error_log('Delete error: ' . $ex->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => 'Error deleting record.']);
        } else {
            header('Location: digital_record.php?error=delete_failed');
        }
        exit;
    } finally {
        $conn->close();
    }
}

// If request is GET without confirm, redirect back (don't delete)
header('Location: digital_record.php');
exit;