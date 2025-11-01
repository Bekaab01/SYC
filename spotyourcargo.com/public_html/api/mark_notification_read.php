<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
include_once '../../private/db.php';

if (!isset($_SESSION['user_syc_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$syc_id = $_SESSION['user_syc_id'];
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND syc_id = ? AND is_read = 0");
    $stmt->execute([$id, $syc_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Notification not found or already read']);
    }
} catch (PDOException $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
