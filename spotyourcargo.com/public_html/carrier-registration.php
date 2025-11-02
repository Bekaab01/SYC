<?php
// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();

// Include database connection
include_once __DIR__ . '/../private/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: access.php');
    exit();
}

// Check if user is a carrier
if ($_SESSION['user_type'] !== 'carrier') {
    header('Location: access.php');
    exit();
}

// Check if carrier already completed registration
$user_id = $_SESSION['user_id'];
$carrier_check_stmt = $pdo->prepare("SELECT id, status FROM carriers WHERE user_id = ?");
$carrier_check_stmt->execute([$user_id]);
$existing_carrier = $carrier_check_stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_carrier) {
    // Check carrier status and redirect accordingly
    if ($existing_carrier['status'] === 'active' || $existing_carrier['status'] === 'verified') {
        // Carrier is approved, redirect to dashboard
        header('Location: carrier-dashboard.php');
        exit();
    } elseif ($existing_carrier['status'] === 'pending' || $existing_carrier['status'] === 'manual_review') {
        // Carrier is pending approval or manual review, redirect to pending approval page
        header('Location: carrier-pending-approval.php');
        exit();
    } elseif ($existing_carrier['status'] === 'rejected') {
        // Carrier was rejected, redirect to pending approval page to show status
        header('Location: carrier-pending-approval.php');
        exit();
    }
}

// Get user data for pre-filling form
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables
$registration_error = '';
$registration_success = '';
$carrier_type = $_POST['carrier_type'] ?? 'individual';

// Ethiopian regions for dropdown
$ethiopian_regions = [
    'Addis Ababa', 'Afar', 'Amhara', 'Benishangul-Gumuz', 'Dire Dawa',
    'Gambella', 'Harari', 'Oromia', 'Somali', 'Southern Nations, Nationalities, and Peoples\' Region (SNNPR)', 'Tigray'
];

// Vehicle types for dropdown
$vehicle_types = [
    'Dry Van', 'Flatbed', 'Tanker', 'Container Carrier', 'Refrigerated Van',
    'Curtain Sider', 'Box Truck', 'Dump Truck', 'Lowboy', 'Car Carrier'
];

// Business types for company operators
$business_types = [
    'PLC', 'Share Company', 'Private Limited Company', 'Cooperative', 'Sole Proprietorship',
    'Partnership', 'Branch Office', 'Foreign Company'
];

// Handle file uploads securely
function handleFileUpload($file_input, $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'], $max_size = 5242880) { // 5MB
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$file_input];
    $file_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate file type
    if (!in_array($file_ext, $allowed_types)) {
        return ['error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        return ['error' => 'File too large. Maximum size: ' . ($max_size / 1024 / 1024) . 'MB'];
    }

    // Generate secure filename
    $secure_name = uniqid('carrier_' . $file_input . '_', true) . '.' . $file_ext;
    $upload_dir = __DIR__ . '/../private/uploads/carriers/';
    $upload_path = $upload_dir . $secure_name;

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'path' => 'private/uploads/carriers/' . $secure_name,
            'original_name' => $file_name
        ];
    } else {
        return ['error' => 'Failed to upload file'];
    }
}

