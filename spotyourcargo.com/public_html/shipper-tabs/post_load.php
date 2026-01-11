<?php
// Post Load Tab - High-fidelity 4-step wizard for shippers to post cargo loads

include_once __DIR__ . '/../../private/session_config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../../private/db.php';
include_once __DIR__ . '/../../private/load_ref_generator.php';
include_once __DIR__ . '/../includes/shipper-common.php';
?>
<?php

// Get shipper information
$shipper_id = $_SESSION['user_id'] ?? null;
$shipper_info = [];

if ($shipper_id) {
    try {
        $stmt = $pdo->prepare("SELECT company_name, contact_person FROM users WHERE id = ?");
        $stmt->execute([$shipper_id]);
        $shipper_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Shipper info query error: " . $e->getMessage());
    }
}

// Ethiopian hub locations for shortcuts
$ethiopian_hubs = [
    'Modjo Dry Port',
    'Djibouti (Doraleh)',
    'Galan',
    'Hawassa Industrial Park',
    'Dire Dawa',
    'Mekelle',
    'Bahir Dar',
    'Jimma',
    'Dessie',
    'Shire'
];

// Cargo type classifications
$cargo_types = [
    'General' => ['category' => 'Standard', 'attributes' => []],
    'Electronics' => ['category' => 'Standard', 'attributes' => ['Fragile']],
    'Furniture' => ['category' => 'Standard', 'attributes' => ['Fragile']],
    'Food & Beverages' => ['category' => 'Perishable', 'attributes' => ['Temperature Controlled']],
    'Pharmaceuticals' => ['category' => 'Perishable', 'attributes' => ['Temperature Controlled', 'Fragile']],
    'Chemicals' => ['category' => 'Hazardous', 'attributes' => ['Hazardous Materials']],
    'Machinery' => ['category' => 'Heavy', 'attributes' => ['Oversized']],
    'Construction Materials' => ['category' => 'Heavy', 'attributes' => ['Oversized']],
    'Textiles' => ['category' => 'Standard', 'attributes' => []],
    'Coffee' => ['category' => 'Perishable', 'attributes' => ['Fragile']]
];

// Vehicle types with icons
$vehicle_types = [
    'flatbed' => ['name' => 'Flatbed', 'description' => 'Containers & machinery', 'icon' => 'truck'],
    'side_loader' => ['name' => 'Side-Loader', 'description' => 'Self-loading containers', 'icon' => 'truck-moving'],
    'lowbed' => ['name' => 'Lowbed', 'description' => 'Heavy construction equipment', 'icon' => 'truck-monster'],
    'tanker' => ['name' => 'Tanker/Box', 'description' => 'Fuel or retail goods', 'icon' => 'gas-pump']
];

