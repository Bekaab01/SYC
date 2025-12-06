<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../../private/db.php';

// Check logged in user and user type
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'association') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get invoice id from GET or POST
$invoice_id = $_GET['invoice_id'] ?? $_POST['invoice_id'] ?? null;
if (empty($invoice_id) || !is_numeric($invoice_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid invoice id']);
    exit();
}

try {
    // Fetch association id for user
    $assoc_stmt = $pdo->prepare("SELECT id FROM associations WHERE user_id = ?");
    $assoc_stmt->execute([$user_id]);
    $association = $assoc_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$association) {
        http_response_code(404);
        echo json_encode(['error' => 'Association not found']);
        exit();
    }
    $association_id = $association['id'];

    // Fetch invoice details along with related service request, shipper and transitor info
    $stmt = $pdo->prepare("
        SELECT i.*,
               sr.origin_city, sr.origin_country, sr.destination_city, sr.destination_country,
               sr.cargo_description, sr.cargo_weight, sr.weight_unit,
               s.company_name as shipper_name,
               s.phone as shipper_phone,
               s.address as shipper_address,
               s.email as shipper_email,
               t.company_name as transitor_name
        FROM invoices i
        LEFT JOIN service_requests sr ON i.load_id = sr.id
        LEFT JOIN shippers s ON sr.cargo_owner_id = s.id
        LEFT JOIN transitors t ON sr.transitor_id = t.id
        WHERE i.association_id = ? AND i.id = ?
    ");
    $stmt->execute([$association_id, $invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit();
    }

    // Return invoice data as JSON
    echo json_encode($invoice);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
