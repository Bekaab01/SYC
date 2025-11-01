<?php
// Admin Carrier Details - No authentication required
// WARNING: This page has full admin access without login for development purposes

// Include database connection
include_once __DIR__ . '/../private/db.php';

// Check if carrier_id is provided
if (!isset($_GET['carrier_id'])) {
    header('Location: index.php');
    exit();
}

$carrier_id = $_GET['carrier_id'];

// Fetch carrier details with user information
$stmt = $pdo->prepare("
    SELECT
        c.id, c.user_id, c.carrier_type, c.status, c.full_name, c.email as carrier_email, c.phone as carrier_phone, c.alt_phone, c.kebele_id,
        -- Removed non-existent columns: c.region, c.city, c.subcity, c.kebele
        c.legal_business_name, c.trade_name, c.business_registration_number, c.tin_number, c.type_of_business,
        c.company_region, c.company_city, c.company_subcity, c.company_kebele, c.company_email, c.company_phone,
        c.operating_areas, c.equipment_types, c.insurance_provider, c.insurance_policy_number, c.insurance_expiry,
        c.business_license, c.emergency_contact_name, c.emergency_contact_phone,
        c.syc_id, c.created_at, c.updated_at,
        u.email as user_email, u.first_name as user_first_name, u.last_name as user_last_name, u.created_at as user_created_at
    FROM carriers c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$carrier_id]);
