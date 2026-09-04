<?php
// view_record.php — returns an HTML fragment (not a full page) for modal injection
// Expects GET id=Transaction_ID

include 'db_connect.php';

$transactionId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($transactionId <= 0) {
    echo '<div class="alert alert-warning">Invalid transaction ID.</div>';
    exit;
}

$sql = "SELECT t.Transaction_ID,
               CONCAT(c.first_name,' ',c.last_name) AS customer_name,
               s.date, s.time, s.service, s.add_ons, s.pick_deliver,
               t.laundry_weight, t.payment, t.payment_status,
               tr.laundry_status
        FROM `transaction` t
        JOIN customer_info c ON t.Customer_ID = c.Customer_ID
        JOIN schedule s ON t.Schedule_ID = s.Schedule_ID
        LEFT JOIN tracking tr ON t.Schedule_ID = tr.Schedule_ID
        WHERE t.Transaction_ID = ?
        LIMIT 1";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
} else {
    echo '<div class="alert alert-danger">Database error.</div>';
    exit;
}

if (!$row) {
    echo '<div class="alert alert-info">Record not found.</div>';
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 mb-3">
      <h5 class="mb-0">Transaction <strong>#<?= h($row['Transaction_ID']) ?></strong></h5>
      <small class="text-muted">Customer: <?= h($row['customer_name']) ?></small>
    </div>

    <div class="col-md-6 mb-2">
      <div class="card card-body p-3">
        <h6 class="mb-2">Schedule</h6>
        <div><strong>Date:</strong> <?= h(date('M d, Y', strtotime($row['date']))) ?></div>
        <div><strong>Time:</strong> <?= h(date('h:i A', strtotime($row['time']))) ?></div>
        <div><strong>Type:</strong> <?= h($row['pick_deliver']) ?></div>
        <div><strong>Service:</strong> <?= h($row['service']) ?></div>
        <div><strong>Add-ons:</strong> <?= h($row['add_ons'] ?: 'None') ?></div>
      </div>
    </div>

    <div class="col-md-6 mb-2">
      <div class="card card-body p-3">
        <h6 class="mb-2">Transaction</h6>
        <div><strong>Weight:</strong> <?= h($row['laundry_weight']) ?> kg</div>
        <div><strong>Payment:</strong> ₱<?= number_format($row['payment'], 2) ?></div>
        <div><strong>Payment Status:</strong> <span class="badge-custom <?= strtolower($row['payment_status']) === 'paid' ? 'badge-paid' : 'badge-pending' ?>"><?= h($row['payment_status']) ?></span></div>
        <div class="mt-2"><strong>Laundry Status:</strong>
          <?php
            $status = $row['laundry_status'] ?? 'Waiting';
            $badgeClass = 'badge-waiting';
            if ($status === 'Completed') $badgeClass = 'badge-completed';
            elseif ($status === 'Processing') $badgeClass = 'badge-processing';
          ?>
          <span class="badge-custom <?= $badgeClass ?>"><?= h($status) ?></span>
        </div>
      </div>
    </div>

    <div class="col-12 mt-3">
      <div class="d-flex gap-2 justify-content-end">
        
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php $conn->close(); ?>