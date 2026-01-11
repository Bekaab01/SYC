<?php
// Shipper Dashboard - Operational Overview

// Initialize KPI variables
$active_shipments_count = 0;
$new_quotations_count = 0;
$critical_alerts_count = 0;
$wallet_balance = 0.00;

// Fleet map data
$active_shipments = [];

// Notification timeline
$recent_notifications = [];

// Analytics data
$spend_trend_data = [];
$partner_performance_data = [];

try {
    // Get active shipments count (trucks currently on the road)
    $active_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM shipments c
        WHERE c.shipper_id = ?
        AND c.status IN ('in_transit', 'moving', 'en_route', 'active')
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $active_stmt->execute([$shipper_id]);
    $active_shipments_count = $active_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get new quotations count (unreviewed bids)
    $quotations_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM quotations q
        JOIN shipments s ON q.shipment_id = s.id
        WHERE s.shipper_id = ?
        AND q.status = 'pending'
        AND q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $quotations_stmt->execute([$shipper_id]);
    $new_quotations_count = $quotations_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get critical alerts count (stalled trucks + missing docs)
    $alerts_stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM shipments WHERE shipper_id = ? AND status = 'stalled' AND updated_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)) +
            (SELECT COUNT(*) FROM shipments WHERE shipper_id = ? AND (pod_status IS NULL OR pod_status = '') AND status = 'delivered') as count
    ");
    $alerts_stmt->execute([$shipper_id, $shipper_id]);
    $critical_alerts_count = $alerts_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get wallet balance
    $wallet_stmt = $pdo->prepare("
        SELECT COALESCE(balance, 0) as balance
        FROM shipper_wallets
        WHERE shipper_id = ?
    ");
    $wallet_stmt->execute([$shipper_id]);
    $wallet_result = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
    $wallet_balance = $wallet_result ? floatval($wallet_result['balance']) : 0.00;

    // Get active shipments for map
    $map_stmt = $pdo->prepare("
        SELECT
            s.id, s.load_ref, s.description,
            s.current_lat, s.current_lng, s.status,
            s.pickup_location, s.dropoff_location,
            s.driver_name, s.driver_phone,
            TIMESTAMPDIFF(MINUTE, s.last_gps_update, NOW()) as minutes_since_update
        FROM shipments s
        WHERE s.shipper_id = ?
        AND s.status IN ('in_transit', 'moving', 'en_route', 'active')
        ORDER BY s.updated_at DESC
        LIMIT 50
    ");
    $map_stmt->execute([$shipper_id]);
    $active_shipments = $map_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent notifications
    $notifications_stmt = $pdo->prepare("
        (SELECT
            'bid_received' as type,
            CONCAT('New quotation received for Load #', s.load_ref) as message,
            q.created_at as timestamp,
            q.id as reference_id
         FROM quotations q
         JOIN shipments s ON q.shipment_id = s.id
         WHERE s.shipper_id = ? AND q.status = 'pending'
         ORDER BY q.created_at DESC LIMIT 5)

        UNION ALL

        (SELECT
            'shipment_update' as type,
            CONCAT('Shipment ', s.load_ref, ' status updated to ', s.status) as message,
            s.updated_at as timestamp,
            s.id as reference_id
         FROM shipments s
         WHERE s.shipper_id = ?
         ORDER BY s.updated_at DESC LIMIT 5)

        ORDER BY timestamp DESC LIMIT 10
    ");
    $notifications_stmt->execute([$shipper_id, $shipper_id]);
    $recent_notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get spend trend data (last 30 days)
    $spend_stmt = $pdo->prepare("
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
    $spend_stmt->execute([$shipper_id]);
    $spend_trend_data = $spend_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get partner performance data (top 3 associations by OTD)
    $partner_stmt = $pdo->prepare("
        SELECT
            a.company_name,
            COUNT(*) as total_shipments,
            SUM(CASE WHEN s.status = 'delivered' AND s.actual_delivery_date <= s.expected_delivery_date THEN 1 ELSE 0 END) as on_time_deliveries,
            ROUND(
                (SUM(CASE WHEN s.status = 'delivered' AND s.actual_delivery_date <= s.expected_delivery_date THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                1
            ) as otd_percentage
        FROM shipments s
        JOIN associations a ON s.assigned_association_id = a.id
        WHERE s.shipper_id = ?
        AND s.status = 'delivered'
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY a.id, a.company_name
        ORDER BY otd_percentage DESC
        LIMIT 3
    ");
    $partner_stmt->execute([$shipper_id]);
    $partner_performance_data = $partner_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard KPI error: " . $e->getMessage());
}
?>

<!-- Primary Action Button -->
<div class="primary-action-container">
    <button class="primary-action-btn" onclick="loadTabContent('post_load')">
        <i class="fas fa-plus"></i>
        Post New Load
    </button>
</div>

<!-- Top Layer: Vital Signs (KPI Widgets) -->
<div class="dashboard-layer vital-signs">
    <div class="kpi-grid">
        <!-- Active Shipments -->
        <div class="kpi-card" data-module="active_shipments">
            <div class="kpi-icon">
                <i class="fas fa-truck-moving"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?php echo number_format($active_shipments_count); ?></div>
                <div class="kpi-label">Active Shipments</div>
                <div class="kpi-subtitle">Trucks on the road</div>
            </div>
            <div class="kpi-action">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>

        <!-- New Quotations -->
        <div class="kpi-card" data-module="pending_bids">
            <div class="kpi-icon">
                <i class="fas fa-file-invoice-dollar"></i>
                <?php if ($new_quotations_count > 0): ?>
                <span class="kpi-badge pulse"><?php echo $new_quotations_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?php echo number_format($new_quotations_count); ?></div>
                <div class="kpi-label">New Quotations</div>
                <div class="kpi-subtitle">Unreviewed bids</div>
            </div>
            <div class="kpi-action">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>

        <!-- Critical Alerts -->
        <div class="kpi-card critical-alerts" data-critical="true">
            <div class="kpi-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?php echo number_format($critical_alerts_count); ?></div>
                <div class="kpi-label">Critical Alerts</div>
                <div class="kpi-subtitle">Require attention</div>
            </div>
            <div class="kpi-action">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>

        <!-- Wallet Balance -->
        <div class="kpi-card" data-module="wallet_balance">
            <div class="kpi-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?php echo number_format($wallet_balance, 2); ?> <small>ETB</small></div>
                <div class="kpi-label">Wallet Balance</div>
                <div class="kpi-subtitle">TeleBirr / Bank</div>
            </div>
            <div class="kpi-action">
                <button class="quick-topup-btn">Quick Top-up</button>
            </div>
        </div>
    </div>
</div>

<!-- Middle Layer: The Pulse (Live Operations) -->
<div class="dashboard-layer operations-pulse">
    <!-- Mini Fleet Map -->
    <div class="pulse-section fleet-map-section">
        <div class="section-header">
            <h3><i class="fas fa-map-marked-alt"></i> Live Fleet Map</h3>
            <div class="map-legend">
                <span class="legend-item"><i class="fas fa-circle" style="color: #4CAF50;"></i> On-time</span>
                <span class="legend-item"><i class="fas fa-circle" style="color: #FF9800;"></i> Delayed</span>
                <span class="legend-item"><i class="fas fa-circle" style="color: #F44336;"></i> Stalled</span>
            </div>
        </div>
        <div class="fleet-map-container">
            <div id="fleet-map" class="fleet-map">
                <!-- Map will be rendered here -->
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>Loading live fleet map...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Smart Notification Center -->
    <div class="pulse-section notifications-section">
        <div class="section-header">
            <h3><i class="fas fa-brain"></i> Smart Notifications</h3>
            <button class="view-all-btn" onclick="loadTabContent('notifications')">View All</button>
        </div>
        <div class="notifications-timeline">
            <?php if (!empty($recent_notifications)): ?>
                <?php foreach ($recent_notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['type']; ?>">
                    <div class="notification-icon">
                        <?php
                        $icon_class = 'fas fa-info-circle';
                        if ($notification['type'] === 'bid_received') $icon_class = 'fas fa-dollar-sign';
                        elseif ($notification['type'] === 'shipment_update') $icon_class = 'fas fa-truck';
                        ?>
                        <i class="<?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-time"><?php echo date('H:i', strtotime($notification['timestamp'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>No recent notifications</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Layer: Business Performance -->
<div class="dashboard-layer business-performance">
    <!-- Spend Trend Chart -->
    <div class="performance-widget spend-trend">
        <div class="widget-header">
            <h4><i class="fas fa-chart-line"></i> Logistics Spend Trend</h4>
            <span class="timeframe">Last 30 days</span>
        </div>
        <div class="chart-container">
            <canvas id="spend-trend-chart" width="400" height="200"></canvas>
        </div>
    </div>

    <!-- Partner Performance Chart -->
    <div class="performance-widget partner-performance">
        <div class="widget-header">
            <h4><i class="fas fa-handshake"></i> Partner Performance</h4>
            <span class="metric">On-Time Delivery %</span>
        </div>
        <div class="chart-container">
            <canvas id="partner-performance-chart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Hidden data for JavaScript -->
<script type="application/json" id="dashboard-data">
{
    "active_shipments": <?php echo json_encode($active_shipments); ?>,
    "spend_trend": <?php echo json_encode($spend_trend_data); ?>,
    "partner_performance": <?php echo json_encode($partner_performance_data); ?>
}
</script>

<!-- Include Modal System JavaScript -->
<script src="../assets/js/modal-system.js"></script>

<!-- Include Dashboard Enhancements CSS -->
<link rel="stylesheet" href="../assets/css/dashboard-enhancements.css">

<!-- Inline CSS for testing -->
<style>
.kpi-card {
    background: white !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 12px !important;
    padding: 24px !important;
    display: flex !important;
    align-items: center !important;
    gap: 20px !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    position: relative !important;
    overflow: hidden !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}

.notification-item {
    display: flex !important;
    gap: 15px !important;
    padding: 15px !important;
    margin-bottom: 10px !important;
    background: white !important;
    border-radius: 8px !important;
    border-left: 4px solid #003366 !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    border: 1px solid #e0e0e0 !important;
}

.view-all-btn {
    background: transparent !important;
    color: #003366 !important;
    border: 2px solid #003366 !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    text-decoration: none !important;
    display: inline-block !important;
}

.view-all-btn:hover {
    background: #003366 !important;
    color: white !important;
    transform: translateY(-1px) !important;
}
</style>

<!-- Include Dashboard JavaScript Components -->
<script src="../assets/js/dashboard-maps.js"></script>
<script src="../assets/js/dashboard-charts.js"></script>
<script src="../assets/js/dashboard-heartbeat.js"></script>

<!-- Loading and Error Modal Styles -->
<style>
    .loading-modal .modal-content {
        max-width: 300px;
        text-align: center;
        padding: 30px;
    }

    .loading-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid var(--primary-blue);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .error-modal .modal-content {
        max-width: 400px;
    }

    .error-content {
        text-align: center;
        padding: 20px;
    }

    .error-content i {
        font-size: 48px;
        color: #F44336;
        margin-bottom: 15px;
    }

    .error-content p {
        margin: 0 0 20px 0;
        color: var(--dark-gray);
    }
</style>
