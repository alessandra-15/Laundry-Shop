<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['Customer_ID'];
    $schedule_date = $_POST['schedule_date'];
    $schedule_time = $_POST['schedule_time'];
    $service = $_POST['service'];
    $add_ons = $_POST['add_ons'];
    $pick_deliver = $_POST['pick_deliver'];
    $special_instructions = $_POST['special_instructions'];
    $status = $_POST['status'];
    $total_amount = $_POST['total_amount'];

    // 1️⃣ Insert to schedule first
    $sql_schedule = "INSERT INTO schedule (Customer_ID, date, time, pick_deliver, service, add_ons, admin_confirmation)
                     VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt1 = $conn->prepare($sql_schedule);
    $stmt1->bind_param("isssss", $customer_id, $schedule_date, $schedule_time, $pick_deliver, $service, $add_ons);

    if ($stmt1->execute()) {
        // Get the newly inserted schedule ID
        $schedule_id = $stmt1->insert_id;

        // 2️⃣ Then insert to booking table
        // ✅ Fixed: used existing columns from your actual booking table
        $sql_booking = "INSERT INTO booking (Customer_ID, Schedule_ID, service, add_ons, pick_deliver, special_instructions, status, total_amount, booking_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        // ✅ Fixed: updated bind_param to match table columns
        $stmt2 = $conn->prepare($sql_booking);
        $stmt2->bind_param(
            "iisssssd",
            $customer_id,
            $schedule_id,
            $service,
            $add_ons,
            $pick_deliver,
            $special_instructions,
            $status,
            $total_amount
        );

        if ($stmt2->execute()) {
            echo "success";
        } else {
            echo "Error saving booking: " . $stmt2->error;
        }

        $stmt2->close();
    } else {
        echo "Error saving schedule: " . $stmt1->error;
    }

    $stmt1->close();
    $conn->close();
}
?>