<?php
/**
 * Notification Check and Creation Script
 * 
 * This script checks for upcoming appointments (within 1 hour) and creates notifications.
 * Should be called periodically (via cron job or AJAX) or on page load.
 * 
 * Requirements:
 * - Appointment status = 'confirmed' (booked appointments)
 * - Appointment time is <= 1 hour from current time
 * - Asia/Kathmandu timezone
 */

session_start();
include 'connection.php';

// Set timezone to Asia/Kathmandu
date_default_timezone_set('Asia/Kathmandu');

// Only run if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';

// Get current date and time in Asia/Kathmandu
$current_datetime = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
$current_date = $current_datetime->format('Y-m-d');
$current_time = $current_datetime->format('H:i:s');
$current_timestamp = $current_datetime->getTimestamp();

// Calculate 1 hour from now
$one_hour_later = clone $current_datetime;
$one_hour_later->modify('+1 hour');
$one_hour_later_time = $one_hour_later->format('H:i:s');

// Find appointments that:
// 1. Belong to this user
// 2. Status is 'confirmed' (booked)
// 3. Appointment date is today
// 4. Appointment time is between now and 1 hour from now
// 5. Don't already have a notification created

$query = "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status
    FROM appointments a
    LEFT JOIN notifications n 
        ON n.appointment_id = a.id 
        AND n.user_id = ?
        AND n.message LIKE '%reminder%'
    WHERE a.user_id = ?
    AND a.status = 'confirmed'
    AND a.appointment_date = ?
    AND a.appointment_time >= ?
    AND a.appointment_time <= ?
    AND n.id IS NULL
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iisss", $user_id, $user_id, $current_date, $current_time, $one_hour_later_time);
$stmt->execute();
$result = $stmt->get_result();

$notifications_created = 0;

while ($appointment = $result->fetch_assoc()) {
    // Calculate remaining minutes
    $appointment_datetime = new DateTime(
        $appointment['appointment_date'] . ' ' . $appointment['appointment_time'],
        new DateTimeZone('Asia/Kathmandu')
    );
    $appointment_timestamp = $appointment_datetime->getTimestamp();
    $remaining_seconds = $appointment_timestamp - $current_timestamp;
    $remaining_minutes = max(0, floor($remaining_seconds / 60));
    
    // Create notification message
    $message = "Hello " . htmlspecialchars($full_name) . ", this is a reminder that your appointment is in " . $remaining_minutes . " minutes. Please be ready.";
    
    // Insert notification
    $insert_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, appointment_id, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $insert_stmt->bind_param("isi", $user_id, $message, $appointment['id']);
    $insert_stmt->execute();
    $insert_stmt->close();
    
    $notifications_created++;
}

$stmt->close();

// Return success (can be used for AJAX calls)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notifications_created' => $notifications_created
    ]);
} else {
    // If called directly, just return silently
    return $notifications_created;
}

$conn->close();
?>

