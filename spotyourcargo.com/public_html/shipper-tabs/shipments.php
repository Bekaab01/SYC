<?php
// Shipments Tab - All shipments list

$shipments_data = [];

try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'shipments'");
    if ($table_check->rowCount() > 0) {
        $shipper_id_column = 'shipper_id';

        $shipments_stmt = $pdo->prepare("SELECT * FROM shipments WHERE $shipper_id_column = ? ORDER BY created_at DESC");
        $shipments_stmt->execute([$shipper_id]);
        $shipments_data = $shipments_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Shipments data error: " . $e->getMessage());
}

// Handle shipment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cargo'])) {
    $cargo_id = $_POST['cargo_id'] ?? '';
    $description = $_POST['description'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $pickup_location = $_POST['pickup_location'] ?? '';
    $dropoff_location = $_POST['dropoff_location'] ?? '';
    $dimensions = $_POST['dimensions'] ?? '';
    $cargo_type = $_POST['cargo_type'] ?? 'General';

    try {
        $update_stmt = $pdo->prepare("
            UPDATE shipments SET description = ?, weight_tons = ?, origin_geo = ?, dest_geo = ?, dimensions = ?, cargo_type = ?, updated_at = NOW()
            WHERE id = ? AND shipper_id = ?
        ");
        $update_stmt->execute([$description, $weight, $pickup_location, $dropoff_location, $dimensions, $cargo_type, $cargo_id, $shipper_id]);

        if ($update_stmt->rowCount() > 0) {
            $update_success_message = "Shipment updated successfully!";
            // Refresh data
            $shipments_stmt->execute([$shipper_id]);
            $shipments_data = $shipments_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $update_error_message = "No changes made or shipment not found.";
        }
    } catch (PDOException $e) {
        error_log("Shipment update error: " . $e->getMessage());
        $update_error_message = "Error updating shipment. Please try again.";
    }
}
?>

<div class="section-header">
    <h2>All Shipments</h2>
    <button class="btn btn-primary" onclick="loadTabContent('post_load')">+ Add New Cargo</button>
</div>

<?php if (isset($update_success_message)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($update_success_message); ?></div>
<?php endif; ?>

<?php if (isset($update_error_message)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($update_error_message); ?></div>
<?php endif; ?>

<?php if (!empty($shipments_data)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Load Ref</th>
                <th>Description</th>
                <th>Weight</th>
                <th>Pickup Location</th>
                <th>Dropoff Location</th>
                <th>Status</th>
                <th>Created Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($shipments_data as $shipment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($shipment['load_ref'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($shipment['description'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($shipment['weight_tons'] ?? 'N/A'); ?> tons</td>
                    <td><?php echo htmlspecialchars($shipment['origin_geo'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($shipment['dest_geo'] ?? 'N/A'); ?></td>
                    <td>
                        <?php
                        $status = $shipment['status'] ?? $shipment['cargo_status'] ?? 'unknown';
                        $status_class = 'unknown';
                        if (stripos($status, 'pending') !== false) $status_class = 'pending';
                        elseif (stripos($status, 'transit') !== false || stripos($status, 'moving') !== false) $status_class = 'in-transit';
                        elseif (stripos($status, 'delivered') !== false) $status_class = 'delivered';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($shipment['created_at']))); ?></td>
                    <td>
                        <button class="action-btn view-cargo-btn"
                                data-id="<?php echo $shipment['id']; ?>"
                                data-description="<?php echo htmlspecialchars($shipment['description'] ?? ''); ?>"
                                data-weight="<?php echo htmlspecialchars($shipment['weight_tons'] ?? ''); ?>"
                                data-dimensions="<?php echo htmlspecialchars($shipment['dimensions'] ?? ''); ?>"
                                data-pickup-location="<?php echo htmlspecialchars($shipment['origin_geo'] ?? ''); ?>"
                                data-dropoff-location="<?php echo htmlspecialchars($shipment['dest_geo'] ?? ''); ?>"
                                data-cargo-type="<?php echo htmlspecialchars($shipment['cargo_type'] ?? 'General'); ?>">
                            View
                        </button>
                        <button class="action-btn edit-cargo-btn"
                                data-id="<?php echo $shipment['id']; ?>"
                                data-description="<?php echo htmlspecialchars($shipment['description'] ?? ''); ?>"
                                data-weight="<?php echo htmlspecialchars($shipment['weight_tons'] ?? ''); ?>"
                                data-dimensions="<?php echo htmlspecialchars($shipment['dimensions'] ?? ''); ?>"
                                data-pickup-location="<?php echo htmlspecialchars($shipment['origin_geo'] ?? ''); ?>"
                                data-dropoff-location="<?php echo htmlspecialchars($shipment['dest_geo'] ?? ''); ?>"
                                data-cargo-type="<?php echo htmlspecialchars($shipment['cargo_type'] ?? 'General'); ?>">
                            Edit
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-shipping-fast"></i>
        <h3>No Shipments Found</h3>
        <p>You haven't created any shipments yet.</p>
        <button class="btn btn-primary" onclick="loadTabContent('post_load')">+ Add New Cargo</button>
    </div>
<?php endif; ?>

<!-- Edit Cargo Modal -->
<div class="modal" id="edit-cargo-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Cargo</h3>
            <button class="modal-close" onclick="closeModal('edit-cargo-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="edit-cargo-form">
                <input type="hidden" name="cargo_id" id="edit-cargo-id">
                <input type="hidden" name="update_cargo" value="1">
                
                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <textarea id="edit-description" name="description" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit-weight">Weight (tons)</label>
                    <input type="number" id="edit-weight" name="weight" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="edit-dimensions">Dimensions</label>
                    <input type="text" id="edit-dimensions" name="dimensions">
                </div>

                <div class="form-group">
                    <label for="edit-pickup-location">Origin Location</label>
                    <input type="text" id="edit-pickup-location" name="pickup_location" required>
                </div>

                <div class="form-group">
                    <label for="edit-dropoff-location">Destination Location</label>
                    <input type="text" id="edit-dropoff-location" name="dropoff_location" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-cargo-type">Cargo Type</label>
                    <select id="edit-cargo-type" name="cargo_type">
                        <option value="General">General</option>
                        <option value="Fragile">Fragile</option>
                        <option value="Perishable">Perishable</option>
                        <option value="Hazardous">Hazardous</option>
                        <option value="Oversized">Oversized</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-cargo-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Cargo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit cargo functionality
document.addEventListener('click', function(e) {
    const editBtn = e.target.closest('.edit-cargo-btn');
    if (editBtn) {
        const modal = document.getElementById('edit-cargo-modal');
        modal.classList.add('active');
        
        // Populate form fields
        document.getElementById('edit-cargo-id').value = editBtn.dataset.id;
        document.getElementById('edit-description').value = editBtn.dataset.description;
        document.getElementById('edit-weight').value = editBtn.dataset.weight;
        document.getElementById('edit-dimensions').value = editBtn.dataset.dimensions;
        document.getElementById('edit-pickup-location').value = editBtn.dataset.pickupLocation;
        document.getElementById('edit-dropoff-location').value = editBtn.dataset.dropoffLocation;
        document.getElementById('edit-cargo-type').value = editBtn.dataset.cargoType;
    }
});

// Handle form submission
document.getElementById('edit-cargo-form')?.addEventListener('submit', function(e) {
    // Form will submit normally via POST
    // The page will reload with updated data
});
</script>
