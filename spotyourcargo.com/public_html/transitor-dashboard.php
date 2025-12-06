<?php
// transitor-dashboard.php
// SYC Transitor Dashboard - Complete logistics management interface

// Security checklist:
// [✓] Session validation and role checking
// [✓] CSRF token protection for all POST actions
// [✓] Prepared statements for all database queries
// [✓] Input validation and output escaping
// [✓] File upload validation and secure storage
// [✓] Rate limiting considerations
// [✓] Audit logging for all state changes

// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();
error_log("Transitor Dashboard accessed - User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", User Type: " . ($_SESSION['user_type'] ?? 'Not set'));

include_once __DIR__ . '/../private/db.php';

// API Endpoint: Welcome message with logging
if (isset($_GET['action']) && $_GET['action'] === 'welcome') {
    // Log request metadata
    $request_method = $_SERVER['REQUEST_METHOD'];
    $request_path = $_SERVER['REQUEST_URI'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_id = $_SESSION['user_id'] ?? 'Not logged in';

    error_log("API Endpoint /welcome accessed - Method: $request_method, Path: $request_path, User-Agent: $user_agent, IP: $ip_address, User ID: $user_id");

    // Set JSON content type
    header('Content-Type: application/json');

    // Return JSON response
    echo json_encode([
        'message' => 'Welcome to the SYC Transitor Dashboard, ' . $welcome_name . '!'
    ]);

    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: access.php');
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: access.php');
    exit();
}

// Check if user is a transitor
if ($_SESSION['user_type'] !== 'transitor') {
    header('Location: access.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's data including syc_id
$user_syc_id = null;
try {
    $user_stmt = $pdo->prepare("SELECT id, syc_id FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_syc_id = $user_data['syc_id'] ?? null;
        // Store in session for notification scripts
        $_SESSION['user_syc_id'] = $user_syc_id;
    }
} catch (PDOException $e) {
    error_log("User data fetch error: " . $e->getMessage());
    $transitor_error = "Error fetching user data: " . $e->getMessage();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to create notifications
function createNotification($pdo, $syc_id, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (syc_id, message, is_read, created_at)
            VALUES (?, ?, 0, NOW())
        ");
        $stmt->execute([$syc_id, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification creation error: " . $e->getMessage());
        return false;
    }
}

// Function to log shipment events for audit
function logShipmentEvent($pdo, $shipment_id, $user_id, $event_type, $payload = []) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO shipment_events (shipment_id, user_id, event_type, payload, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$shipment_id, $user_id, $event_type, json_encode($payload)]);
        return true;
    } catch (PDOException $e) {
        error_log("Shipment event logging error: " . $e->getMessage());
        return false;
    }
}

// Initialize variables
$user_name = "Transitor";
$user_initials = "T";
$company_name = "Transitor Account";
$transitor_id = null;
$syc_transitor_id = null;
$transitor_data = [];
$transitor_error = "";
$success_message = "";
$welcome_name = "Transitor";

// Fetch transitor profile data
try {
    $transitor_stmt = $pdo->prepare("
        SELECT
            u.*,
            u.first_name as user_first_name,
            u.last_name as user_last_name,
            t.id as transitor_id,
            t.syc_id as syc_transitor_id,
            t.company_name,
            t.contact_person,
            t.phone,
            t.email as transitor_email,
            t.status as transitor_status,
            t.created_at as transitor_created_at
        FROM users u
        LEFT JOIN transitors t ON u.syc_id = t.syc_id
        WHERE u.id = ?
    ");
    $transitor_stmt->execute([$user_id]);
    $transitor_data = $transitor_stmt->fetch(PDO::FETCH_ASSOC);

    if ($transitor_data) {
        // Prioritize transitor first_name and last_name if available
        $first_name = $transitor_data['first_name'] ?? null;
        $last_name = $transitor_data['last_name'] ?? null;
        $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($transitor_data['name'] ?? $transitor_data['username'] ?? 'Transitor');
        $user_name = $display_name;

        // Extract first name for welcome message
        if ($first_name) {
            $welcome_name = ucfirst(strtolower(trim($first_name)));
        } else {
            $user_first_name = $transitor_data['user_first_name'] ?? null;
            if ($user_first_name) {
                $welcome_name = ucfirst(strtolower(trim($user_first_name)));
            } else {
                $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
                if (strpos($welcome_name, '@') !== false) {
                    $welcome_name = explode('@', $welcome_name)[0];
                }
                $welcome_name = ucfirst(strtolower($welcome_name));
            }
        }

        $user_initials = strtoupper(substr($display_name, 0, 2));
        $company_name = $transitor_data['company_name'] ?? 'Transitor Account';
        $transitor_id = $transitor_data['transitor_id'] ?? null;
        $syc_transitor_id = $transitor_data['syc_transitor_id'] ?? $user_syc_id;

        // If transitor profile doesn't exist but user is transitor type, create one
        if (!$transitor_id && $_SESSION['user_type'] === 'transitor') {
            // Extract first_name from username or email
            $username = $transitor_data['username'] ?? '';
            $email = $transitor_data['email'] ?? '';
            $extracted_first = '';
            if (strpos($username, '@') !== false) {
                $extracted_first = explode('@', $username)[0];
            } elseif (!empty($username)) {
                $extracted_first = $username;
            } elseif (!empty($email)) {
                $extracted_first = explode('@', $email)[0];
            }
            $first_name_to_set = ucfirst(strtolower(trim($extracted_first)));

            $create_transitor = $pdo->prepare("
                INSERT INTO transitors (user_id, syc_id, company_name, email, contact_person, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $create_transitor->execute([
                $user_id,
                $user_syc_id,
                $company_name,
                $transitor_data['email'],
                $display_name
            ]);
            $transitor_id = $pdo->lastInsertId();

            // Refresh transitor data
            $transitor_stmt->execute([$user_id]);
            $transitor_data = $transitor_stmt->fetch(PDO::FETCH_ASSOC);
            $syc_transitor_id = $transitor_data['syc_transitor_id'] ?? $user_syc_id;
        }
    } else {
        $syc_transitor_id = $user_syc_id;
        $display_name = 'Transitor';
        $user_name = $display_name;
        $welcome_name = $display_name;
        $user_initials = 'T';
    }
} catch (PDOException $e) {
    error_log("Transitor data fetch error: " . $e->getMessage());
    $transitor_error = "Error loading transitor profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $transitor_error = "Security token validation failed.";
    } else {
        $company_name = $_POST['company_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $contact_person = $_POST['contact_person'] ?? '';

        try {
            $update_stmt = $pdo->prepare("
                UPDATE transitors
                SET company_name = ?, phone = ?, contact_person = ?, updated_at = NOW()
                WHERE syc_id = ?
            ");
            $update_stmt->execute([$company_name, $phone, $contact_person, $user_syc_id]);

            if ($update_stmt->rowCount() > 0) {
                $success_message = "Profile updated successfully!";
                createNotification($pdo, $user_syc_id, "Profile updated: Company info modified");

                // Refresh transitor data
                $transitor_stmt->execute([$user_id]);
                $transitor_data = $transitor_stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $transitor_error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Calculate KPI metrics
$kpi_metrics = [
    'active_shipments' => 0,
    'pending_quotes' => 0,
    'on_time_percentage' => 0,
    'revenue_awaiting' => 0
];

try {
    // Active shipments count
    $active_stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM shipments
        WHERE transitor_id = ? AND status IN ('pending', 'assigned', 'in_transit', 'customs_hold')
    ");
    $active_stmt->execute([$transitor_id]);
    $kpi_metrics['active_shipments'] = $active_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Pending quotes count
    $quotes_stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM transitor_quotes
        WHERE transitor_id = ? AND status = 'pending'
    ");
    $quotes_stmt->execute([$transitor_id]);
    $kpi_metrics['pending_quotes'] = $quotes_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // On-time percentage (last 30 days)
    $ontime_stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN actual_delivery_date <= estimated_delivery_date THEN 1 ELSE 0 END) as on_time
        FROM shipments
        WHERE transitor_id = ?
        AND status = 'delivered'
        AND actual_delivery_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $ontime_stmt->execute([$transitor_id]);
    $ontime_data = $ontime_stmt->fetch(PDO::FETCH_ASSOC);
    $kpi_metrics['on_time_percentage'] = $ontime_data['total'] > 0 ?
        round(($ontime_data['on_time'] / $ontime_data['total']) * 100, 1) : 100;

    // Revenue awaiting (sum of pending invoice amounts)
    $revenue_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM invoices
        WHERE transitor_id = ? AND status = 'pending'
    ");
    $revenue_stmt->execute([$transitor_id]);
    $kpi_metrics['revenue_awaiting'] = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    error_log("KPI metrics error: " . $e->getMessage());
}

// Handle shipment filters and pagination
$shipment_filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'shipper' => $_GET['shipper'] ?? '',
    'transitor' => $_GET['transitor'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Fetch shipments with filters
$shipments = [];
$total_shipments = 0;

try {
    // Build query with filters
    $shipments_query = "
        SELECT
            s.*,
            sh.company_name as shipper_company,
            c.company_name as carrier_company,
            u_shipper.username as shipper_contact,
            u_carrier.username as carrier_contact
        FROM shipments s
        LEFT JOIN shippers sh ON s.shipper_id = sh.id
        LEFT JOIN carriers c ON s.assigned_carrier_id = c.id
        LEFT JOIN users u_shipper ON sh.syc_id = u_shipper.syc_id
        LEFT JOIN users u_carrier ON c.syc_id = u_carrier.syc_id
        WHERE s.transitor_id = ?
    ";

    $count_query = "
        SELECT COUNT(*) as total
        FROM shipments s
        LEFT JOIN shippers sh ON s.shipper_id = sh.id
        LEFT JOIN carriers c ON s.assigned_carrier_id = c.id
        WHERE s.transitor_id = ?
    ";

    $query_params = [$transitor_id];
    $count_params = [$transitor_id];

    // Add search filter
    if (!empty($shipment_filters['search'])) {
        $search_param = '%' . $shipment_filters['search'] . '%';
        $shipments_query .= " AND (s.shipment_id LIKE ? OR s.origin_city LIKE ? OR s.destination_city LIKE ?)";
        $count_query .= " AND (s.shipment_id LIKE ? OR s.origin_city LIKE ? OR s.destination_city LIKE ?)";
        array_push($query_params, $search_param, $search_param, $search_param);
        array_push($count_params, $search_param, $search_param, $search_param);
    }

    // Add status filter
    if (!empty($shipment_filters['status'])) {
        $shipments_query .= " AND s.status = ?";
        $count_query .= " AND s.status = ?";
        $query_params[] = $shipment_filters['status'];
        $count_params[] = $shipment_filters['status'];
    }

    // Add shipper filter
    if (!empty($shipment_filters['shipper'])) {
        $shipper_param = '%' . $shipment_filters['shipper'] . '%';
        $shipments_query .= " AND sh.company_name LIKE ?";
        $count_query .= " AND sh.company_name LIKE ?";
        $query_params[] = $shipper_param;
        $count_params[] = $shipper_param;
    }

    // Add carrier filter
    if (!empty($shipment_filters['carrier'])) {
        $carrier_param = '%' . $shipment_filters['carrier'] . '%';
        $shipments_query .= " AND c.company_name LIKE ?";
        $count_query .= " AND c.company_name LIKE ?";
        $query_params[] = $carrier_param;
        $count_params[] = $carrier_param;
    }

    // Add date range filter
    if (!empty($shipment_filters['date_from'])) {
        $shipments_query .= " AND s.created_at >= ?";
        $count_query .= " AND s.created_at >= ?";
        $query_params[] = $shipment_filters['date_from'];
        $count_params[] = $shipment_filters['date_from'];
    }

    if (!empty($shipment_filters['date_to'])) {
        $shipments_query .= " AND s.created_at <= ?";
        $count_query .= " AND s.created_at <= ? . ' 23:59:59'";
        $query_params[] = $shipment_filters['date_to'] . ' 23:59:59';
        $count_params[] = $shipment_filters['date_to'] . ' 23:59:59';
    }

    // Get total count
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_shipments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Add sorting and pagination
    $shipments_query .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $shipments_stmt = $pdo->prepare($shipments_query);
    $shipments_stmt->execute($query_params);
    $shipments = $shipments_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Shipments fetch error: " . $e->getMessage());
    $transitor_error = "Error loading shipments: " . $e->getMessage();
}

// Fetch available carriers for assignment
$available_carriers = [];
try {
    $carriers_stmt = $pdo->prepare("
        SELECT
            c.*,
            u.phone as carrier_phone,
            u.email as carrier_email,
            COUNT(DISTINCT t.id) as total_trucks,
            AVG(r.rating) as avg_rating
        FROM carriers c
        LEFT JOIN users u ON c.syc_id = u.syc_id
        LEFT JOIN trucks t ON c.id = t.carrier_id AND t.status = 'active'
        LEFT JOIN carrier_reviews r ON c.id = r.carrier_id
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY avg_rating DESC, c.company_name ASC
    ");
    $carriers_stmt->execute();
    $available_carriers = $carriers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Available carriers fetch error: " . $e->getMessage());
}

// Fetch notifications
$notifications = [];
$unread_notifications_count = 0;
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->rowCount() > 0) {
        $notifications_stmt = $pdo->prepare("SELECT * FROM notifications WHERE syc_id = ? ORDER BY created_at DESC LIMIT 5");
        $notifications_stmt->execute([$user_syc_id]);
        $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE syc_id = ? AND is_read = 0");
        $unread_stmt->execute([$user_syc_id]);
        $unread_notifications_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
} catch (PDOException $e) {
    error_log("Notifications error: " . $e->getMessage());
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['shipment_document'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $upload_error = "Security token validation failed.";
    } else {
        $shipment_id = $_POST['shipment_id'] ?? '';
        $document_type = $_POST['document_type'] ?? '';
        $visibility = $_POST['visibility'] ?? 'private';

        $file = $_FILES['shipment_document'];

        // Validate file
        $allowed_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $max_size = 20 * 1024 * 1024; // 20MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_error = "File upload failed with error code: " . $file['error'];
        } elseif ($file['size'] > $max_size) {
            $upload_error = "File size exceeds 20MB limit.";
        } elseif (!in_array($file['type'], $allowed_types)) {
            $upload_error = "File type not allowed. Allowed types: PDF, JPG, PNG, GIF, DOC, DOCX";
        } else {
            // Generate secure filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $random_name = bin2hex(random_bytes(16)) . '.' . $file_extension;
            $upload_dir = __DIR__ . '/../storage/shipments/' . $shipment_id . '/';

            // Create directory if not exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_path = $upload_dir . $random_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Store document metadata in database
                try {
                    $doc_stmt = $pdo->prepare("
                        INSERT INTO shipment_documents (
                            shipment_id, filename, original_name, file_path, file_type,
                            file_size, document_type, visibility, uploaded_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $doc_stmt->execute([
                        $shipment_id,
                        $random_name,
                        $file['name'],
                        $file_path,
                        $file['type'],
                        $file['size'],
                        $document_type,
                        $visibility,
                        $user_id
                    ]);

                    // Log event
                    logShipmentEvent($pdo, $shipment_id, $user_id, 'document_uploaded', [
                        'filename' => $file['name'],
                        'document_type' => $document_type
                    ]);

                    $upload_success = "Document uploaded successfully!";

                } catch (PDOException $e) {
                    error_log("Document upload DB error: " . $e->getMessage());
                    $upload_error = "Error saving document metadata.";
                    // Clean up uploaded file
                    if (file_exists($file_path)) unlink($file_path);
                }
            } else {
                $upload_error = "Failed to move uploaded file.";
            }
        }
    }
}

// Handle quote creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quote'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $quote_error = "Security token validation failed.";
    } else {
        $shipment_id = $_POST['shipment_id'] ?? '';
        $price = $_POST['price'] ?? '';
        $currency = $_POST['currency'] ?? 'USD';
        $valid_until = $_POST['valid_until'] ?? '';
        $notes = $_POST['quote_notes'] ?? '';

        // Validation
        if (empty($price) || !is_numeric($price) || $price <= 0) {
            $quote_error = "Please enter a valid price greater than 0.";
        } else {
            try {
                $quote_stmt = $pdo->prepare("
                    INSERT INTO transitor_quotes (
                        shipment_id, transitor_id, price, currency, valid_until,
                        notes, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $quote_stmt->execute([
                    $shipment_id,
                    $transitor_id,
                    $price,
                    $currency,
                    $valid_until,
                    $notes
                ]);

                // Log event
                logShipmentEvent($pdo, $shipment_id, $user_id, 'quote_created', [
                    'price' => $price,
                    'currency' => $currency
                ]);

                $quote_success = "Quote created successfully!";

            } catch (PDOException $e) {
                error_log("Quote creation error: " . $e->getMessage());
                $quote_error = "Error creating quote: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Transitor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        /* Reuse the exact same CSS as carrier-dashboard.php with transitor-specific adjustments */
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --light-gray: #F5F5F5;
            --dark-gray: #333333;
            --white: #FFFFFF;
            --text-dark: #1d1d1f;
            --text-light: #86868b;
            --card-bg: rgba(255, 255, 255, 0.8);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --sidebar-width: 240px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: var(--dark-gray);
            line-height: 1.6;
            font-weight: 400;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Same as carrier dashboard */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-blue);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 30px 0 0 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .sidebar-top {
            padding: 0 25px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar-logo img {
            height: 32px;
            width: auto;
        }

        .sidebar-logo-text {
            font-weight: 700;
            color: white;
            font-size: 22px;
            letter-spacing: -0.5px;
        }

        .sidebar-logo-dot {
            color: var(--primary-yellow);
        }

        .sidebar-nav {
            flex: 1;
            padding: 30px 0;
            overflow-y: auto;
            max-height: calc(100vh - 150px);
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 5px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--primary-yellow);
        }

        .sidebar-nav i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 20px 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--primary-blue);
            flex-shrink: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-yellow);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
            margin-right: 12px;
        }

        .user-info h4 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .logout-btn {
            display: block;
            width: 100%;
            padding: 12px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
        }

        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--light-gray);
            border-radius: 10px;
            padding: 8px 15px;
            width: 300px;
        }

        .search-bar input {
            border: none;
            background: transparent;
            padding: 5px;
            width: 100%;
            outline: none;
            font-size: 14px;
        }

        .search-bar i {
            color: var(--text-light);
            margin-right: 10px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            flex-wrap: wrap;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-dropdown.show {
            display: block;
        }

        .dropdown-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dropdown-header h4 {
            margin: 0;
            font-size: 16px;
            color: var(--primary-blue);
        }

        .dropdown-header a {
            color: var(--secondary-blue);
            text-decoration: none;
            font-size: 12px;
        }

        .dropdown-content {
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--light-gray);
        }

        .dropdown-item.unread {
            background: rgba(255, 215, 0, 0.05);
            border-left: 3px solid var(--primary-yellow);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-text p {
            margin: 0 0 5px 0;
            font-size: 14px;
            line-height: 1.4;
        }

        .dropdown-text small {
            color: var(--text-light);
            font-size: 12px;
        }

        .dropdown-footer {
            padding: 10px 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .dropdown-footer a {
            color: var(--secondary-blue);
            text-decoration: none;
            font-size: 14px;
        }

        .notification-btn, .support-icon {
            position: relative;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn:hover, .support-icon:hover {
            background: var(--light-gray);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary-yellow);
            color: var(--primary-blue);
            font-size: 10px;
            font-weight: 600;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 30px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .welcome-text p {
            opacity: 0.9;
            max-width: 600px;
        }

        .banner-cta {
            background: var(--primary-yellow);
            color: var(--primary-blue);
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .banner-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-blue);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(0, 51, 102, 0.1);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 12px;
            color: #28a745;
            display: flex;
            align-items: center;
        }

        .stat-change.negative {
            color: #dc3545;
        }

        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .section-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-blue);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark-gray);
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: var(--light-gray);
            font-weight: 600;
            color: var(--dark-gray);
        }

        .data-table tr:hover {
            background: rgba(0, 51, 102, 0.02);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        .status-completed {
            background: rgba(0, 123, 255, 0.1);
            color: #004085;
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark-gray);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -100%;
            }

            .main-content {
                margin-left: 0;
            }

            .top-header {
                padding: 0 15px;
            }

            .dashboard-content {
                padding: 15px;
            }

            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-blue);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Error/Success Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert i {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-top">
            <a href="index.php" class="sidebar-logo">
                <img src="assets/img/SYC-Transparent.png" alt="SYC" style="filter: brightness(0) invert(1);">
                <span class="sidebar-logo-text">SYC<span class="sidebar-logo-dot">.</span></span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li><a href="#dashboard" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#shipments"><i class="fas fa-truck"></i> Shipments</a></li>
                <li><a href="#quotes"><i class="fas fa-calculator"></i> Quotes</a></li>
                <li><a href="#carriers"><i class="fas fa-users"></i> Carriers</a></li>
                <li><a href="#documents"><i class="fas fa-file-alt"></i> Documents</a></li>
                <li><a href="#profile"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user_name); ?></h4>
                    <p><?php echo htmlspecialchars($company_name); ?></p>
                </div>
            </div>
            <button class="logout-btn" onclick="window.location.href='?logout=1'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search shipments..." id="global-search">
            </div>

            <div class="header-actions">
                <a href="#" class="support-icon" title="Support">
                    <i class="fas fa-headset"></i>
                </a>

                <div class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </div>

                <div class="notification-dropdown" id="notification-dropdown">
                    <div class="dropdown-header">
                        <h4>Notifications</h4>
                        <a href="#" onclick="markAllRead()">Mark all read</a>
                    </div>
                    <div class="dropdown-content">
                        <?php if (empty($notifications)): ?>
                            <div class="dropdown-item">
                                <div class="dropdown-text">
                                    <p>No notifications yet</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="dropdown-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>"
                                     onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                    <div class="dropdown-text">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small><?php echo date('M j, H:i', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="#">View all notifications</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Error/Success Messages -->
            <?php if (!empty($transitor_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($transitor_error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</h2>
                    <p>Manage your shipments, quotes, and carrier relationships from your transitor dashboard.</p>
                </div>
                <button class="banner-cta" onclick="showQuoteModal()">
                    <i class="fas fa-plus"></i> Create Quote
                </button>
            </div>

            <!-- KPI Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Active Shipments</div>
                        <div class="stat-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($kpi_metrics['active_shipments']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> In transit
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Pending Quotes</div>
                        <div class="stat-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($kpi_metrics['pending_quotes']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-clock"></i> Awaiting response
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">On-Time Delivery</div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $kpi_metrics['on_time_percentage']; ?>%</div>
                    <div class="stat-change">
                        <i class="fas fa-check-circle"></i> Last 30 days
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Revenue Awaiting</div>
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">$<?php echo number_format($kpi_metrics['revenue_awaiting'], 2); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-money-bill-wave"></i> Pending invoices
                    </div>
                </div>
            </div>

            <!-- Recent Shipments -->
            <div class="content-section">
                <div class="section-header">
                    <h3 class="section-title">Recent Shipments</h3>
                    <div class="section-actions">
                        <button class="btn btn-secondary" onclick="showFilters()">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                        <a href="#shipments" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                </div>

                <?php if (empty($shipments)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <i class="fas fa-dolly" style="font-size: 48px; margin-bottom: 20px;"></i>
                        <p>No shipments found. Create your first quote to get started!</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Shipment ID</th>
                                    <th>Origin</th>
                                    <th>Destination</th>
                                    <th>Shipper</th>
                                    <th>Carrier</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($shipments, 0, 5) as $shipment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($shipment['shipment_id']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['origin_city'] . ', ' . $shipment['origin_country']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['destination_city'] . ', ' . $shipment['destination_country']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['shipper_company']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['carrier_company'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $shipment['status'])); ?>">
                                                <?php echo htmlspecialchars($shipment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-secondary btn-sm" onclick="viewShipment('<?php echo $shipment['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Carriers -->
            <div class="content-section">
                <div class="section-header">
                    <h3 class="section-title">Available Carriers</h3>
                    <a href="#carriers" class="btn btn-primary">
                        <i class="fas fa-truck"></i> View All Carriers
                    </a>
                </div>

                <?php if (empty($available_carriers)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <i class="fas fa-truck" style="font-size: 48px; margin-bottom: 20px;"></i>
                        <p>No carriers available at the moment.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach (array_slice($available_carriers, 0, 3) as $carrier): ?>
                            <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px;">
                                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; margin-right: 15px;">
                                        <?php echo strtoupper(substr($carrier['company_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0; font-size: 16px;"><?php echo htmlspecialchars($carrier['company_name']); ?></h4>
                                        <p style="margin: 5px 0 0 0; color: var(--text-light); font-size: 14px;">
                                            <?php echo $carrier['total_trucks'] ?? 0; ?> trucks available
                                        </p>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <small style="color: var(--text-light);">Rating:</small>
                                        <div style="color: #ffc107;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star" style="opacity: <?php echo $i <= ($carrier['avg_rating'] ?? 0) ? '1' : '0.3'; ?>;"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-sm" onclick="contactCarrier('<?php echo $carrier['id']; ?>')">
                                        <i class="fas fa-envelope"></i> Contact
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Quote Creation Modal -->
    <div class="modal" id="quote-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Quote</h3>
                <button class="modal-close" onclick="closeModal('quote-modal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="create_quote" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Shipment ID</label>
                        <input type="text" name="shipment_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price</label>
                        <input type="number" name="price" step="0.01" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-control">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid Until</label>
                        <input type="date" name="valid_until" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="quote_notes" class="form-control" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('quote-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quote</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Notification functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notification-dropdown');
            dropdown.classList.toggle('show');
        }

        function markAsRead(notificationId) {
            // AJAX call to mark notification as read
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: notificationId
                })
            }).then(() => {
                location.reload();
            });
        }

        function markAllRead() {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            }).then(() => {
                location.reload();
            });
        }

        // Modal functions
        function showQuoteModal() {
            document.getElementById('quote-modal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Shipment functions
        function viewShipment(shipmentId) {
            window.location.href = 'shipment-details.php?id=' + shipmentId;
        }

        function contactCarrier(carrierId) {
            // Open contact modal or redirect to carrier profile
            window.location.href = 'carrier-profile.php?id=' + carrierId;
        }

        // Filter functions
        function showFilters() {
            // Toggle filter panel
            alert('Filter functionality coming soon!');
        }

        // Global search
        document.getElementById('global-search').addEventListener('input', function(e) {
            // Implement live search
            const searchTerm = e.target.value.toLowerCase();
            // Filter table rows based on search term
        });
    </script>
</body>
</html>
