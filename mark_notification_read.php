<?php
/**
 * Mark Notification as Read API
 * 
 * Marks a specific notification as read when user clicks on it
 */

session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get notification ID from POST or GET
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 
                   (isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0);

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid notification ID']);
    exit();
}

// Update notification - mark as read and set read_at timestamp
$query = "
    UPDATE notifications
    SET is_read = 1, read_at = NOW()
    WHERE id = ? AND user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'error' => 'Notification not found or already read'
    ]);
}

$stmt->close();
$conn->close();
?>

