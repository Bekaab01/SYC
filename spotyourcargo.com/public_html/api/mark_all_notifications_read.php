<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
include_once '../../private/db.php';

if (!isset($_SESSION['user_syc_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$syc_id = $_SESSION['user_syc_id'];

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE syc_id = ? AND is_read = 0");
    $stmt->execute([$syc_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        echo json_encode(['success' => true, 'message' => 'No unread notifications to mark']);
    }
} catch (PDOException $e) {
    error_log("Mark all notifications read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
