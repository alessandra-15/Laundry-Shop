<?php
// edit_record.php — modal form fragment for edit (GET) and update handler (POST)
include 'db_connect.php';
include_once 'user_notif_helpers.php';

$transactionId = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
if ($transactionId <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid transaction ID.']);
        exit;
    }
    echo '<div class="alert alert-warning">Invalid transaction ID.</div>';
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }

$sql = "SELECT t.Transaction_ID, t.Schedule_ID, t.Customer_ID, t.laundry_weight, t.payment, t.payment_status,
               s.date, s.time, s.service, s.add_ons, s.pick_deliver,
               tr.laundry_status,
               CONCAT(c.first_name,' ',c.last_name) AS customer_name
        FROM transaction t
        JOIN schedule s ON t.Schedule_ID = s.Schedule_ID
        LEFT JOIN tracking tr ON t.Schedule_ID = tr.Schedule_ID
        LEFT JOIN customer_info c ON t.Customer_ID = c.Customer_ID
        WHERE t.Transaction_ID = ?
        LIMIT 1";

$row = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'DB error.']);
        exit;
    }
    echo '<div class="alert alert-danger">Database error.</div>';
    exit;
}

if (!$row) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
        exit;
    }
    echo '<div class="alert alert-info">Record not found.</div>';
    exit;
}

