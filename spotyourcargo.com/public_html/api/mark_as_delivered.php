<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
include_once '../../private/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'carrier') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$load_id = $_POST['load_id'] ?? null;

if (!$load_id || !is_numeric($load_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid load ID']);
    exit();
}

try {
    // Check if load belongs to this carrier and is accepted
    $load_stmt = $pdo->prepare("
        SELECT c.id, b.bid_amount, b.carrier_id
        FROM cargo c
        LEFT JOIN bids b ON c.id = b.load_id
        WHERE c.id = ? AND b.carrier_id = ? AND b.status = 'accepted'
    ");
    $load_stmt->execute([$load_id, $user_id]);
    $load = $load_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$load) {
        echo json_encode(['success' => false, 'error' => 'Load not found or not assigned to you']);
        exit();
    }

    // Update cargo status to delivered
    $update_stmt = $pdo->prepare("UPDATE cargo SET status = 'delivered', updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$load_id]);

    // Create invoice if not exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'invoices'");
    if ($table_check->rowCount() == 0) {
        $create_invoice_table = $pdo->prepare("
            CREATE TABLE invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                load_id INT NOT NULL,
                carrier_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status ENUM('pending', 'paid') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $create_invoice_table->execute();
    }

    // Check if invoice already exists
    $invoice_check = $pdo->prepare("SELECT id FROM invoices WHERE load_id = ?");
    $invoice_check->execute([$load_id]);
    if (!$invoice_check->fetch(PDO::FETCH_ASSOC)) {
        // Create invoice
        $invoice_stmt = $pdo->prepare("
            INSERT INTO invoices (load_id, carrier_id, amount, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $invoice_stmt->execute([$load_id, $load['carrier_id'], $load['bid_amount']]);
    }

    echo json_encode(['success' => true, 'message' => 'Load marked as delivered and invoice generated']);
} catch (PDOException $e) {
    error_log("Mark as delivered error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
