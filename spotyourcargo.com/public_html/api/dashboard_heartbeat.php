<?php
// Dashboard Heartbeat API
// Provides real-time KPI updates for the dashboard

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include configuration and start session
require_once '../includes/config.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$shipper_id = $_SESSION['shipper_id'] ?? null;
if (!$shipper_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

try {
    $response = ['success' => true, 'data' => []];

    // Get updated KPI counts
    $kpis = [];

    // Active shipments count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM cargo c
        WHERE c.shipper_user_id = ?
        AND c.status IN ('in_transit', 'moving', 'en_route', 'active', 'picked_up')
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$shipper_id]);
    $kpis['active_shipments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Pending bids count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM bids b
        JOIN cargo c ON b.cargo_id = c.id
        WHERE c.shipper_user_id = ?
        AND b.status = 'pending'
        AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$shipper_id]);
    $kpis['pending_bids'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Critical alerts count
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM cargo WHERE shipper_user_id = ? AND status = 'stalled' AND updated_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)) +
            (SELECT COUNT(*) FROM cargo WHERE shipper_user_id = ? AND (pod_status IS NULL OR pod_status = '') AND status = 'delivered') as count
    ");
    $stmt->execute([$shipper_id, $shipper_id]);
    $kpis['critical_alerts'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Wallet balance
    $stmt = $pdo->prepare("
        SELECT COALESCE(balance, 0) as balance, currency
        FROM wallets
        WHERE user_id = ? AND user_type = 'shipper'
    ");
    $stmt->execute([$shipper_id]);
    $wallet_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $kpis['wallet_balance'] = $wallet_data ? floatval($wallet_data['balance']) : 0.00;
    $kpis['wallet_currency'] = $wallet_data ? $wallet_data['currency'] : 'ETB';

    $response['data']['kpis'] = $kpis;

    // Get recent notifications (last 5 minutes)
    $stmt = $pdo->prepare("
        (SELECT
            'bid_received' as type,
            CONCAT('New bid received for Load #', c.cargo_id) as message,
            b.created_at as timestamp,
            b.id as reference_id,
            b.amount,
            a.company_name as bidder_name
         FROM bids b
         JOIN cargo c ON b.cargo_id = c.id
         JOIN associations a ON b.association_id = a.id
         WHERE c.shipper_user_id = ? AND b.status = 'pending'
         AND b.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))

        UNION ALL

        (SELECT
            'shipment_update' as type,
            CONCAT('Shipment ', c.cargo_id, ' status updated to ', c.status) as message,
            c.updated_at as timestamp,
            c.id as reference_id,
            NULL as amount,
            NULL as bidder_name
         FROM cargo c
         WHERE c.shipper_user_id = ?
         AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))

        ORDER BY timestamp DESC LIMIT 5
    ");
    $stmt->execute([$shipper_id, $shipper_id]);
    $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['notifications'] = $recent_notifications;

    // Get updated shipment locations for map
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.cargo_id, c.status,
            c.current_lat, c.current_lng,
            TIMESTAMPDIFF(MINUTE, c.last_gps_update, NOW()) as minutes_since_update
        FROM cargo c
        WHERE c.shipper_user_id = ?
        AND c.status IN ('in_transit', 'moving', 'en_route', 'active', 'picked_up')
        AND c.current_lat IS NOT NULL AND c.current_lng IS NOT NULL
        ORDER BY c.updated_at DESC
        LIMIT 50
    ");
    $stmt->execute([$shipper_id]);
    $shipment_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['shipment_locations'] = $shipment_locations;

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Dashboard heartbeat error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Dashboard heartbeat unexpected error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