// POST: process update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture old values BEFORE overwriting with POST data
    $oldLaundryStatus  = trim($row['laundry_status'] ?? 'Scheduled');
    $oldPaymentStatus  = trim($row['payment_status'] ?? '');

    $date = $_POST['date'] ?? $row['date'];
    $time = $_POST['time'] ?? $row['time'];
    $service = trim($_POST['service'] ?? $row['service']);
    $add_ons = trim($_POST['add_ons'] ?? $row['add_ons']);
    $pick_deliver = trim($_POST['pick_deliver'] ?? $row['pick_deliver']);
    $laundry_weight = isset($_POST['laundry_weight']) ? floatval($_POST['laundry_weight']) : $row['laundry_weight'];
    $payment = isset($_POST['payment']) ? floatval($_POST['payment']) : $row['payment'];
    $payment_status = trim($_POST['payment_status'] ?? $row['payment_status']);
    $laundry_status = isset($_POST['laundry_status']) ? trim($_POST['laundry_status']) : ($row['laundry_status'] ?? 'Scheduled');

    $errors = [];
    if ($service === '') $errors[] = 'Service cannot be empty.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'Invalid date format.';
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) $errors[] = 'Invalid time format.';

    $allowedStatuses = ['Scheduled','PickedUp','Processing','Ready','Completed'];
    if (!in_array($laundry_status, $allowedStatuses, true)) {
        $errors[] = 'Invalid laundry status.';
    }

    if (!empty($errors)) {
        header('HTTP/1.1 422 Unprocessable Entity');
        echo '<div class="alert alert-danger"><ul>';
        foreach ($errors as $e) echo '<li>' . h($e) . '</li>';
        echo '</ul></div>';
        exit;
    }

    $conn->begin_transaction();
    try {
        $scheduleId = $row['Schedule_ID'];

        $sqlUpdateSchedule = "UPDATE schedule SET date = ?, time = ?, service = ?, add_ons = ?, pick_deliver = ? WHERE Schedule_ID = ?";
        if ($stmt = $conn->prepare($sqlUpdateSchedule)) {
            $stmt->bind_param('sssssi', $date, $time, $service, $add_ons, $pick_deliver, $scheduleId);
            $stmt->execute();
            if ($stmt->errno) throw new Exception($stmt->error);
            $stmt->close();
        } else throw new Exception('Prepare failed (schedule).');

       $sqlUpdateTransaction = "UPDATE transaction SET laundry_weight = ?, payment = ?, payment_status = ?, transaction_date = COALESCE(transaction_date, NOW()) WHERE Transaction_ID = ?";
if ($stmt = $conn->prepare($sqlUpdateTransaction)) {
    $stmt->bind_param('ddsi', $laundry_weight, $payment, $payment_status, $transactionId);
    $stmt->execute();
    if ($stmt->errno) throw new Exception($stmt->error);
    $stmt->close();
} else throw new Exception('Prepare failed (transaction).');

        $sqlCheckTracking = "SELECT tracking_id FROM tracking WHERE Schedule_ID = ? LIMIT 1";
        $trackingExists = false;
        if ($stmt = $conn->prepare($sqlCheckTracking)) {
            $stmt->bind_param('i', $scheduleId);
            $stmt->execute();
            $res = $stmt->get_result();
            $trackingExists = $res->num_rows > 0;
            $stmt->close();
        } else throw new Exception('Prepare failed (check tracking).');

        if ($trackingExists) {
    $sqlUpdateTracking = "UPDATE tracking SET 
        laundry_status = ?,
        tracking_time = TIME(NOW()),
        tracking_date = CURDATE(),
        Scheduled_time  = CASE WHEN ? = 'Scheduled'  THEN TIME(NOW()) ELSE Scheduled_time  END,
        Pickup_time     = CASE WHEN ? = 'PickedUp'   THEN TIME(NOW()) ELSE Pickup_time     END,
        Processing_time = CASE WHEN ? = 'Processing' THEN TIME(NOW()) ELSE Processing_time END,
        Ready_time      = CASE WHEN ? = 'Ready'      THEN TIME(NOW()) ELSE Ready_time      END,
        Completed_time  = CASE WHEN ? = 'Completed'  THEN TIME(NOW()) ELSE Completed_time  END
        WHERE Schedule_ID = ?";
    if ($stmt = $conn->prepare($sqlUpdateTracking)) {
        $stmt->bind_param('ssssssi',
            $laundry_status,
            $laundry_status,
            $laundry_status,
            $laundry_status,
            $laundry_status,
            $laundry_status,
            $scheduleId
        );
        $stmt->execute();
        if ($stmt->errno) throw new Exception($stmt->error);
        $stmt->close();
    } else throw new Exception('Prepare failed (update tracking).');
} else {
    $sqlInsertTracking = "INSERT INTO tracking 
        (Schedule_ID, Customer_ID, laundry_status, tracking_time, tracking_date,
        Scheduled_time, Pickup_time, Processing_time, Ready_time, Completed_time)
        VALUES (?, ?, ?, TIME(NOW()), CURDATE(),
        CASE WHEN ? = 'Scheduled'  THEN TIME(NOW()) ELSE NULL END,
        CASE WHEN ? = 'PickedUp'   THEN TIME(NOW()) ELSE NULL END,
        CASE WHEN ? = 'Processing' THEN TIME(NOW()) ELSE NULL END,
        CASE WHEN ? = 'Ready'      THEN TIME(NOW()) ELSE NULL END,
        CASE WHEN ? = 'Completed'  THEN TIME(NOW()) ELSE NULL END)";
    if ($stmt = $conn->prepare($sqlInsertTracking)) {
        $customerId = $row['Customer_ID'] ?? null;
        $stmt->bind_param('iissssss',
            $scheduleId,      // ? #1 Schedule_ID
            $customerId,      // ? #2 Customer_ID
            $laundry_status,  // ? #3 laundry_status
            $laundry_status,  // ? #4 CASE Scheduled
            $laundry_status,  // ? #5 CASE PickedUp
            $laundry_status,  // ? #6 CASE Processing
            $laundry_status,  // ? #7 CASE Ready
            $laundry_status   // ? #8 CASE Completed
        );
        $stmt->execute();
        if ($stmt->errno) throw new Exception($stmt->error);
        $stmt->close();
    } else throw new Exception('Prepare failed (insert tracking).');
}

        // Sync booking_online status kapag Completed ang laundry_status
        $statusMap = [
    'Scheduled'  => 'Pending',
    'PickedUp'   => 'Processing',
    'Processing' => 'Processing',
    'Ready'      => 'Ready',
    'Completed'  => 'Completed',
];
$laundry_status_clean = trim($laundry_status);
$onlineStatus = $statusMap[$laundry_status_clean] ?? 'Pending';

// Debug log — tanggalin mo ito pagkatapos ma-fix
error_log("laundry_status=[$laundry_status_clean] onlineStatus=[$onlineStatus] scheduleId=[$scheduleId]");

if (!empty($onlineStatus)) {
    $sqlSyncOnline = "UPDATE booking_online 
        SET status = ?,
            completed_at = CASE 
                WHEN ? = 'Completed' AND completed_at IS NULL 
                THEN NOW() 
                ELSE completed_at 
            END
        WHERE id = (
            SELECT bo_id FROM (
                SELECT id AS bo_id FROM booking_online 
                WHERE schedule_id = ? 
                ORDER BY id DESC 
                LIMIT 1
            ) AS tmp
        )";

    if ($stmt = $conn->prepare($sqlSyncOnline)) {
        $stmt->bind_param('ssi', $onlineStatus, $onlineStatus, $scheduleId);
        $stmt->execute();
        $stmt->close();
    }
}

        $conn->commit();

        // ✅ SEND USER NOTIFICATIONS after successful save
        $userId = (int)($row['Customer_ID'] ?? 0);
        $txId   = $transactionId;
        $schedId = (int)($row['Schedule_ID'] ?? 0);

        // 1. Laundry status — notify only when status actually changed
        if ($laundry_status !== $oldLaundryStatus && $userId > 0) {
            notifyUserLaundryStatus($conn, $txId, $userId, $laundry_status, trim($row['service'] ?? ''), $schedId);
        }

        // 2. Payment — notify only when changed TO Paid
        if (strtolower($payment_status) === 'paid' && strtolower($oldPaymentStatus) !== 'paid' && $userId > 0) {
            notifyUserPaymentConfirmed($conn, $txId, $payment, $schedId);
        }

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Record updated.']);
            exit;
        } else {
            echo '<div class="alert alert-success">Record updated successfully. <a href="digital_record.php">Back to records</a></div>';
            exit;
        }
    } catch (Exception $ex) {
    $conn->rollback();
    error_log('Edit error: ' . $ex->getMessage());
    if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
    // Ipakita ang exact error para malaman natin kung ano talaga ang problema
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($ex->getMessage()) . '</div>';
    exit;
}
}