// Handle carrier registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_registration'])) {
    $carrier_type = trim($_POST['carrier_type'] ?? 'individual');

    // Common validation
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($full_name) || empty($email) || empty($phone)) {
        $registration_error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registration_error = 'Please enter a valid email address.';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Use the SYC ID from users table (already generated during signup)
            $syc_id = $user_data['syc_id'];

            // Initialize common data
            $carrier_data = [
                'user_id' => $user_id,
                'syc_id' => $syc_id,
                'carrier_type' => $carrier_type,
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'status' => 'pending'
            ];

            // Handle individual owner-operator data
            if ($carrier_type === 'individual') {
                $carrier_data = array_merge($carrier_data, [
                    'kebele_id' => trim($_POST['kebele_id'] ?? ''),
                    'driving_license_number' => trim($_POST['driving_license_number'] ?? ''),
                    'vehicle_plate_number' => trim($_POST['vehicle_plate_number'] ?? ''),
                    'vehicle_type' => trim($_POST['vehicle_type'] ?? ''),
                    'alt_phone' => trim($_POST['alt_phone'] ?? ''),
                    'bank_name' => trim($_POST['bank_name'] ?? ''),
                    'account_number' => trim($_POST['account_number'] ?? ''),
                    'account_holder_name' => trim($_POST['account_holder_name'] ?? '')
                ]);

                // Handle file uploads for individual
                $defensive_driving_cert = handleFileUpload('defensive_driving_cert');
                $vehicle_registration_cert = handleFileUpload('vehicle_registration_cert');
                $vehicle_insurance_cert = handleFileUpload('vehicle_insurance_cert');

                if (isset($defensive_driving_cert['error'])) {
                    $registration_error = 'Defensive Driving Certificate: ' . $defensive_driving_cert['error'];
                } elseif (isset($vehicle_registration_cert['error'])) {
                    $registration_error = 'Vehicle Registration Certificate: ' . $vehicle_registration_cert['error'];
                } elseif (isset($vehicle_insurance_cert['error'])) {
                    $registration_error = 'Vehicle Insurance Certificate: ' . $vehicle_insurance_cert['error'];
                } else {
                $carrier_data['defensive_driving_cert'] = null;
                $carrier_data['vehicle_registration_cert'] = null;
                $carrier_data['vehicle_insurance_cert'] = null;
                }
            }
            // Handle company/fleet operator data
            elseif ($carrier_type === 'company') {
                $carrier_data = array_merge($carrier_data, [
                    'legal_business_name' => trim($_POST['legal_business_name'] ?? ''),
                    'trade_name' => trim($_POST['trade_name'] ?? ''),
                    'business_registration_number' => trim($_POST['business_registration_number'] ?? ''),
                    'tin_number' => trim($_POST['tin_number'] ?? ''),
                    'type_of_business' => trim($_POST['type_of_business'] ?? ''),
                    'company_region' => trim($_POST['company_region'] ?? ''),
                    'company_city' => trim($_POST['company_city'] ?? ''),
                    'company_subcity' => trim($_POST['company_subcity'] ?? ''),
                    'company_kebele' => trim($_POST['company_kebele'] ?? ''),
                    'company_email' => trim($_POST['company_email'] ?? ''),
                    'company_phone' => trim($_POST['company_phone'] ?? ''),
                    'total_trucks' => (int)($_POST['total_trucks'] ?? 0),
                    'owner_manager_name' => trim($_POST['owner_manager_name'] ?? ''),
                    'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
                    'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
                    'bank_name' => trim($_POST['bank_name'] ?? ''),
                    'account_number' => trim($_POST['account_number'] ?? ''),
                    'account_holder_name' => trim($_POST['account_holder_name'] ?? '')
                ]);

                // Handle file uploads for company
                $business_license_upload = handleFileUpload('business_license_upload');
                $tin_certificate_upload = handleFileUpload('tin_certificate_upload');
                $id_passport_upload = handleFileUpload('id_passport_upload');
                $bank_statement_upload = handleFileUpload('bank_statement_upload');
                $safety_compliance_cert = handleFileUpload('safety_compliance_cert');
                $cooperative_membership_proof = handleFileUpload('cooperative_membership_proof');
                $reference_letter_upload = handleFileUpload('reference_letter_upload');

                // Check for upload errors
                $upload_errors = [];
                foreach (['business_license_upload', 'tin_certificate_upload', 'id_passport_upload', 'bank_statement_upload', 'safety_compliance_cert', 'cooperative_membership_proof', 'reference_letter_upload'] as $upload_field) {
                    $upload_result = ${$upload_field};
                    if (isset($upload_result['error'])) {
                        $upload_errors[] = ucfirst(str_replace('_', ' ', $upload_field)) . ': ' . $upload_result['error'];
                    }
                }

                if (!empty($upload_errors)) {
                    $registration_error = implode('<br>', $upload_errors);
                } else {
                    $carrier_data['business_license_upload'] = null;
                    $carrier_data['tin_certificate_upload'] = null;
                    $carrier_data['id_passport_upload'] = null;
                    $carrier_data['bank_statement_upload'] = null;
                    $carrier_data['safety_compliance_cert'] = null;
                    $carrier_data['cooperative_membership_proof'] = null;
                    $carrier_data['reference_letter_upload'] = null;
                }
            }

            if (empty($registration_error)) {
                // Insert carrier data
                $columns = implode(', ', array_keys($carrier_data));
                $placeholders = str_repeat('?, ', count($carrier_data) - 1) . '?';

                $carrier_sql = "INSERT INTO carriers ($columns) VALUES ($placeholders)
                               ON DUPLICATE KEY UPDATE " .
                               implode(', ', array_map(function($col) { return "$col = VALUES($col)"; }, array_keys($carrier_data)));

                $carrier_stmt = $pdo->prepare($carrier_sql);
                $carrier_stmt->execute(array_values($carrier_data));

                $carrier_id = $pdo->lastInsertId();

                // Insert carrier documents
                if ($carrier_type === 'individual') {
                    if ($defensive_driving_cert) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'defensive_driving_cert', $defensive_driving_cert['path'], $defensive_driving_cert['original_name']]);
                    }
                    if ($vehicle_registration_cert) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'vehicle_registration_cert', $vehicle_registration_cert['path'], $vehicle_registration_cert['original_name']]);
                    }
                    if ($vehicle_insurance_cert) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'vehicle_insurance_cert', $vehicle_insurance_cert['path'], $vehicle_insurance_cert['original_name']]);
                    }
                } elseif ($carrier_type === 'company') {
                    if ($business_license_upload) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'business_license_upload', $business_license_upload['path'], $business_license_upload['original_name']]);
                    }
                    if ($tin_certificate_upload) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'tin_certificate_upload', $tin_certificate_upload['path'], $tin_certificate_upload['original_name']]);
                    }
                    if ($id_passport_upload) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'id_passport_upload', $id_passport_upload['path'], $id_passport_upload['original_name']]);
                    }
                    if ($bank_statement_upload) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'bank_statement_upload', $bank_statement_upload['path'], $bank_statement_upload['original_name']]);
                    }
                    if ($safety_compliance_cert) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'safety_compliance_cert', $safety_compliance_cert['path'], $safety_compliance_cert['original_name']]);
                    }
                    if ($cooperative_membership_proof) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'cooperative_membership_proof', $cooperative_membership_proof['path'], $cooperative_membership_proof['original_name']]);
                    }
                    if ($reference_letter_upload) {
                        $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'reference_letter_upload', $reference_letter_upload['path'], $reference_letter_upload['original_name']]);
                    }
                }

                // Handle truck data for company operators
                if ($carrier_type === 'company' && isset($_POST['trucks']) && is_array($_POST['trucks'])) {
                    foreach ($_POST['trucks'] as $truck_data) {
                        $truck = [
                            'carrier_id' => $carrier_id,
                            'plate_number' => trim($truck_data['plate_number'] ?? ''),
                            'vehicle_type' => trim($truck_data['vehicle_type'] ?? ''),
                            'ownership_type' => trim($truck_data['ownership_type'] ?? 'owned'),
                            'driver_name' => trim($truck_data['driver_name'] ?? ''),
                            'driver_license_number' => trim($truck_data['driver_license_number'] ?? '')
                        ];

                        // Handle truck file uploads
                        $truck_registration_cert = handleFileUpload('truck_registration_' . $truck_data['index'] ?? '');
                        $truck_insurance_cert = handleFileUpload('truck_insurance_' . $truck_data['index'] ?? '');
                        $driver_license_path = handleFileUpload('driver_license_' . $truck_data['index'] ?? '');
                        $defensive_driving_cert_path = handleFileUpload('truck_defensive_driving_' . $truck_data['index'] ?? '');

                        $truck['registration_cert_path'] = $truck_registration_cert ? $truck_registration_cert['path'] : null;
                        $truck['insurance_cert_path'] = $truck_insurance_cert ? $truck_insurance_cert['path'] : null;
                        $truck['driver_license_path'] = $driver_license_path ? $driver_license_path['path'] : null;
                        $truck['defensive_driving_cert_path'] = $defensive_driving_cert_path ? $defensive_driving_cert_path['path'] : null;

                        // Set truck paths to null since documents are moved to carrier_documents
                        $truck['registration_cert_path'] = null;
                        $truck['insurance_cert_path'] = null;
                        $truck['driver_license_path'] = null;
                        $truck['defensive_driving_cert_path'] = null;

                        $truck_columns = implode(', ', array_keys($truck));
                        $truck_placeholders = str_repeat('?, ', count($truck) - 1) . '?';
                        $truck_sql = "INSERT INTO carrier_trucks ($truck_columns) VALUES ($truck_placeholders)";

                        $truck_stmt = $pdo->prepare($truck_sql);
                        $truck_stmt->execute(array_values($truck));

                        $truck_id = $pdo->lastInsertId();

                        if ($truck_registration_cert) {
                            $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'truck_registration_cert_' . $truck_id, $truck_registration_cert['path'], $truck_registration_cert['original_name']]);
                        }
                        if ($truck_insurance_cert) {
                            $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'truck_insurance_cert_' . $truck_id, $truck_insurance_cert['path'], $truck_insurance_cert['original_name']]);
                        }
                        if ($driver_license_path) {
                            $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'driver_license_' . $truck_id, $driver_license_path['path'], $driver_license_path['original_name']]);
                        }
                        if ($defensive_driving_cert_path) {
                            $pdo->prepare("INSERT INTO carrier_documents (carrier_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$carrier_id, 'truck_defensive_driving_cert_' . $truck_id, $defensive_driving_cert_path['path'], $defensive_driving_cert_path['original_name']]);
                        }
                    }
                }

                // Commit transaction
                $pdo->commit();

                // Update session to indicate registration is complete
                $_SESSION['carrier_registered'] = true;

                // Redirect to pending approval page
                header('Location: carrier-pending-approval.php');
                exit();
            }

        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();

            error_log("Carrier registration error: " . $e->getMessage());
            $registration_error = 'Registration failed. Please try again. Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Complete Carrier Registration</title>
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
            --error-red: #e74c3c;
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
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .registration-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 30px 0;
            text-align: center;
        }

        .registration-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .registration-header p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Progress Indicator */
        .progress-container {
            max-width: 800px;
            margin: -20px auto 0;
            padding: 0 20px;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.2);
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            background: var(--primary-yellow);
            height: 100%;
            width: 16.67%;
            transition: width 0.3s ease;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .progress-step {
            text-align: center;
            flex: 1;
        }

        .progress-step.completed .step-number {
            background: var(--primary-yellow);
            color: var(--primary-blue);
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .step-label {
            font-size: 12px;
            opacity: 0.8;
        }

        /* Multi-step form styles */
        .multi-step-form {
            width: 100%;
        }

        .form-step {
            display: none;
        }

        .step-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .step-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        /* Carrier type selection */
        .carrier-type-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .carrier-type-option {
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: var(--transition);
            background: var(--white);
        }

        .carrier-type-option:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(0, 51, 102, 0.1);
        }

        .carrier-type-option.selected {
            border-color: var(--primary-blue);
            background: rgba(0, 51, 102, 0.05);
        }

        .type-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .type-icon i {
            font-size: 48px;
            color: var(--primary-blue);
        }

        .type-content h4 {
            color: var(--primary-blue);
            margin-bottom: 10px;
            font-size: 18px;
        }

        .type-content ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .type-content li {
            padding: 4px 0;
            font-size: 14px;
            color: var(--dark-gray);
        }

        .type-radio {
            text-align: center;
            margin-top: 15px;
        }

        .type-radio input[type="radio"] {
            display: none;
        }

        .type-radio label {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 50%;
            cursor: pointer;
            position: relative;
        }

        .type-radio input[type="radio"]:checked + label {
            border-color: var(--primary-blue);
            background: var(--primary-blue);
        }

        .type-radio input[type="radio"]:checked + label::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-yellow);
        }

        /* Review section */
        .review-section {
            background: var(--light-gray);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .review-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .review-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .review-item h4 {
            color: var(--primary-blue);
            margin-bottom: 5px;
            font-size: 16px;
        }

        .review-item p {
            color: var(--dark-gray);
            margin: 0;
        }

        /* Terms section */
        .terms-section {
            background: var(--light-gray);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        /* File hints */
        .file-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        /* Document sections */
        .document-section h4 {
            color: var(--primary-blue);
            margin-bottom: 15px;
            font-size: 18px;
        }

        /* Main Content */
        .registration-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .card-header {
            background: var(--light-gray);
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
        }

        .card-header h2 {
            color: var(--primary-blue);
            font-size: 24px;
            margin-bottom: 10px;
        }

        .card-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .card-body {
            padding: 30px;
        }

        /* Form Styles */
        .registration-form {
            display: grid;
            gap: 25px;
        }

        .form-section {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-yellow);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-blue);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #ffebee;
            color: var(--error-red);
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e9;
            color: var(--success-green);
            border: 1px solid #c8e6c9;
        }

        .alert i {
            font-size: 18px;
        }

        /* Required Field Indicator */
        .required {
            color: var(--error-red);
        }


        
        /* Responsive Design */
        @media (max-width: 768px) {
            .registration-header {
                padding: 20px 0;
            }

            .registration-header h1 {
                font-size: 24px;
            }

            .registration-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .card-body {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .progress-steps {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="registration-header">
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-steps">
                <div class="progress-step completed">
                    <div class="step-number">1</div>
                    <div class="step-label">Account Created</div>
                </div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div class="step-label">Carrier Details</div>
                </div>
            </div>
        </div>
        <h1>Complete Your Carrier Registration</h1>
        <p>Please provide your carrier information to start receiving load opportunities</p>
    </header>

    <!-- Main Content -->
    <div class="registration-container">
        <div class="registration-card">
            <div class="card-header">
                <h2><i class="fas fa-truck"></i> Carrier Information</h2>
                <p>Fill out the details below to complete your carrier profile</p>
            </div>

            <div class="card-body">
                <?php if (!empty($registration_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($registration_error); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($registration_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($registration_success); ?></span>
                </div>
                <?php endif; ?>

                <!-- Multi-Step Form Container -->
                <div class="multi-step-form">
                    <!-- Step 1: Carrier Type Selection -->
                    <div class="form-step" id="step-1">
                        <div class="step-header">
                            <h3 class="section-title">
                                <i class="fas fa-user-tag"></i>
                                Select Carrier Type
                            </h3>
                            <p>Choose the type of carrier registration that best describes your business</p>
                        </div>

                        <div class="carrier-type-selection">
                            <div class="carrier-type-option" data-type="individual">
                                <div class="type-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="type-content">
                                    <h4>Individual Owner-Operator</h4>
                                    <p>Single truck owner operating independently</p>
                                    <ul>
                                        <li>Personal vehicle registration</li>
                                        <li>Individual insurance</li>
                                        <li>Simple documentation</li>
                                    </ul>
                                </div>
                                <div class="type-radio">
                                    <input type="radio" id="carrier_individual" name="carrier_type" value="individual" <?php echo ($carrier_type === 'individual') ? 'checked' : ''; ?>>
                                    <label for="carrier_individual"></label>
                                </div>
                            </div>

                            <div class="carrier-type-option" data-type="company">
                                <div class="type-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="type-content">
                                    <h4>Company/Fleet Operator</h4>
                                    <p>Business with multiple trucks and drivers</p>
                                    <ul>
                                        <li>Business registration</li>
                                        <li>Fleet management</li>
                                        <li>Multiple truck documentation</li>
                                    </ul>
                                </div>
                                <div class="type-radio">
                                    <input type="radio" id="carrier_company" name="carrier_type" value="company" <?php echo ($carrier_type === 'company') ? 'checked' : ''; ?>>
                                    <label for="carrier_company"></label>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn btn-primary" id="continue-step-1">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Account Information -->
                    <div class="form-step" id="step-2" style="display: none;">
                        <div class="step-header">
                            <h3 class="section-title">
                                <i class="fas fa-user-circle"></i>
                                Account Information
                            </h3>
                            <p>Basic account details for your carrier profile</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name <span class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="alt_phone">Alternative Phone Number</label>
                                <input type="tel" id="alt_phone" name="alt_phone" placeholder="Optional secondary phone">
                            </div>
                        </div>



                        <div class="step-actions">
                            <button type="button" class="btn btn-outline" id="back-step-2">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="continue-step-2">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Business/Vehicle Details -->
                    <div class="form-step" id="step-3" style="display: none;">
                        <div class="step-header">
                            <h3 class="section-title">
                                <i class="fas fa-truck"></i>
                                <span id="step-3-title">Business Details</span>
                            </h3>
                            <p id="step-3-description">Provide your business or vehicle information</p>
                        </div>

                        <!-- Individual Owner-Operator Fields -->
                        <div id="individual-fields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="kebele_id">Kebele ID</label>
                                    <input type="text" id="kebele_id" name="kebele_id" placeholder="Your Kebele ID number">
                                </div>

                                <div class="form-group">
                                    <label for="driving_license_number">Driving License Number</label>
                                    <input type="text" id="driving_license_number" name="driving_license_number" placeholder="License number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vehicle_plate_number">Vehicle Plate Number</label>
                                    <input type="text" id="vehicle_plate_number" name="vehicle_plate_number" placeholder="e.g., AA-1234">
                                </div>

                                <div class="form-group">
                                    <label for="vehicle_type">Vehicle Type</label>
                                    <select id="vehicle_type" name="vehicle_type">
                                        <option value="">Select vehicle type</option>
                                        <?php foreach ($vehicle_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Company/Fleet Operator Fields -->
                        <div id="company-fields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="legal_business_name">Legal Business Name</label>
                                    <input type="text" id="legal_business_name" name="legal_business_name" placeholder="Official registered business name">
                                </div>

                                <div class="form-group">
                                    <label for="trade_name">Trade Name (DBA)</label>
                                    <input type="text" id="trade_name" name="trade_name" placeholder="Doing business as name">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="business_registration_number">Business Registration Number</label>
                                    <input type="text" id="business_registration_number" name="business_registration_number" placeholder="Registration number">
                                </div>

                                <div class="form-group">
                                    <label for="tin_number">TIN Number</label>
                                    <input type="text" id="tin_number" name="tin_number" placeholder="Tax identification number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="type_of_business">Type of Business</label>
                                    <select id="type_of_business" name="type_of_business">
                                        <option value="">Select business type</option>
                                        <?php foreach ($business_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="total_trucks">Total Number of Trucks</label>
                                    <input type="number" id="total_trucks" name="total_trucks" min="1" placeholder="Number of trucks">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="company_region">Region</label>
                                    <select id="company_region" name="company_region">
                                        <option value="">Select region</option>
                                        <?php foreach ($ethiopian_regions as $region): ?>
                                        <option value="<?php echo htmlspecialchars($region); ?>"><?php echo htmlspecialchars($region); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="company_city">City</label>
                                    <input type="text" id="company_city" name="company_city" placeholder="City name">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="company_subcity">Subcity</label>
                                    <input type="text" id="company_subcity" name="company_subcity" placeholder="Subcity name">
                                </div>

                                <div class="form-group">
                                    <label for="company_kebele">Kebele</label>
                                    <input type="text" id="company_kebele" name="company_kebele" placeholder="Kebele number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="company_email">Company Email</label>
                                    <input type="email" id="company_email" name="company_email" placeholder="company@example.com">
                                </div>

                                <div class="form-group">
                                    <label for="company_phone">Company Phone</label>
                                    <input type="tel" id="company_phone" name="company_phone" placeholder="Company phone number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="owner_manager_name">Owner/Manager Name</label>
                                    <input type="text" id="owner_manager_name" name="owner_manager_name" placeholder="Full name of owner/manager">
                                </div>

                                <div class="form-group">
                                    <label for="emergency_contact_name">Emergency Contact Name</label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" placeholder="Emergency contact person">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" placeholder="Emergency contact phone">
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn btn-outline" id="back-step-3">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="continue-step-3">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Documents Upload -->
                    <div class="form-step" id="step-4" style="display: none;">
                        <div class="step-header">
                            <h3 class="section-title">
                                <i class="fas fa-file-upload"></i>
                                Documents Upload
                            </h3>
                            <p>Upload required documents for verification</p>
                        </div>

                        <!-- Individual Documents -->
                        <div id="individual-documents" style="display: none;">
                            <div class="document-section">
                                <h4>Individual Owner-Operator Documents</h4>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="defensive_driving_cert">Defensive Driving Certificate</label>
                                        <input type="file" id="defensive_driving_cert" name="defensive_driving_cert" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="vehicle_registration_cert">Vehicle Registration Certificate</label>
                                        <input type="file" id="vehicle_registration_cert" name="vehicle_registration_cert" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="vehicle_insurance_cert">Vehicle Insurance Certificate</label>
                                        <input type="file" id="vehicle_insurance_cert" name="vehicle_insurance_cert" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Documents -->
                        <div id="company-documents" style="display: none;">
                            <div class="document-section">
                                <h4>Company Documents</h4>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="business_license_upload">Business License</label>
                                        <input type="file" id="business_license_upload" name="business_license_upload" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="tin_certificate_upload">TIN Certificate</label>
                                        <input type="file" id="tin_certificate_upload" name="tin_certificate_upload" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="id_passport_upload">ID/Passport</label>
                                        <input type="file" id="id_passport_upload" name="id_passport_upload" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="bank_statement_upload">Bank Statement</label>
                                        <input type="file" id="bank_statement_upload" name="bank_statement_upload" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="safety_compliance_cert">Safety Compliance Certificate</label>
                                        <input type="file" id="safety_compliance_cert" name="safety_compliance_cert" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="cooperative_membership_proof">Cooperative Membership Proof</label>
                                        <input type="file" id="cooperative_membership_proof" name="cooperative_membership_proof" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB (optional)</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="reference_letter_upload">Reference Letter</label>
                                        <input type="file" id="reference_letter_upload" name="reference_letter_upload" accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="file-hint">PDF, JPG, PNG up to 5MB (optional)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn btn-outline" id="back-step-4">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="continue-step-4">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 5: Banking Information -->
                    <div class="form-step" id="step-5" style="display: none;">
                        <div class="step-header">
                            <h3 class="section-title">
                                <i class="fas fa-university"></i>
                                Banking Information
                            </h3>
                            <p>Provide your banking details for payments</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bank_name">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" placeholder="Name of your bank">
                            </div>

                            <div class="form-group">
                                <label for="account_number">Account Number</label>
                                <input type="text" id="account_number" name="account_number" placeholder="Bank account number">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="account_holder_name">Account Holder Name</label>
                                <input type="text" id="account_holder_name" name="account_holder_name" placeholder="Name on the account">
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn btn-outline" id="back-step-5">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="continue-step-5">
                                Review & Submit <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 6: Review & Submit -->
                    <div class="form-step" id="step-6" style="display: none;">
                        <div class="step-header">
                            <h3 class="section-title">
                                <i class="fas fa-clipboard-check"></i>
                                Review & Submit
                            </h3>
                            <p>Please review your information before submitting</p>
                        </div>

                        <div class="review-section">
                            <div class="review-item">
                                <h4>Carrier Type</h4>
                                <p id="review-carrier-type">-</p>
                            </div>

                            <div class="review-item">
                                <h4>Personal Information</h4>
                                <p id="review-personal-info">-</p>
                            </div>

                            <div class="review-item" id="review-business-info" style="display: none;">
                                <h4>Business Information</h4>
                                <p id="review-business-details">-</p>
                            </div>

                            <div class="review-item" id="review-vehicle-info" style="display: none;">
                                <h4>Vehicle Information</h4>
                                <p id="review-vehicle-details">-</p>
                            </div>

                            <div class="review-item">
                                <h4>Banking Information</h4>
                                <p id="review-banking-info">-</p>
                            </div>

                            <div class="review-item">
                                <h4>Documents</h4>
                                <p id="review-documents">-</p>
                            </div>
                        </div>



                        <div class="step-actions">
                            <button type="button" class="btn btn-outline" id="back-step-6">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="btn btn-primary" id="submit-registration">
                                <i class="fas fa-paper-plane"></i> Submit Registration
                            </button>
                        </div>
                    </div>
                </div>

                <form class="registration-form" id="registration-form" method="POST" action="carrier-registration.php" enctype="multipart/form-data" style="display: none;">
                    <input type="hidden" name="complete_registration" value="1">
                    <input type="hidden" name="carrier_type" id="form_carrier_type" value="">
                    <!-- Form fields will be populated by JavaScript -->
                </form>
            </div>
        </div>
    </div>

    <script>
        // Multi-step form functionality
        let currentStep = 1;
        let selectedCarrierType = 'individual';
        let formData = {};

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            setupEventListeners();
        });

        function initializeForm() {
            // Set initial carrier type
            const carrierTypeRadios = document.querySelectorAll('input[name="carrier_type"]');
            carrierTypeRadios.forEach(radio => {
                if (radio.checked) {
                    selectedCarrierType = radio.value;
                }
            });

            // Show first step
            showStep(1);
            updateProgress();
        }

        function setupEventListeners() {
            // Carrier type selection
            document.querySelectorAll('input[name="carrier_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    selectedCarrierType = this.value;
                    updateCarrierTypeDisplay();
                });
            });

            // Step navigation buttons
            document.getElementById('continue-step-1').addEventListener('click', () => nextStep());
            document.getElementById('continue-step-2').addEventListener('click', () => nextStep());
            document.getElementById('continue-step-3').addEventListener('click', () => nextStep());
            document.getElementById('continue-step-4').addEventListener('click', () => nextStep());
            document.getElementById('continue-step-5').addEventListener('click', () => nextStep());

            // Back buttons
            document.getElementById('back-step-2').addEventListener('click', () => previousStep());
            document.getElementById('back-step-3').addEventListener('click', () => previousStep());
            document.getElementById('back-step-4').addEventListener('click', () => previousStep());
            document.getElementById('back-step-5').addEventListener('click', () => previousStep());
            document.getElementById('back-step-6').addEventListener('click', () => previousStep());

            // Submit button
            document.getElementById('submit-registration').addEventListener('click', submitForm);

            // Real-time validation
            setupValidation();

            // Phone formatting
            setupPhoneFormatting();
        }

        function showStep(stepNumber) {
            // Hide all steps
            document.querySelectorAll('.form-step').forEach(step => {
                step.style.display = 'none';
            });

            // Show current step
            const currentStepElement = document.getElementById(`step-${stepNumber}`);
            if (currentStepElement) {
                currentStepElement.style.display = 'block';
            }

            currentStep = stepNumber;
            updateProgress();

            // Update step-specific content
            if (stepNumber === 3) {
                updateStep3Content();
            } else if (stepNumber === 4) {
                updateStep4Content();
            } else if (stepNumber === 6) {
                populateReviewSection();
            }
        }

        function nextStep() {
            if (validateCurrentStep()) {
                collectStepData(currentStep);
                showStep(currentStep + 1);
            }
        }

        function previousStep() {
            showStep(currentStep - 1);
        }

        function updateProgress() {
            const progressFill = document.querySelector('.progress-fill');
            const progress = (currentStep / 6) * 100;
            progressFill.style.width = progress + '%';
        }

        function updateCarrierTypeDisplay() {
            // Update step 3 title and description based on carrier type
            const titleElement = document.getElementById('step-3-title');
            const descElement = document.getElementById('step-3-description');

            if (selectedCarrierType === 'individual') {
                titleElement.textContent = 'Vehicle Details';
                descElement.textContent = 'Provide your vehicle information';
            } else {
                titleElement.textContent = 'Business Details';
                descElement.textContent = 'Provide your business information';
            }
        }

        function updateStep3Content() {
            const individualFields = document.getElementById('individual-fields');
            const companyFields = document.getElementById('company-fields');

            if (selectedCarrierType === 'individual') {
                individualFields.style.display = 'block';
                companyFields.style.display = 'none';
            } else {
                individualFields.style.display = 'none';
                companyFields.style.display = 'block';
            }
        }

        function updateStep4Content() {
            const individualDocs = document.getElementById('individual-documents');
            const companyDocs = document.getElementById('company-documents');

            if (selectedCarrierType === 'individual') {
                individualDocs.style.display = 'block';
                companyDocs.style.display = 'none';
            } else {
                individualDocs.style.display = 'none';
                companyDocs.style.display = 'block';
            }
        }

        function validateCurrentStep() {
            let isValid = true;
            const requiredFields = getRequiredFieldsForStep(currentStep);

            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && !field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    isValid = false;
                } else if (field) {
                    field.style.borderColor = '#ddd';
                }
            });



            if (!isValid) {
                alert('Please fill in all required fields.');
            }

            return isValid;
        }

        function getRequiredFieldsForStep(step) {
            const requiredFields = {
                1: ['carrier_individual', 'carrier_company'], // At least one must be selected
                2: ['full_name', 'email', 'phone'],
                3: selectedCarrierType === 'individual'
                    ? ['vehicle_plate_number', 'vehicle_type']
                    : ['legal_business_name', 'business_registration_number', 'tin_number', 'type_of_business', 'total_trucks', 'company_region', 'company_city', 'company_email', 'company_phone', 'owner_manager_name'],
                4: [], // Documents are optional but recommended
                5: ['bank_name', 'account_number', 'account_holder_name'],
                6: []
            };

            return requiredFields[step] || [];
        }

        function collectStepData(step) {
            const stepData = {};

            // Collect data from current step
            const stepElement = document.getElementById(`step-${step}`);
            const inputs = stepElement.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                if (input.name) {
                    if (input.type === 'radio') {
                        if (input.checked) {
                            stepData[input.name] = input.value;
                        }
                    } else if (input.type === 'checkbox') {
                        if (input.checked) {
                            if (!stepData[input.name]) {
                                stepData[input.name] = [];
                            }
                            stepData[input.name].push(input.value);
                        }
                    } else if (input.type === 'file') {
                        // Files will be handled separately during form submission
                        stepData[input.name] = input.files[0] || null;
                    } else {
                        stepData[input.name] = input.value;
                    }
                }
            });

            // Merge with existing form data
            formData = { ...formData, ...stepData };
        }

        function populateReviewSection() {
            // Carrier Type
            const carrierTypeText = selectedCarrierType === 'individual' ? 'Individual Owner-Operator' : 'Company/Fleet Operator';
            document.getElementById('review-carrier-type').textContent = carrierTypeText;

            // Personal Information
            const personalInfo = `${formData.full_name || ''}, ${formData.email || ''}, ${formData.phone || ''}`;
            document.getElementById('review-personal-info').textContent = personalInfo;

            // Business/Vehicle Information
            if (selectedCarrierType === 'individual') {
                document.getElementById('review-vehicle-info').style.display = 'block';
                document.getElementById('review-business-info').style.display = 'none';
                const vehicleInfo = `Plate: ${formData.vehicle_plate_number || ''}, Type: ${formData.vehicle_type || ''}`;
                document.getElementById('review-vehicle-details').textContent = vehicleInfo;
            } else {
                document.getElementById('review-vehicle-info').style.display = 'none';
                document.getElementById('review-business-info').style.display = 'block';
                const businessInfo = `${formData.legal_business_name || ''}, ${formData.company_city || ''}, ${formData.total_trucks || 0} trucks`;
                document.getElementById('review-business-details').textContent = businessInfo;
            }

            // Banking Information
            const bankingInfo = `${formData.bank_name || ''}, Account: ${formData.account_number || ''}`;
            document.getElementById('review-banking-info').textContent = bankingInfo;

            // Documents
            const documents = [];
            if (selectedCarrierType === 'individual') {
                if (formData.defensive_driving_cert) documents.push('Defensive Driving Certificate');
                if (formData.vehicle_registration_cert) documents.push('Vehicle Registration');
                if (formData.vehicle_insurance_cert) documents.push('Vehicle Insurance');
            } else {
                if (formData.business_license_upload) documents.push('Business License');
                if (formData.tin_certificate_upload) documents.push('TIN Certificate');
                if (formData.id_passport_upload) documents.push('ID/Passport');
                if (formData.bank_statement_upload) documents.push('Bank Statement');
                if (formData.safety_compliance_cert) documents.push('Safety Compliance');
            }

            document.getElementById('review-documents').textContent = documents.length > 0 ? documents.join(', ') : 'No documents uploaded';
        }

        function submitForm() {
            if (!validateCurrentStep()) {
                return;
            }

            // Collect final data
            collectStepData(6);

            // Populate hidden form
            const hiddenForm = document.getElementById('registration-form');
            hiddenForm.innerHTML = '<input type="hidden" name="complete_registration" value="1">';

            // Add carrier type
            const carrierTypeInput = document.createElement('input');
            carrierTypeInput.type = 'hidden';
            carrierTypeInput.name = 'carrier_type';
            carrierTypeInput.value = selectedCarrierType;
            hiddenForm.appendChild(carrierTypeInput);

            // Add all form data
            Object.keys(formData).forEach(key => {
                if (key !== 'carrier_type' && formData[key] !== null && formData[key] !== undefined) {
                    if (Array.isArray(formData[key])) {
                        formData[key].forEach(value => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key + '[]';
                            input.value = value;
                            hiddenForm.appendChild(input);
                        });
                    } else if (formData[key] instanceof File) {
                        // Files need special handling - copy to hidden form
                        const fileInput = document.getElementById(key);
                        if (fileInput && fileInput.files.length > 0) {
                            // Create a new file input in the hidden form
                            const newFileInput = document.createElement('input');
                            newFileInput.type = 'file';
                            newFileInput.name = key;
                            newFileInput.style.display = 'none';
                            newFileInput.files = fileInput.files;
                            hiddenForm.appendChild(newFileInput);
                        }
                    } else {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = formData[key];
                        hiddenForm.appendChild(input);
                    }
                }
            });

            // Submit the form
            hiddenForm.submit();
        }

        function setupValidation() {
            document.querySelectorAll('input[required], select[required]').forEach(field => {
                field.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = '#e74c3c';
                    } else {
                        this.style.borderColor = '#ddd';
                    }
                });

                field.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.style.borderColor = '#ddd';
                    }
                });
            });
        }

        function setupPhoneFormatting() {
            const phoneFields = ['phone', 'company_phone', 'emergency_contact_phone'];

            phoneFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        if (value.length >= 10) {
                            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                        } else if (value.length >= 6) {
                            value = value.replace(/(\d{3})(\d{3})/, '($1) $2-');
                        } else if (value.length >= 3) {
                            value = value.replace(/(\d{3})/, '($1) ');
                        }
                        e.target.value = value;
                    });
                }
            });
        }

        // Carrier type option styling
        document.querySelectorAll('.carrier-type-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    selectedCarrierType = radio.value;
                    updateCarrierTypeDisplay();

                    // Update visual selection
                    document.querySelectorAll('.carrier-type-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    this.classList.add('selected');
                }
            });
        });
    </script>

</body>
</html>
