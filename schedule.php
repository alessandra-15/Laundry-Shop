<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    $scheduleID = intval($_POST['schedule_id']);
    $newStatus = $_POST['confirm'];

    $update = $conn->prepare("UPDATE schedule SET admin_confirmation = ? WHERE Schedule_ID = ?");
    $update->bind_param("si", $newStatus, $scheduleID);
    $update->execute();

    if ($update->affected_rows > 0) {
        echo "<script>alert('Schedule #{$scheduleID} has been {$newStatus}.'); window.location.href='schedule.php';</script>";
    } else {
        echo "<script>alert('Failed to update schedule.'); window.location.href='schedule.php';</script>";
    }
}
?>
