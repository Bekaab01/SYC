<?php
// Get Chart Data API
// Provides updated chart data for dashboard analytics

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

    // Get spend trend data (last 30 days)
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m-%d') as date,
            SUM(COALESCE(amount, 0)) as daily_spend
        FROM transactions
        WHERE shipper_id = ?
        AND type = 'payment'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
        ORDER BY date
    ");
    $stmt->execute([$shipper_id]);
    $spend_trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['spend_trend'] = $spend_trend_data;

    // Get partner performance data (top 3 associations by OTD)
    $stmt = $pdo->prepare("
        SELECT
            a.company_name,
            COUNT(*) as total_shipments,
            SUM(CASE WHEN c.status = 'delivered' AND c.actual_delivery_date <= c.expected_delivery_date THEN 1 ELSE 0 END) as on_time_deliveries,
            ROUND(
                (SUM(CASE WHEN c.status = 'delivered' AND c.actual_delivery_date <= c.expected_delivery_date THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                1
            ) as otd_percentage
        FROM cargo c
        JOIN associations a ON c.assigned_association_id = a.id
        WHERE c.shipper_user_id = ?
        AND c.status = 'delivered'
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY a.id, a.company_name
        ORDER BY otd_percentage DESC
        LIMIT 3
    ");
    $stmt->execute([$shipper_id]);
    $partner_performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['data']['partner_performance'] = $partner_performance_data;

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Get chart data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Get chart data unexpected error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
