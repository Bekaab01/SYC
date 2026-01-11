<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../../private/db.php';

// Check logged in user and user type
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'shipper') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$shipper_id = $_SESSION['user_id'];

try {
    // Get active shipments with assignment and tracking data
    // Active shipments are those that have been assigned to carriers (awarded/assigned/in_transit)
    $sql = "
        SELECT
            s.id,
            s.load_ref,
            s.origin_geo,
            s.dest_geo,
            s.status,
            s.created_at,
            a.id as assignment_id,
            a.status as assignment_status,
            t.plate_no_truck,
            d.name as driver_name,
            d.phone as driver_phone,
            tl.latitude,
            tl.longitude,
            tl.speed_kmh,
            tl.recorded_at,
            TIMESTAMPDIFF(HOUR, tl.recorded_at, NOW()) as hours_inactive
        FROM shipments s
        LEFT JOIN assignments a ON s.id = a.shipment_id AND a.status IN ('assigned', 'in_transit')
        LEFT JOIN trucks t ON a.truck_id = t.id
        LEFT JOIN drivers d ON a.driver_id = d.id
        LEFT JOIN truck_locations tl ON t.id = tl.truck_id
            AND tl.recorded_at = (
                SELECT MAX(recorded_at)
                FROM truck_locations
                WHERE truck_id = t.id
            )
        WHERE s.shipper_id = ?
        AND s.status IN ('awarded', 'assigned', 'in_transit')
        ORDER BY s.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$shipper_id]);
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process shipments to add calculated fields
    $processed_shipments = [];
    $kpi_counts = ['on_time' => 0, 'delayed' => 0, 'critical' => 0];

    foreach ($shipments as $shipment) {
        // Calculate status intelligence
        $hours_inactive = $shipment['hours_inactive'] ?? 24; // Default to 24 if no tracking
        $status_badge = 'unknown';
        $status_class = 'unknown';

        if ($hours_inactive < 1) {
            $status_badge = 'In-Transit';
            $status_class = 'success';
            $kpi_counts['on_time']++;
        } elseif ($hours_inactive >= 1 && $hours_inactive < 5) {
            $status_badge = 'Delayed';
            $status_class = 'warning';
            $kpi_counts['delayed']++;
        } else {
            $status_badge = 'Critical';
            $status_class = 'danger';
            $kpi_counts['critical']++;
        }

        // Calculate ETA (simplified - in real app would use route calculation)
        $eta = 'Calculating...'; // Placeholder

        // Calculate route progress (simplified)
        $progress = 65; // Placeholder percentage

        $processed_shipments[] = [
            'id' => $shipment['id'],
            'load_ref' => $shipment['load_ref'],
            'origin' => $shipment['origin_geo'] ?? 'Unknown',
            'destination' => $shipment['dest_geo'] ?? 'Unknown',
            'driver_name' => $shipment['driver_name'] ?? 'Unassigned',
            'driver_phone' => $shipment['driver_phone'] ?? '',
            'truck_plate' => $shipment['plate_no_truck'] ?? 'Unassigned',
            'status_badge' => $status_badge,
            'status_class' => $status_class,
            'eta' => $eta,
            'progress' => $progress,
            'hours_inactive' => $hours_inactive,
            'last_update' => $shipment['recorded_at'] ?? null
        ];
    }

    // Return data with KPI counts
    echo json_encode([
        'success' => true,
        'kpi_counts' => $kpi_counts,
        'shipments' => $processed_shipments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
