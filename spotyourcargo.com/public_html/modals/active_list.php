<?php
// Active Shipments List Modal
// This modal shows all currently active shipments with real-time status

require_once '../includes/config.php';

// Get active shipments for the current shipper
$active_shipments = [];
$shipper_id = $_SESSION['shipper_id'] ?? null;

if ($shipper_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id, c.cargo_id, c.description, c.weight, c.dimensions,
                c.pickup_location, c.dropoff_location, c.status,
                c.driver_name, c.driver_phone, c.vehicle_type,
                c.created_at, c.updated_at,
                TIMESTAMPDIFF(MINUTE, c.last_gps_update, NOW()) as minutes_since_update,
                a.company_name as association_name
            FROM cargo c
            LEFT JOIN associations a ON c.assigned_association_id = a.id
            WHERE c.shipper_user_id = ?
            AND c.status IN ('in_transit', 'moving', 'en_route', 'active', 'picked_up')
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$shipper_id]);
        $active_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Active shipments query error: " . $e->getMessage());
    }
}
?>

<div class="modal active" id="active-list-modal">
    <div class="modal-content active-list-modal">
        <div class="modal-header">
            <h3><i class="fas fa-truck-moving"></i> Active Shipments</h3>
            <button class="modal-close" onclick="closeModal('active-list-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <?php if (!empty($active_shipments)): ?>
                <div class="active-shipments-grid">
                    <?php foreach ($active_shipments as $shipment): ?>
                        <div class="active-shipment-card" data-shipment-id="<?php echo $shipment['id']; ?>">
                            <div class="shipment-header">
                                <div class="shipment-id">
                                    <strong><?php echo htmlspecialchars($shipment['cargo_id']); ?></strong>
                                </div>
                                <div class="shipment-status">
                                    <span class="status-badge <?php
                                        $status_class = 'unknown';
                                        if (stripos($shipment['status'], 'in_transit') !== false) $status_class = 'in-transit';
                                        elseif (stripos($shipment['status'], 'moving') !== false) $status_class = 'moving';
                                        elseif (stripos($shipment['status'], 'picked_up') !== false) $status_class = 'picked-up';
                                        echo $status_class;
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="shipment-details">
                                <div class="detail-row">
                                    <span class="label">Description:</span>
                                    <span class="value"><?php echo htmlspecialchars($shipment['description']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Weight:</span>
                                    <span class="value"><?php echo htmlspecialchars($shipment['weight']); ?> kg</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">From:</span>
                                    <span class="value"><?php echo htmlspecialchars($shipment['pickup_location']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">To:</span>
                                    <span class="value"><?php echo htmlspecialchars($shipment['dropoff_location']); ?></span>
                                </div>
                                <?php if ($shipment['association_name']): ?>
                                <div class="detail-row">
                                    <span class="label">Carrier:</span>
                                    <span class="value"><?php echo htmlspecialchars($shipment['association_name']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="shipment-driver">
                                <div class="driver-info">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($shipment['driver_name'] ?? 'Not assigned'); ?></span>
                                </div>
                                <?php if ($shipment['driver_phone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($shipment['driver_phone']); ?>" class="driver-call">
                                    <i class="fas fa-phone"></i>
                                    Call
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="shipment-actions">
                                <button class="btn btn-secondary track-shipment" data-id="<?php echo $shipment['id']; ?>">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Track
                                </button>
                                <button class="btn btn-primary view-details" data-id="<?php echo $shipment['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                    Details
                                </button>
                            </div>

                            <div class="last-update">
                                <small>
                                    <i class="fas fa-clock"></i>
                                    Last updated: <?php
                                        $minutes = $shipment['minutes_since_update'];
                                        if ($minutes < 60) {
                                            echo $minutes . ' minutes ago';
                                        } else {
                                            echo floor($minutes / 60) . ' hours ago';
                                        }
                                    ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-truck"></i>
                    <h3>No Active Shipments</h3>
                    <p>You don't have any shipments currently in transit.</p>
                    <button class="btn btn-primary" onclick="closeModal('active-list-modal'); loadTabContent('post_load');">
                        <i class="fas fa-plus"></i>
                        Post New Load
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.active-list-modal .modal-content {
    max-width: 1000px;
    max-height: 80vh;
}

.active-shipments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.active-shipment-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
    transition: var(--transition);
}

.active-shipment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.shipment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.shipment-id strong {
    font-size: 16px;
    color: var(--primary-blue);
}

.shipment-details {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    margin-bottom: 8px;
    font-size: 14px;
}

.detail-row .label {
    font-weight: 600;
    color: var(--dark-gray);
    min-width: 80px;
    margin-right: 10px;
}

.detail-row .value {
    color: var(--text-light);
    flex: 1;
}

.shipment-driver {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.driver-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark-gray);
}

.driver-call {
    background: var(--primary-blue);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: var(--transition);
}

.driver-call:hover {
    background: var(--secondary-blue);
    transform: scale(1.05);
}

.shipment-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.shipment-actions .btn {
    flex: 1;
    font-size: 14px;
    padding: 10px;
}

.last-update {
    text-align: center;
    color: var(--text-light);
    font-size: 12px;
}

.last-update i {
    margin-right: 5px;
}

/* Responsive */
@media (max-width: 768px) {
    .active-shipments-grid {
        grid-template-columns: 1fr;
    }

    .shipment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .shipment-driver {
        flex-direction: column;
        gap: 10px;
    }

    .shipment-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Active shipments modal functionality
function openActiveListModal() {
    const modal = document.getElementById('active-list-modal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeActiveListModal() {
    const modal = document.getElementById('active-list-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Track shipment functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('track-shipment') || e.target.closest('.track-shipment')) {
        const button = e.target.classList.contains('track-shipment') ? e.target : e.target.closest('.track-shipment');
        const shipmentId = button.dataset.id;

        // Open map modal or redirect to tracking page
        alert('Tracking feature for shipment ID: ' + shipmentId + ' (Coming soon)');
    }
});

// View shipment details
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('view-details') || e.target.closest('.view-details')) {
        const button = e.target.classList.contains('view-details') ? e.target : e.target.closest('.view-details');
        const shipmentId = button.dataset.id;

        // Load shipment details modal
        loadShipmentDetails(shipmentId);
    }
});
</script>
