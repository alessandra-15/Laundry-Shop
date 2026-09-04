<?php
// user_notif_helpers.php
// Dedicated helper para sa notifications_user table.
// booking_id ay laging NULL — FK constraint sa booking table ay dapat
// i-drop na muna sa phpMyAdmin:
//   ALTER TABLE notifications_user DROP FOREIGN KEY notifications_user_ibfk_1;

/**
 * Base function — mag-INSERT ng notification sa notifications_user.
 */
function sendUserNotification($conn, $user_id, $message, $status = 'sent', $booking_id = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications_user (booking_id, user_id, message, status, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmt) return false;

    $stmt->bind_param("iiss", $booking_id, $user_id, $message, $status);
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return ($ok && $id > 0) ? $id : false;
}

/**
 * Kapag nag-Approve o Reject ng schedule ang admin.
 */
function notifyUserScheduleAction($conn, $schedule_id, $action) {
    $stmt = $conn->prepare("
        SELECT s.Customer_ID, s.date, s.time, s.service
        FROM schedule s
        JOIN customer_info c ON s.Customer_ID = c.Customer_ID
        WHERE s.Schedule_ID = ?
        LIMIT 1
    ");
    if (!$stmt) return false;

    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return false;

    $user_id    = (int)$row['Customer_ID'];
    $service    = $row['service'];
    $sched_date = date('M j, Y', strtotime($row['date']));
    $sched_time = date('h:i A', strtotime($row['time']));

    if ($action === 'Approved') {
        $message = "Good news! Your booking for {$service} on {$sched_date} at {$sched_time} has been approved and is now scheduled. Please have your laundry ready on your scheduled date.";
    } else {
        $message = "We're sorry, your booking for {$service} on {$sched_date} at {$sched_time} has been rejected. Please contact us or rebook at a different schedule.";
    }

    return sendUserNotification($conn, $user_id, $message, 'sent', $schedule_id);
}

/**
 * Kapag nag-update ng laundry status ang admin (sa edit_record.php).
 */
function notifyUserLaundryStatus($conn, $transaction_id, $user_id, $laundry_status, $service = '', $booking_id = null) {
    $label = $service ? " ({$service})" : "";
    $statusMessages = [
        'PickedUp'   => "Your laundry{$label} (Transaction #{$transaction_id}) has been picked up by our team and is now on its way to our shop.",
        'Processing' => "Your laundry{$label} (Transaction #{$transaction_id}) is now being washed and processed. We'll let you know once it's done!",
        'Ready'      => "Your laundry{$label} (Transaction #{$transaction_id}) is done and ready! We will deliver it back to you shortly.",
        'Completed'  => "Your freshly laundered clothes{$label} (Transaction #{$transaction_id}) have been delivered back to you. Thank you for choosing MangTV Laundry!",
    ];

    if (!isset($statusMessages[$laundry_status])) return false;

    return sendUserNotification($conn, $user_id, $statusMessages[$laundry_status], 'sent', $booking_id);
}

/**
 * Kapag nag-mark ng Paid ang admin.
 */
function notifyUserPaymentConfirmed($conn, $transaction_id, $amount, $booking_id = null) {
    $stmt = $conn->prepare("
        SELECT t.Customer_ID, s.service
        FROM `transaction` t
        LEFT JOIN schedule s ON t.Schedule_ID = s.Schedule_ID
        WHERE t.Transaction_ID = ?
        LIMIT 1
    ");
    if (!$stmt) return false;

    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return false;

    $user_id   = (int)$row['Customer_ID'];
    $service   = $row['service'] ?? 'laundry service';
    $formatted = '₱' . number_format((float)$amount, 2);
    $message   = "Your payment of {$formatted} for {$service} (Transaction #{$transaction_id}) has been confirmed. Thank you!";

    return sendUserNotification($conn, $user_id, $message, 'sent', $booking_id);
}

/**
 * Kapag nag-update ng complaint status ang admin.
 */
function notifyUserComplaintUpdate($conn, $complaint_id, $customer_id, $new_status, $remarks = '', $booking_id = null) {
    if ($new_status === 'Resolved') {
        $message = "Good news! Your complaint #{$complaint_id} has been resolved.";
        if (!empty($remarks)) {
            $message .= " Admin note: {$remarks}";
        }
    } elseif ($new_status === 'In Progress') {
        $message = "Your complaint #{$complaint_id} is now being reviewed by our team. We'll update you shortly.";
    } else {
        return false;
    }

    return sendUserNotification($conn, $customer_id, $message, 'sent', $booking_id);
}

/**
 * Kapag nag-respond ang admin sa feedback ng user.
 */
function notifyUserFeedbackResponse($conn, $feedback_id, $customer_id, $booking_id = null) {
    $message = "The admin has responded to your feedback #{$feedback_id}. You can now view the response in your profile.";
    return sendUserNotification($conn, $customer_id, $message, 'sent', $booking_id);
}
?>