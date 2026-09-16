<?php
/**
 * Get Notifications API
 * 
 * Returns all notifications for the logged-in user
 * Returns JSON format
 */

session_start();
include 'connection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if notifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Notifications table does not exist. Please run create_notifications_table.sql',
        'notifications' => [],
        'unread_count' => 0,
        'total_count' => 0
    ]);
    exit();
}

try {
    // Get all notifications for this user, ordered by newest first
    $query = "
        SELECT id, message, is_read, appointment_id, created_at, read_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'message' => $row['message'],
            'is_read' => (bool)$row['is_read'],
            'appointment_id' => $row['appointment_id'] ? (int)$row['appointment_id'] : null,
            'created_at' => $row['created_at'],
            'read_at' => $row['read_at']
        ];
    }

    $stmt->close();

    // Count unread notifications
    $unread_count = 0;
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) {
            $unread_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'total_count' => count($notifications)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'notifications' => [],
        'unread_count' => 0,
        'total_count' => 0
    ]);
}

$conn->close();
?>