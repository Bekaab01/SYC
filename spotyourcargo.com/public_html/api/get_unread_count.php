<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
include_once '../../private/db.php';

if (!isset($_SESSION['user_syc_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$syc_id = $_SESSION['user_syc_id'];

try {
    // Get unread notifications count
    $notifications_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE syc_id = ? AND is_read = 0");
    $notifications_stmt->execute([$syc_id]);
    $notifications_count = $notifications_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'notifications' => (int)$notifications_count
    ]);
} catch (PDOException $e) {
    error_log("Unread count fetch error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>
