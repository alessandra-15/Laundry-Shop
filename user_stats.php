<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user stats from admin API
$api_url = "http://localhost/laundry/api/user_stats.php?user_id=" . $user_id;
$stats = json_decode(file_get_contents($api_url), true);
?>

<!-- Add this where you want to display the stats in your user dashboard -->
<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="display-4"><?php echo $stats['completed_orders']; ?></h3>
                    <p class="text-muted mb-0">Completed Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="display-4"><?php echo $stats['active_orders']; ?></h3>
                    <p class="text-muted mb-0">Active Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="display-4">₱<?php echo number_format($stats['total_spent'], 2); ?></h3>
                    <p class="text-muted mb-0">Total Spent</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Refresh stats every 30 seconds
setInterval(function() {
    fetch('<?php echo $api_url; ?>')
        .then(response => response.json())
        .then(stats => {
            document.querySelectorAll('.display-4').forEach((el, index) => {
                if (index === 0) el.textContent = stats.completed_orders;
                if (index === 1) el.textContent = stats.active_orders;
                if (index === 2) el.textContent = '₱' + parseFloat(stats.total_spent).toFixed(2);
            });
        });
}, 30000);
</script>