$currentStatus = $row['laundry_status'] ?? 'Scheduled';
$statuses = ['Scheduled','PickedUp','Processing','Ready','Completed'];
?>
<style>
:root {
    --dark-blue: #00537A;
    --yellow: #FFD35B;
    --light-blue: #A8E8F9;
}

.edit-record-container {
    font-family: 'Poppins', sans-serif;
}

.edit-record-header {
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
    padding: 1.5rem;
    border-radius: 16px 16px 0 0;
    border-bottom: 3px solid var(--yellow);
    margin: -1rem -1rem 1.5rem -1rem;
}

.edit-record-header h6 {
    color: var(--dark-blue);
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
}

.edit-record-header small {
    color: #6c757d;
    font-size: 0.9rem;
}

.current-status-badge {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.badge-completed { color: #0f5132; }
.badge-processing { color: #084298; }
.badge-ready { color: #664d03; }
.badge-pickedup { color: #004085; }
.badge-scheduled { color: #842029; }

.form-label {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control, .form-select {
    border: 2px solid rgba(168,232,249,0.5);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.form-control:focus, .form-select:focus {
    border-color: var(--yellow);
    box-shadow: 0 0 0 0.2rem rgba(255,213,91,0.25);
    outline: none;
}

.form-section {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid rgba(168,232,249,0.2);
    margin-bottom: 1rem;
}

.form-section-title {
    color: var(--dark-blue);
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--light-blue);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-section-title i {
    color: var(--yellow);
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-save {
    background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,83,122,0.3);
}

.modal-footer-custom {
    padding-top: 1.5rem;
    border-top: 2px solid rgba(168,232,249,0.3);
    margin-top: 1.5rem;
}
</style>

<div class="edit-record-container">
    <div class="edit-record-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6>Edit Transaction #<?= h($row['Transaction_ID']) ?></h6>
                <small>Customer: <?= h($row['customer_name'] ?? '') ?></small>
            </div>
            <div class="current-status-badge">
                <span style="font-size: 0.75rem; font-weight: 600; color: #6c757d;">Current Status:</span>
                <span class="badge-<?= $currentStatus === 'Completed' ? 'completed' : ($currentStatus === 'Processing' ? 'processing' : ($currentStatus === 'Ready' ? 'ready' : ($currentStatus === 'PickedUp' ? 'pickedup' : 'scheduled'))) ?>" style="font-weight: 700;">
                    <?= h($currentStatus) ?>
                </span>
            </div>
        </div>
    </div>

    <form method="post" action="edit_record.php?id=<?= $transactionId ?>" novalidate>
        <input type="hidden" name="id" value="<?= $transactionId ?>">

        <!-- Schedule Information -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-calendar-alt"></i>
                Schedule Information
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= h($row['date']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Time</label>
                    <input type="time" name="time" class="form-control" value="<?= substr(h($row['time']),0,5) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Service</label>
                    <input type="text" name="service" class="form-control" value="<?= h($row['service']) ?>" required placeholder="e.g., Wash & Fold">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Type (Pick / Deliver)</label>
                    <select name="pick_deliver" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="Pickup" <?= $row['pick_deliver'] === 'Pickup' ? 'selected' : '' ?>>Pickup</option>
                        <option value="Delivery" <?= $row['pick_deliver'] === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                        <option value="Pickup & Delivery" <?= $row['pick_deliver'] === 'Pickup & Delivery' ? 'selected' : '' ?>>Pickup & Delivery</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Add-ons (Optional)</label>
                    <input type="text" name="add_ons" class="form-control" value="<?= h($row['add_ons']) ?>" placeholder="e.g., Extra Dry, Fabric Conditioner">
                </div>
            </div>
        </div>

        <!-- Transaction Details -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-file-invoice"></i>
                Transaction Details
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Laundry Weight (kg)</label>
                    <input type="number" step="0.1" min="0" name="laundry_weight" class="form-control" value="<?= h($row['laundry_weight']) ?>" placeholder="0.0">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Payment Amount (₱)</label>
                    <input type="number" step="0.01" min="0" name="payment" class="form-control" value="<?= h($row['payment']) ?>" placeholder="0.00">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select">
                        <option value="Paid" <?= strtolower($row['payment_status']) === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="Unpaid" <?= strtolower($row['payment_status']) === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="Partial" <?= strtolower($row['payment_status']) === 'partial' ? 'selected' : '' ?>>Partial</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Laundry Status</label>
                    <select name="laundry_status" class="form-select">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= h($s) ?>" <?= $currentStatus === $s ? 'selected' : '' ?>>
                                <?= h($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="modal-footer-custom">
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<?php $conn->close(); ?>