$carrier = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch carrier documents
$documents_stmt = $pdo->prepare("
    SELECT * FROM carrier_documents
    WHERE carrier_id = ?
    ORDER BY uploaded_at DESC
");
$documents_stmt->execute([$carrier_id]);
$documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch carrier trucks (for company carriers)
$trucks_stmt = $pdo->prepare("
    SELECT * FROM carrier_trucks
    WHERE carrier_id = ?
    ORDER BY created_at DESC
");
$trucks_stmt->execute([$carrier_id]);
$trucks = $trucks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine carrier type and display name
$is_individual = ($carrier['carrier_type'] === 'individual');
$is_company = ($carrier['carrier_type'] === 'company');
$display_name = $is_individual ? ($carrier['full_name'] ?: 'Individual Carrier') : ($carrier['legal_business_name'] ?: $carrier['company_name'] ?: 'Company Carrier');

if (!$carrier) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Carrier Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --light-gray: #F5F5F5;
            --dark-gray: #333333;
            --white: #FFFFFF;
            --success-green: #2ecc71;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--white);
            color: var(--dark-gray);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header */
        .details-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 40px 0;
            text-align: center;
        }

        .details-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .details-header p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Main Content */
        .details-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .details-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: var(--light-gray);
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            color: var(--primary-blue);
            font-size: 24px;
            margin: 0;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-badge.pending { background: #FFF3CD; color: #856404; }
        .status-badge.verified { background: #D4EDDA; color: #155724; }
        .status-badge.rejected { background: #F8D7DA; color: #721C24; }

        .carrier-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .carrier-type-badge.individual { background: #E3F2FD; color: #1976D2; }
        .carrier-type-badge.company { background: #F3E5F5; color: #7B1FA2; }

        .card-body {
            padding: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .info-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .section-title {
            color: var(--primary-blue);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-yellow);
        }

        .info-item {
            margin-bottom: 12px;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 4px;
        }

        .info-value {
            color: var(--dark-gray);
        }

        .equipment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .equipment-tag {
            background: var(--primary-blue);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.2);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        .btn-success {
            background: var(--success-green);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .details-header {
                padding: 30px 0;
            }

            .details-header h1 {
                font-size: 24px;
            }

            .details-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .card-body {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="details-header">
        <h1>Carrier Account Details</h1>
        <p>Complete profile information for <?php echo htmlspecialchars($display_name); ?> <span class="carrier-type-badge <?php echo $carrier['carrier_type']; ?>"><?php echo ucfirst($carrier['carrier_type']); ?> Carrier</span></p>
    </header>

    <!-- Main Content -->
    <div class="details-container">
        <!-- Basic Information Card -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-<?php echo $is_individual ? 'user' : 'building'; ?>"></i> <?php echo $is_individual ? 'Individual Owner-Operator' : 'Company Information'; ?></h2>
                <span class="status-badge <?php echo $carrier['status']; ?>">
                    <?php echo ucfirst($carrier['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <?php if ($is_individual): ?>
                    <!-- Individual Carrier Information -->
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-id-card"></i>
                            Personal Information
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['full_name'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['carrier_email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['carrier_phone']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Alternative Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['alt_phone'] ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Location Details
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Kebele ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($carrier['kebele_id'] ?? 'N/A'); ?></div>
                        </div>
                        <!-- Removed non-existent location fields: region, city, subcity, kebele -->
                        <div class="info-item">
                            <div class="info-label">Location</div>
                            <div class="info-value">Location details not available in current schema</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Company Carrier Information -->
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Company Details
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Legal Business Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['legal_business_name'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Trade Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['trade_name'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Business Registration Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['business_registration_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">TIN Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['tin_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Type of Business</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['type_of_business'] ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Business Location
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Region</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['company_region'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">City</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['company_city'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Subcity</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['company_subcity'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Kebele</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['company_kebele'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['company_email'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['company_phone'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Operations Card -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-truck"></i> Operations & Equipment</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-route"></i>
                            Service Areas
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Operating Areas</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['operating_areas'] ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-tools"></i>
                            Equipment Types
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Available Equipment</div>
                            <div class="equipment-list">
                                <?php
                                $equipment = explode(', ', $carrier['equipment_types'] ?: '');
                                foreach ($equipment as $item) {
                                    if (!empty(trim($item))) {
                                        echo '<span class="equipment-tag">' . htmlspecialchars(trim($item)) . '</span>';
                                    }
                                }
                                if (empty($carrier['equipment_types'])) {
                                    echo '<span class="info-value">N/A</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_company && !empty($trucks)): ?>
        <!-- Fleet Information Card (Company Carriers Only) -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-truck-moving"></i> Fleet Information</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <?php foreach ($trucks as $truck): ?>
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-truck"></i>
                            <?php echo htmlspecialchars($truck['truck_type'] ?: 'Truck'); ?> - <?php echo htmlspecialchars($truck['license_plate']); ?>
                        </h3>
                        <div class="info-item">
                            <div class="info-label">License Plate</div>
                            <div class="info-value"><?php echo htmlspecialchars($truck['license_plate']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Truck Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($truck['truck_type'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Capacity</div>
                            <div class="info-value"><?php echo htmlspecialchars($truck['capacity'] ?: 'N/A'); ?> tons</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Year</div>
                            <div class="info-value"><?php echo htmlspecialchars($truck['year'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value"><?php echo htmlspecialchars($truck['status'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Insurance & Compliance Card -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-shield-alt"></i> Insurance & Compliance</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-contract"></i>
                            Insurance Details
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Insurance Provider</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['insurance_provider'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Policy Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['insurance_policy_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Expiry Date</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['insurance_expiry'] ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-gavel"></i>
                            Business Compliance
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Business License</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['business_license'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contact Card -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-phone"></i> Emergency Contact</h2>
            </div>
            <div class="card-body">
                <div class="info-section">
                    <div class="info-item">
                        <div class="info-label">Emergency Contact Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($carrier['emergency_contact_name'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Emergency Contact Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($carrier['emergency_contact_phone'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Card -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-file-alt"></i> Documents & Images</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($documents)): ?>
                <div class="info-grid">
                    <?php foreach ($documents as $doc): ?>
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-file"></i>
                            <?php echo htmlspecialchars($doc['document_type']); ?>
                        </h3>
                        <div class="info-item">
                            <div class="info-label">File Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($doc['original_filename'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Uploaded</div>
                            <div class="info-value"><?php echo date('M j, Y H:i', strtotime($doc['uploaded_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Actions</div>
                            <div class="info-value">
                                <?php
                                $file_extension = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);

                                if ($is_image): ?>
                                    <a href="serve-file.php?file_id=<?php echo $doc['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View Image
                                    </a>
                                <?php endif; ?>
                                <a href="serve-file.php?file_id=<?php echo $doc['id']; ?>&download=1" class="btn btn-outline btn-sm">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                        <?php if ($is_image): ?>
                        <div class="info-item">
                            <div class="info-label">Preview</div>
                            <div class="info-value">
                                <img src="serve-file.php?file_id=<?php echo $doc['id']; ?>" alt="<?php echo htmlspecialchars($doc['original_filename']); ?>" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="info-section">
                    <p style="text-align: center; color: #666; margin: 20px 0;">No documents uploaded yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Information Card -->
        <div class="details-card">
            <div class="card-header">
                <h2><i class="fas fa-calendar-alt"></i> Account Information</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Account Details
                        </h3>
                        <div class="info-item">
                            <div class="info-label">SYC Carrier ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($carrier['syc_id']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">User Account Created</div>
                            <div class="info-value"><?php echo date('M j, Y H:i', strtotime($carrier['user_created_at'])); ?></div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-clock"></i>
                            Registration Timeline
                        </h3>
                        <div class="info-item">
                            <div class="info-label">Carrier Profile Created</div>
                            <div class="info-value"><?php echo date('M j, Y H:i', strtotime($carrier['created_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value"><?php echo date('M j, Y H:i', strtotime($carrier['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <?php if ($carrier['status'] === 'pending'): ?>
                <form method="POST" action="index.php" style="display: inline;">
                    <input type="hidden" name="carrier_id" value="<?php echo htmlspecialchars($carrier['id']); ?>">
                    <button type="submit" name="approve_carrier" class="btn btn-success">
                        <i class="fas fa-check"></i> Approve Carrier
                    </button>
                </form>
                <form method="POST" action="index.php" style="display: inline;">
                    <input type="hidden" name="carrier_id" value="<?php echo htmlspecialchars($carrier['id']); ?>">
                    <button type="submit" name="reject_carrier" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reject Carrier
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