// Handle new cargo submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_load'])) {
    header('Content-Type: application/json');

    try {
        // Create shipments table if not exists (using schema from db_schema_migration.sql)
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS shipments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                load_ref VARCHAR(20) UNIQUE NOT NULL COMMENT 'Unique load reference ID',
                shipper_id INT NOT NULL,
                association_id INT NULL COMMENT 'Tenant ID - assigned association',
                origin_geo VARCHAR(255) COMMENT 'Origin location coordinates/address',
                dest_geo VARCHAR(255) COMMENT 'Destination location coordinates/address',
                cargo_type VARCHAR(100),
                weight_tons DECIMAL(8,2),
                value_etb DECIMAL(12,2),
                status ENUM('posted', 'bidding', 'awarded', 'assigned', 'in_transit', 'delivered', 'cancelled') DEFAULT 'posted',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                INDEX idx_shipments_load_ref (load_ref),
                INDEX idx_shipments_shipper_id (shipper_id),
                INDEX idx_shipments_association_id (association_id),
                INDEX idx_shipments_status (status),
                INDEX idx_shipments_created_at (created_at),
                FOREIGN KEY (shipper_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($create_table_sql);

        // Validate session user_id
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid session. Please log in.']);
            exit;
        }
        $shipper_id = $_SESSION['user_id'];

        // Extract and validate mandatory fields
        $cargo_type = trim($_POST['cargo_description'] ?? '');
        $origin_geo = trim($_POST['pickup_location'] ?? '');
        $dest_geo = trim($_POST['dropoff_location'] ?? '');

        if (empty($cargo_type) || empty($origin_geo) || empty($dest_geo)) {
            echo json_encode(['status' => 'error', 'message' => 'Mandatory fields are missing: cargo_type, origin_geo, dest_geo.']);
            exit;
        }

        // Extract other fields
        $weight_tons = floatval($_POST['weight_value'] ?? 0);
        $value_etb = floatval($_POST['floor_price'] ?? 0); // Use floor price as value estimate

        // Generate unique load reference ID
        $load_ref = generateUniqueLoadRef($pdo);

        // Insert into shipments table using prepared statement
        $insert_sql = "
            INSERT INTO shipments (load_ref, shipper_id, origin_geo, dest_geo, cargo_type, weight_tons, value_etb, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending Verification')
        ";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([$load_ref, $shipper_id, $origin_geo, $dest_geo, $cargo_type, $weight_tons, $value_etb]);

        $new_load_id = $pdo->lastInsertId();

        echo json_encode(['status' => 'success', 'load_ref' => $load_ref, 'new_load_id' => $new_load_id]);

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit; // Prevent further HTML output
}
?>

<div class="section-header">
    <h2>Post New Load</h2>
    <p>Create a new shipment request with detailed specifications</p>
</div>

<?php if (isset($cargo_success_message)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($cargo_success_message); ?></div>
<?php endif; ?>

<?php if (isset($cargo_error_message)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($cargo_error_message); ?></div>
<?php endif; ?>

<div class="wizard-container">
    <!-- Progress Stepper -->
    <div class="wizard-progress">
        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill"></div>
        </div>
        <div class="progress-steps">
            <div class="step active" data-step="1">
                <div class="step-icon"><i class="fas fa-box"></i></div>
                <div class="step-label">Cargo Details</div>
            </div>
            <div class="step" data-step="2">
                <div class="step-icon"><i class="fas fa-route"></i></div>
                <div class="step-label">Route & Timeline</div>
            </div>
            <div class="step" data-step="3">
                <div class="step-icon"><i class="fas fa-truck"></i></div>
                <div class="step-label">Vehicle Specs</div>
            </div>
            <div class="step" data-step="4">
                <div class="step-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="step-label">Pricing & Publish</div>
            </div>
        </div>
    </div>

    <!-- Wizard Form -->
    <form id="post-load-wizard-form" class="wizard-form" method="POST">
        <!-- Step 1: Cargo Identity & Requirements -->
        <div class="wizard-step active" data-step="1">
            <div class="step-header">
                <h4><i class="fas fa-box"></i> Cargo Identity & Requirements</h4>
                <p>Define your cargo specifications and special handling needs</p>
            </div>

            <div class="step-content">
                <div class="form-section">
                    <div class="form-group">
                        <label for="cargo_description">Cargo Description *</label>
                        <textarea id="cargo_description" name="cargo_description" rows="3" placeholder="Describe your cargo (e.g., Electronics, Furniture, Coffee beans)" required></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Cargo Type & Classification</h5>
                    <div class="form-group">
                        <label for="cargo_type_search">Smart Search</label>
                        <input type="text" id="cargo_type_search" placeholder="Start typing cargo type (e.g., Coffee, Electronics)" autocomplete="off">
                        <div id="cargo_type_dropdown" class="dropdown-menu" style="display: none;">
                            <?php foreach ($cargo_types as $type => $info): ?>
                                <div class="dropdown-item" data-type="<?php echo $type; ?>" data-category="<?php echo $info['category']; ?>" data-attributes="<?php echo implode(',', $info['attributes']); ?>">
                                    <div class="cargo-type-name"><?php echo $type; ?></div>
                                    <div class="cargo-category"><?php echo $info['category']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="cargo_type" name="cargo_type">
                        <input type="hidden" id="cargo_category" name="cargo_category">
                    </div>

                    <div id="cargo_attributes" class="cargo-attributes" style="display: none;">
                        <div class="attribute-tags"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Quantity & Metrics</h5>
                    <div class="dual-input-group">
                        <div class="form-group">
                            <label for="weight_value">Weight *</label>
                            <div class="input-with-unit">
                                <input type="number" id="weight_value" name="weight_value" step="0.1" placeholder="500" required>
                                <select id="weight_unit" name="weight_unit">
                                    <option value="kg">Kg</option>
                                    <option value="tons">Tons</option>
                                    <option value="quintals">Quintals</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="volume_value">Volume</label>
                            <div class="input-with-unit">
                                <input type="number" id="volume_value" name="volume_value" step="0.1" placeholder="10">
                                <select id="volume_unit" name="volume_unit">
                                    <option value="cbm">CBM</option>
                                    <option value="pallets">Pallets</option>
                                    <option value="boxes">Boxes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Hazmat & Security</h5>
                    <div class="toggle-switches">
                        <div class="toggle-group">
                            <label class="toggle-switch">
                                <input type="checkbox" id="hazardous_materials" name="hazardous_materials">
                                <span class="toggle-slider"></span>
                            </label>
                            <div class="toggle-info">
                                <strong>Hazardous Materials</strong>
                                <p>Requires special handling and documentation</p>
                            </div>
                        </div>
                        <div class="toggle-group">
                            <label class="toggle-switch">
                                <input type="checkbox" id="high_value_cargo" name="high_value_cargo">
                                <span class="toggle-slider"></span>
                            </label>
                            <div class="toggle-info">
                                <strong>High-Value Cargo</strong>
                                <p>Armed escort required for security</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>HS Code (Optional)</h5>
                    <div class="form-group">
                        <label for="hs_code_search">HS Code Lookup</label>
                        <input type="text" id="hs_code_search" placeholder="Search Ethiopian customs tariff">
                        <div id="hs_code_results" class="hs-code-results" style="display: none;"></div>
                        <input type="hidden" id="hs_code" name="hs_code">
                        <small class="form-help">Auto-associates with correct tax classification</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Logistics Route & Timeline -->
        <div class="wizard-step" data-step="2">
            <div class="step-header">
                <h4><i class="fas fa-route"></i> Logistics Route & Timeline</h4>
                <p>Define pickup, delivery locations and scheduling requirements</p>
            </div>

            <div class="step-content">
                <div class="form-section">
                    <h5>Origin & Destination</h5>
                    <div class="location-inputs">
                        <div class="form-group">
                            <label for="pickup_location">Pickup Location *</label>
                            <input type="text" id="pickup_location" name="pickup_location" placeholder="Enter pickup location" required>
                            <div class="hub-shortcuts">
                                <span class="shortcut-label">Quick select:</span>
                                <?php foreach (array_slice($ethiopian_hubs, 0, 5) as $hub): ?>
                                    <button type="button" class="hub-shortcut" data-location="<?php echo $hub; ?>"><?php echo $hub; ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="dropoff_location">Dropoff Location *</label>
                            <input type="text" id="dropoff_location" name="dropoff_location" placeholder="Enter dropoff location" required>
                            <div class="hub-shortcuts">
                                <span class="shortcut-label">Quick select:</span>
                                <?php foreach (array_slice($ethiopian_hubs, 0, 5) as $hub): ?>
                                    <button type="button" class="hub-shortcut" data-location="<?php echo $hub; ?>"><?php echo $hub; ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Map Integration</h5>
                    <div class="map-container">
                        <div id="route-map" class="route-map"></div>
                        <div class="map-controls">
                            <button type="button" id="pin-pickup" class="btn btn-secondary">
                                <i class="fas fa-map-marker-alt"></i> Pin Pickup
                            </button>
                            <button type="button" id="pin-dropoff" class="btn btn-secondary">
                                <i class="fas fa-map-marker-alt"></i> Pin Dropoff
                            </button>
                        </div>
                        <input type="hidden" id="pickup_coords" name="pickup_coords">
                        <input type="hidden" id="dropoff_coords" name="dropoff_coords">
                    </div>
                </div>

                <div class="form-section">
                    <h5>Loading & Unloading Hours</h5>
                    <div class="time-windows">
                        <div class="time-window-group">
                            <h6>Loading</h6>
                            <div class="time-inputs">
                                <div class="form-group">
                                    <label>Start Time</label>
                                    <input type="time" id="loading_start" name="loading_start">
                                </div>
                                <div class="form-group">
                                    <label>End Time</label>
                                    <input type="time" id="loading_end" name="loading_end">
                                </div>
                            </div>
                            <div class="time-options">
                                <label class="checkbox-option">
                                    <input type="checkbox" id="night_loading" name="night_loading">
                                    <span class="checkmark"></span>
                                    Night Loading Available
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" id="restricted_loading" name="restricted_loading">
                                    <span class="checkmark"></span>
                                    Restricted Access
                                </label>
                            </div>
                        </div>
                        <div class="time-window-group">
                            <h6>Unloading</h6>
                            <div class="time-inputs">
                                <div class="form-group">
                                    <label>Start Time</label>
                                    <input type="time" id="unloading_start" name="unloading_start">
                                </div>
                                <div class="form-group">
                                    <label>End Time</label>
                                    <input type="time" id="unloading_end" name="unloading_end">
                                </div>
                            </div>
                            <div class="time-options">
                                <label class="checkbox-option">
                                    <input type="checkbox" id="night_unloading" name="night_unloading">
                                    <span class="checkmark"></span>
                                    Night Unloading Available
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" id="restricted_unloading" name="restricted_unloading">
                                    <span class="checkmark"></span>
                                    Restricted Access
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Urgency Level</h5>
                    <div class="urgency-options">
                        <div class="urgency-option" data-urgency="standard">
                            <div class="urgency-header">
                                <input type="radio" id="urgency_standard" name="urgency" value="standard" checked>
                                <label for="urgency_standard">
                                    <i class="fas fa-clock"></i>
                                    <strong>Standard</strong>
                                </label>
                            </div>
                            <div class="urgency-details">
                                <p>Bidding open for 24 hours</p>
                                <span class="urgency-badge">No premium</span>
                            </div>
                        </div>
                        <div class="urgency-option" data-urgency="flash">
                            <div class="urgency-header">
                                <input type="radio" id="urgency_flash" name="urgency" value="flash">
                                <label for="urgency_flash">
                                    <i class="fas fa-bolt"></i>
                                    <strong>Flash</strong>
                                </label>
                            </div>
                            <div class="urgency-details">
                                <p>Bidding open for 2 hours</p>
                                <span class="urgency-badge premium">+25% premium fee</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Vehicle & Handling Specifications -->
        <div class="wizard-step" data-step="3">
            <div class="step-header">
                <h4><i class="fas fa-truck"></i> Vehicle & Handling Specifications</h4>
                <p>Select appropriate vehicle type and handling requirements</p>
            </div>

            <div class="step-content">
                <div class="form-section">
                    <h5>Visual Truck Selector</h5>
                    <div class="vehicle-selector">
                        <?php foreach ($vehicle_types as $key => $vehicle): ?>
                            <div class="vehicle-option" data-vehicle="<?php echo $key; ?>">
                                <input type="radio" id="vehicle_<?php echo $key; ?>" name="vehicle_type" value="<?php echo $key; ?>">
                                <label for="vehicle_<?php echo $key; ?>" class="vehicle-card">
                                    <div class="vehicle-icon">
                                        <i class="fas fa-<?php echo $vehicle['icon']; ?>"></i>
                                    </div>
                                    <div class="vehicle-info">
                                        <h6><?php echo $vehicle['name']; ?></h6>
                                        <p><?php echo $vehicle['description']; ?></p>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Accessory Requirements</h5>
                    <div class="accessory-checklist">
                        <div class="checklist-grid">
                            <label class="accessory-item">
                                <input type="checkbox" name="accessories[]" value="tarpaulins">
                                <span class="accessory-icon"><i class="fas fa-shield-alt"></i></span>
                                <span class="accessory-name">Tarpaulins</span>
                            </label>
                            <label class="accessory-item">
                                <input type="checkbox" name="accessories[]" value="straps">
                                <span class="accessory-icon"><i class="fas fa-link"></i></span>
                                <span class="accessory-name">Straps</span>
                            </label>
                            <label class="accessory-item">
                                <input type="checkbox" name="accessories[]" value="ramps">
                                <span class="accessory-icon"><i class="fas fa-ramp-loading"></i></span>
                                <span class="accessory-name">Ramps</span>
                            </label>
                            <label class="accessory-item">
                                <input type="checkbox" name="accessories[]" value="pallet_jacks">
                                <span class="accessory-icon"><i class="fas fa-dolly"></i></span>
                                <span class="accessory-name">Pallet Jacks</span>
                            </label>
                            <label class="accessory-item">
                                <input type="checkbox" name="accessories[]" value="forklifts">
                                <span class="accessory-icon"><i class="fas fa-truck-loading"></i></span>
                                <span class="accessory-name">Forklifts</span>
                            </label>
                            <label class="accessory-item">
                                <input type="checkbox" name="accessories[]" value="cranes">
                                <span class="accessory-icon"><i class="fas fa-magnet"></i></span>
                                <span class="accessory-name">Cranes</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Pricing Model & Settlement -->
        <div class="wizard-step" data-step="4">
            <div class="step-header">
                <h4><i class="fas fa-dollar-sign"></i> Pricing Model & Settlement</h4>
                <p>Choose your pricing strategy and payment terms</p>
            </div>

            <div class="step-content">
                <div class="pricing-container">
                    <div class="pricing-main">
                        <div class="form-section">
                            <h5>Pricing Model</h5>
                            <div class="pricing-models">
                                <div class="pricing-model" data-model="auction">
                                    <input type="radio" id="pricing_auction" name="pricing_model" value="auction" checked>
                                    <label for="pricing_auction" class="model-card">
                                        <div class="model-header">
                                            <i class="fas fa-gavel"></i>
                                            <h6>Auction Mode</h6>
                                        </div>
                                        <div class="model-description">
                                            <p>Shipper sets floor price, associations competitively bid</p>
                                            <div class="model-features">
                                                <span class="feature">Lowest Bid Wins</span>
                                                <span class="feature">Market Competition</span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <div class="pricing-model" data-model="direct">
                                    <input type="radio" id="pricing_direct" name="pricing_model" value="direct">
                                    <label for="pricing_direct" class="model-card">
                                        <div class="model-header">
                                            <i class="fas fa-handshake"></i>
                                            <h6>Direct Assign</h6>
                                        </div>
                                        <div class="model-description">
                                            <p>Skip bidding, send directly to preferred transporters</p>
                                            <div class="model-features">
                                                <span class="feature">Pre-negotiated Rates</span>
                                                <span class="feature">Trusted Partners</span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="auction_settings" style="display: block;">
                            <h5>Auction Settings</h5>
                            <div class="form-group">
                                <label for="floor_price">Floor Price (ETB) *</label>
                                <input type="number" id="floor_price" name="floor_price" placeholder="50000" required>
                                <small>Minimum price associations must bid above</small>
                            </div>
                            <div class="auction-preview">
                                <div class="auction-timer">
                                    <i class="fas fa-clock"></i>
                                    <span id="auction_duration">24 hours</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="direct_settings" style="display: none;">
                            <h5>Direct Assignment</h5>
                            <div class="form-group">
                                <label for="preferred_associations">Select Preferred Associations</label>
                                <select id="preferred_associations" name="preferred_associations[]" multiple>
                                    <!-- This would be populated dynamically -->
                                    <option value="">Loading associations...</option>
                                </select>
                                <small>Hold Ctrl/Cmd to select multiple</small>
                            </div>
                            <div class="form-group">
                                <label for="negotiated_rate">Negotiated Rate (ETB)</label>
                                <input type="number" id="negotiated_rate" name="negotiated_rate" placeholder="75000">
                            </div>
                        </div>

                        <div class="form-section">
                            <h5>Settlement Terms</h5>
                            <div class="settlement-config">
                                <div class="settlement-split">
                                    <label>Split Configuration</label>
                                    <div class="split-controls">
                                        <div class="split-item">
                                            <span class="split-label">Upfront (Telebirr)</span>
                                            <input type="number" id="upfront_percentage" name="upfront_percentage" value="50" min="0" max="100" readonly>
                                            <span class="percentage">%</span>
                                        </div>
                                        <div class="split-item">
                                            <span class="split-label">On Delivery (POD)</span>
                                            <input type="number" id="delivery_percentage" name="delivery_percentage" value="50" min="0" max="100" readonly>
                                            <span class="percentage">%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="settlement-timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-icon"><i class="fas fa-credit-card"></i></div>
                                        <div class="timeline-content">
                                            <h6>At Loading</h6>
                                            <p>50% payment via Telebirr</p>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-icon"><i class="fas fa-box-check"></i></div>
                                        <div class="timeline-content">
                                            <h6>Proof of Delivery</h6>
                                            <p>50% payment released</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Price Intelligence Sidebar -->
                    <div class="pricing-sidebar">
                        <div class="intelligence-widget">
                            <h6><i class="fas fa-chart-line"></i> Market Intelligence</h6>
                            <div class="market-data">
                                <div class="market-route">
                                    <span class="route-label">Addis Ababa → Djibouti</span>
                                    <div class="price-range">
                                        <span class="range">85k–92k ETB</span>
                                        <span class="trend up"><i class="fas fa-arrow-up"></i> +5%</span>
                                    </div>
                                </div>
                                <div class="market-insights">
                                    <div class="insight-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Coffee shipments trending up</span>
                                    </div>
                                    <div class="insight-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Peak hours: 6AM-10AM</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Wizard Navigation -->
    <div class="wizard-navigation">
        <button type="button" class="btn btn-secondary" id="wizard-prev" style="display: none;">
            <i class="fas fa-arrow-left"></i> Previous
        </button>
        <div class="wizard-indicators">
            <span id="current-step-indicator">Step 1 of 4</span>
        </div>
        <button type="button" class="btn btn-primary" id="wizard-next">
            Next <i class="fas fa-arrow-right"></i>
        </button>
        <button type="button" id="wizard-publish" class="btn btn-success" style="display: none;">
            🚀 Publish to Marketplace
        </button>
    </div>
</div>

<?php
$debugScript = <<<EOD
<script>
// Debug: Inline debug code
console.log('Debug: Starting wizard debug...');

// Check if wizard container exists
const wizard = document.querySelector('.wizard-container');
console.log('Wizard container found:', !!wizard);

// Check if required elements exist
if (wizard) {
    const nextBtn = wizard.querySelector('#wizard-next');
    const cargoDesc = wizard.querySelector('#cargo_description');
    const weightInput = wizard.querySelector('#weight_value');

    console.log('Next button found:', !!nextBtn);
    console.log('Cargo description field found:', !!cargoDesc);
    console.log('Weight input field found:', !!weightInput);

    // Check current values
    if (cargoDesc) console.log('Cargo description value:', '"' + cargoDesc.value + '"');
    if (weightInput) console.log('Weight value:', '"' + weightInput.value + '"');

    // Test validation manually
    function testValidation() {
        console.log('Testing validation...');

        // Test required fields
        const requiredFields = wizard.querySelectorAll('[required]');
        console.log('Required fields found:', requiredFields.length);

        let valid = true;
        requiredFields.forEach(field => {
            const isEmpty = !field.value.trim();
            console.log(`Field \${field.id}: value="\${field.value}", empty=\${isEmpty}`);
            if (isEmpty) valid = false;
        });

        // Test cargo details validation
        const cargoType = wizard.querySelector('#cargo_type').value;
        const cargoTypeSearch = wizard.querySelector('#cargo_type_search').value;
        const weight = wizard.querySelector('#weight_value').value;

        console.log('Cargo type hidden:', '"' + cargoType + '"');
        console.log('Cargo type search:', '"' + cargoTypeSearch + '"');
        console.log('Weight:', '"' + weight + '"');

        const finalCargoType = cargoType || cargoTypeSearch || 'General';
        console.log('Final cargo type:', '"' + finalCargoType + '"');

        const weightValid = weight && parseFloat(weight) > 0;
        console.log('Weight valid:', weightValid);

        const cargoValid = weightValid;
        console.log('Cargo validation result:', cargoValid);

        valid = valid && cargoValid;
        console.log('Overall validation result:', valid);

        return valid;
    }

    // Add debug click handler
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            console.log('Next button clicked');
            const isValid = testValidation();
            console.log('Validation result:', isValid);
            if (!isValid) {
                e.preventDefault();
                console.log('Preventing navigation due to validation failure');
            }
        });
    }

    // Run initial test
    testValidation();
} else {
    console.log('Wizard container not found - checking document structure...');
    console.log('Body children:', document.body.children.length);
    console.log('Document ready state:', document.readyState);
}

// Check if initPostLoadWizard function exists
console.log('initPostLoadWizard function exists:', typeof initPostLoadWizard === 'function');

// Check if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded - rechecking wizard...');
        const wizardAfterLoad = document.querySelector('.wizard-container');
        console.log('Wizard container after DOM load:', !!wizardAfterLoad);
    });
} else {
    console.log('DOM already loaded');
}

console.log('Debug script loaded successfully');
</script>
EOD;
echo $debugScript;
?>
