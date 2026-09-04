<?php
session_start();
include 'db_connect.php';

if (!isset($_GET['ref']) || !isset($_SESSION['customer_id'])) {
    header("Location: payment.php");
    exit();
}

$ref_number = $_GET['ref'];
$customer_id = $_SESSION['customer_id'];

// Get booking details for the receipt
$stmt = $conn->prepare("SELECT * FROM booking_online WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: payment.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Mang TV Laundry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 20px;
            }
        }
        .receipt-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="text-center mb-4">
            <h2>Mang TV Laundry Shop</h2>
            <p>Payment Receipt</p>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Customer Details:</h5>
                <p><strong>Customer ID:</strong> <?php echo htmlspecialchars($booking['customer_id']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($booking['contact_number']); ?></p>
                <p><strong>Dropoff Schedule:</strong> <?php echo date('F j, Y', strtotime($booking['dropoff_date'])); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <p><strong>Reference Number:</strong> <?php echo htmlspecialchars($ref_number); ?></p>
                <p><strong>Date:</strong> <?php echo date('F j, Y h:i A'); ?></p>
                <p><strong>Payment Method:</strong> Cash</p>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Schedule</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['service']); ?></td>
                        <td><?php echo date('F j, Y', strtotime($booking['dropoff_date'])); ?></td>
                        <td class="text-end">₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                    </tr>
                    <?php if (!empty($booking['addons'])): ?>
                    <tr>
                        <td colspan="2">Add-ons: <?php echo htmlspecialchars($booking['addons']); ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($booking['discount'] > 0): ?>
                    <tr>
                        <td colspan="2" class="text-end">Original Amount:</td>
                        <td class="text-end">₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                    </tr>
                    <tr class="text-success">
                        <td colspan="2" class="text-end">
                            <i class="fas fa-piggy-bank"></i> Savings/Discount:
                        </td>
                        <td class="text-end">-₱<?php echo number_format($booking['discount'], 2); ?></td>
                    </tr>
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="text-end">Final Amount:</td>
                        <td class="text-end">₱<?php echo number_format($booking['total_amount'] - $booking['discount'], 2); ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th colspan="2" class="text-end">Total Amount:</th>
                        <th class="text-end">₱<?php echo number_format($booking['total_amount'], 2); ?></th>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-4 text-center">
            <p>Please present this receipt when making payment at our shop.</p>
            <p><strong>Reference Number: <?php echo htmlspecialchars($ref_number); ?></strong></p>
        </div>
    </div>

    <?php if (isset($_GET['download']) && $_GET['download'] === 'true'): ?>
    <script>
        window.print();
    </script>
    <?php endif; ?>
</body>
</html>
