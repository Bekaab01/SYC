<?php
// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();
error_log("Dashboard accessed - User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", User Type: " . ($_SESSION['user_type'] ?? 'Not set'));

include_once __DIR__ . '/../private/db.php';

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

// Check if user is a carrier
if ($_SESSION['user_type'] !== 'carrier') {
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

        // Check if carrier has completed registration
        $carrier_check_stmt = $pdo->prepare("SELECT id FROM carriers WHERE user_id = ?");
        $carrier_check_stmt->execute([$user_id]);
        $carrier_exists = $carrier_check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$carrier_exists) {
            // Carrier hasn't completed registration, redirect to registration page
            header('Location: carrier-registration.php');
            exit();
        }
    }
} catch (PDOException $e) {
    error_log("User data fetch error: " . $e->getMessage());
    $carrier_error = "Error fetching user data: " . $e->getMessage();
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

// Initialize variables
$user_name = "User";
$user_initials = "U";
$company_name = "Carrier Account";
$carrier_id = null;
$syc_carrier_id = null;
$carrier_data = [];
$trucks = [];
$carrier_error = "";
$success_message = "";

// Fetch carrier profile data
try {
    $carrier_stmt = $pdo->prepare("
        SELECT
            u.*,
            u.first_name as user_first_name,  -- Add this line
            u.last_name as user_last_name,    -- Add this line
            c.id as carrier_id,
            c.syc_id as syc_carrier_id,
            c.company_name,
            c.contact_person,
            c.phone,
            c.email as carrier_email,
            c.status as carrier_status,
            c.created_at as carrier_created_at,
            c.first_name,
            c.last_name
        FROM users u
        LEFT JOIN carriers c ON u.syc_id = c.syc_id
        WHERE u.id = ?
    ");
    $carrier_stmt->execute([$user_id]);
    $carrier_data = $carrier_stmt->fetch(PDO::FETCH_ASSOC);
    
if ($carrier_data) {
    // Prioritize carrier first_name and last_name if available
    $first_name = $carrier_data['first_name'] ?? null;
    $last_name = $carrier_data['last_name'] ?? null;
    $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($carrier_data['name'] ?? $carrier_data['username'] ?? 'User');
    $user_name = $display_name; // For sidebar and other uses

    // Extract first name for welcome message - FINAL FIXED VERSION
    if ($first_name) {
        $welcome_name = ucfirst(strtolower(trim($first_name)));
    } else {
        // Try to get first name from users table if not in carriers table
        $user_first_name = $carrier_data['user_first_name'] ?? null;
        if ($user_first_name) {
            $welcome_name = ucfirst(strtolower(trim($user_first_name)));
        } else {
            // Fallback to display name extraction
            $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
            // If welcome_name contains @ (email), extract username part
            if (strpos($welcome_name, '@') !== false) {
                $welcome_name = explode('@', $welcome_name)[0];
            }
            // Capitalize first letter
            $welcome_name = ucfirst(strtolower($welcome_name));
        }
    }

    $user_initials = strtoupper(substr($display_name, 0, 2));
    $company_name = $carrier_data['company_name'] ?? 'Carrier Account';
    $carrier_id = $carrier_data['carrier_id'] ?? null;
    $syc_carrier_id = $carrier_data['syc_carrier_id'] ?? $user_syc_id;

        // If carrier profile doesn't exist but user is carrier type, create one
        if (!$carrier_id && $_SESSION['user_type'] === 'carrier') {
            // Check if carrier already exists for this user_id to prevent duplicate
            $check_carrier_exists = $pdo->prepare("SELECT id FROM carriers WHERE user_id = ?");
            $check_carrier_exists->execute([$user_id]);
            $existing_carrier = $check_carrier_exists->fetch(PDO::FETCH_ASSOC);

            if (!$existing_carrier) {
                // Extract first_name from username or email
                $username = $carrier_data['username'] ?? '';
                $email = $carrier_data['email'] ?? '';
                $extracted_first = '';
                if (strpos($username, '@') !== false) {
                    $extracted_first = explode('@', $username)[0];
                } elseif (!empty($username)) {
                    $extracted_first = $username;
                } elseif (!empty($email)) {
                    $extracted_first = explode('@', $email)[0];
                }
                $first_name_to_set = ucfirst(strtolower(trim($extracted_first)));

                $create_carrier = $pdo->prepare("
                    INSERT INTO carriers (user_id, syc_id, company_name, email, contact_person, first_name, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $create_carrier->execute([
                    $user_id,
                    $user_syc_id,
                    $company_name,
                    $carrier_data['email'],
                    $display_name,
                    $first_name_to_set
                ]);
                $carrier_id = $pdo->lastInsertId();
            }

            // Refresh carrier data
            $carrier_stmt->execute([$user_id]);
            $carrier_data = $carrier_stmt->fetch(PDO::FETCH_ASSOC);
            $syc_carrier_id = $carrier_data['syc_carrier_id'] ?? $user_syc_id;

            // Recompute names after refresh
            $first_name = $carrier_data['first_name'] ?? null;
            $last_name = $carrier_data['last_name'] ?? null;
            $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($carrier_data['name'] ?? $carrier_data['username'] ?? 'User');
            $user_name = $display_name;
            if ($first_name) {
                $welcome_name = ucfirst(strtolower(trim($first_name)));
            } else {
                $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
                if (strpos($welcome_name, '@') !== false) {
                    $welcome_name = explode('@', $welcome_name)[0];
                }
                $welcome_name = ucfirst(strtolower($welcome_name));
            }
            $user_initials = strtoupper(substr($display_name, 0, 2));
        }
    } else {
        $syc_carrier_id = $user_syc_id;
        $display_name = 'User';
        $user_name = $display_name;
        $welcome_name = $display_name;
        $user_initials = 'U';
    }
} catch (PDOException $e) {
    error_log("Carrier data fetch error: " . $e->getMessage());
    $carrier_error = "Error loading carrier profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $company_name = $_POST['company_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    
    try {
        $update_stmt = $pdo->prepare("
            UPDATE carriers 
            SET company_name = ?, phone = ?, contact_person = ?, updated_at = NOW() 
            WHERE syc_id = ?
        ");
        $update_stmt->execute([$company_name, $phone, $contact_person, $user_syc_id]);
        
        if ($update_stmt->rowCount() > 0) {
            $success_message = "Profile updated successfully!";

            //  ADD NOTIFICATION FOR PROFILE UPDATE
            $notification_message = "Profile updated: Company info modified";
            createNotification($pdo, $user_syc_id, $notification_message);

            // Refresh carrier data
            $carrier_stmt->execute([$user_id]);
            $carrier_data = $carrier_stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $carrier_error = "Error updating profile: " . $e->getMessage();
    }
}
// Example: Add welcome notification for first-time users (only once)
if ($carrier_data && $user_syc_id) {
    // Check if welcome notification already exists for this user
    $welcome_check_stmt = $pdo->prepare("SELECT id FROM notifications WHERE syc_id = ? AND message LIKE 'Welcome to SYC Carrier Dashboard%' LIMIT 1");
    $welcome_check_stmt->execute([$user_syc_id]);
    $existing_welcome = $welcome_check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_welcome) {
        $welcome_notification = "Welcome to SYC Carrier Dashboard! Get started by adding your trucks and exploring available loads.";
        createNotification($pdo, $user_syc_id, $welcome_notification);
    }
}

// Handle truck registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_truck'])) {
    $license_plate = $_POST['license_plate'] ?? '';
    $truck_model = $_POST['truck_model'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $status = 'active';
    
    if ($carrier_id) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO trucks (carrier_id, license_plate, truck_model, capacity, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$carrier_id, $license_plate, $truck_model, $capacity, $status]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "Truck registered successfully!";

                //  ADD NOTIFICATION FOR TRUCK REGISTRATION
                $notification_message = "New truck registered: " . htmlspecialchars($license_plate) . " (" . htmlspecialchars($truck_model) . ")";
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $carrier_error = "Error registering truck. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Truck registration error: " . $e->getMessage());
            $carrier_error = "Error registering truck: " . $e->getMessage();
        }
    } else {
        $carrier_error = "Cannot register truck. Carrier profile not found.";
    }
}

// Fetch available loads for carriers
$available_loads = [];
$loads_filters = [
    'origin' => $_GET['origin'] ?? '',
    'destination' => $_GET['destination'] ?? '',
    'weight_min' => $_GET['weight_min'] ?? '',
    'weight_max' => $_GET['weight_max'] ?? '',
    'cargo_type' => $_GET['cargo_type'] ?? ''
];

try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'cargo'");
    if ($table_check->rowCount() > 0) {
        // Build query with filters
        $loads_query = "
            SELECT c.*, 
                   s.company_name as shipper_company,
                   s.contact_person as shipper_contact,
                   u.phone as shipper_phone,
                   u.email as shipper_email
            FROM cargo c
            LEFT JOIN shippers s ON c.shipper_id = s.id
            LEFT JOIN users u ON s.syc_id = u.syc_id
            WHERE c.status = 'Pending'
        ";
        
        $query_params = [];
        
        // Add origin filter
        if (!empty($loads_filters['origin'])) {
            $loads_query .= " AND (c.pickup_location LIKE ?)";
            $origin_param = '%' . $loads_filters['origin'] . '%';
            $query_params[] = $origin_param;
        }
        
        // Add destination filter
        if (!empty($loads_filters['destination'])) {
            $loads_query .= " AND (c.dropoff_location LIKE ?)";
            $destination_param = '%' . $loads_filters['destination'] . '%';
            $query_params[] = $destination_param;
        }
        
        // Add weight filters
        if (!empty($loads_filters['weight_min'])) {
            $loads_query .= " AND c.weight >= ?";
            $query_params[] = $loads_filters['weight_min'];
        }
        
        if (!empty($loads_filters['weight_max'])) {
            $loads_query .= " AND c.weight <= ?";
            $query_params[] = $loads_filters['weight_max'];
        }
        
        // Add cargo type filter
        if (!empty($loads_filters['cargo_type'])) {
            $loads_query .= " AND c.cargo_type = ?";
            $query_params[] = $loads_filters['cargo_type'];
        }
        
        $loads_query .= " ORDER BY c.created_at DESC";
        
        $loads_stmt = $pdo->prepare($loads_query);
        $loads_stmt->execute($query_params);
        $available_loads = $loads_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Available loads fetch error: " . $e->getMessage());
}

// Get unique cargo types for filter dropdown
$cargo_types = [];
try {
    $types_stmt = $pdo->query("SELECT DISTINCT cargo_type FROM cargo WHERE cargo_type IS NOT NULL AND cargo_type != ''");
    $cargo_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Cargo types fetch error: " . $e->getMessage());
}

// Handle load bid submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bid'])) {
    $load_id = $_POST['load_id'] ?? '';
    $bid_amount = $_POST['bid_amount'] ?? '';
    $bid_notes = $_POST['bid_notes'] ?? '';
    
    // Validation
    if (empty($bid_amount) || !is_numeric($bid_amount) || $bid_amount <= 0) {
        $bid_error_message = "Please enter a valid bid amount greater than 0.";
    } else {
        try {
            // Check if bids table exists, create if not
            $table_check = $pdo->query("SHOW TABLES LIKE 'bids'");
            if ($table_check->rowCount() == 0) {
                // Create bids table
                $create_bids_table = $pdo->prepare("
                    CREATE TABLE bids (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        load_id INT NOT NULL,
                        carrier_id INT NOT NULL,
                        bid_amount DECIMAL(10,2) NOT NULL,
                        bid_notes TEXT,
                        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                $create_bids_table->execute();
            }
            
            // Insert bid
            $bid_stmt = $pdo->prepare("
                INSERT INTO bids (load_id, carrier_id, bid_amount, bid_notes, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $bid_stmt->execute([$load_id, $carrier_id, $bid_amount, $bid_notes]);
            
            if ($bid_stmt->rowCount() > 0) {
                $bid_success_message = "Bid submitted successfully!";
                
                // Create notification for carrier
                $notification_message = "Your bid of $" . $bid_amount . " for load " . $load_id . " has been submitted.";
                createNotification($pdo, $user_syc_id, $notification_message);
                
                // Notify shipper
                $shipper_stmt = $pdo->prepare("
                    SELECT s.syc_id 
                    FROM cargo c 
                    LEFT JOIN shippers s ON c.shipper_id = s.id 
                    WHERE c.id = ?
                ");
                $shipper_stmt->execute([$load_id]);
                $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($shipper_data && $shipper_data['syc_id']) {
                    $shipper_notification = "New bid received for your load " . $load_id . " from carrier " . htmlspecialchars($company_name) . " for $" . $bid_amount;
                    createNotification($pdo, $shipper_data['syc_id'], $shipper_notification);
                }
                
            } else {
                $bid_error_message = "Error submitting bid. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Bid submission error: " . $e->getMessage());
            $bid_error_message = "Error submitting bid: " . $e->getMessage();
        }
    }
}

// Fetch carrier's active bids
$active_bids = [];
if ($carrier_id) {
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'bids'");
        if ($table_check->rowCount() > 0) {
            $bids_stmt = $pdo->prepare("
                SELECT b.*, c.cargo_id, c.description, c.pickup_location, c.dropoff_location
                FROM bids b
                LEFT JOIN cargo c ON b.load_id = c.id
                WHERE b.carrier_id = ? AND b.status = 'pending'
                ORDER BY b.created_at DESC
            ");
            $bids_stmt->execute([$carrier_id]);
            $active_bids = $bids_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Active bids fetch error: " . $e->getMessage());
    }
}

// Fetch invoices for Invoices tab
$invoices = [];
if ($carrier_id) {
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'invoices'");
        if ($table_check->rowCount() > 0) {
            $invoices_stmt = $pdo->prepare("
                SELECT i.*, c.cargo_id, c.description, c.pickup_location, c.dropoff_location
                FROM invoices i
                LEFT JOIN cargo c ON i.load_id = c.id
                WHERE i.carrier_id = ?
                ORDER BY i.created_at DESC
            ");
            $invoices_stmt->execute([$carrier_id]);
            $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Invoices fetch error: " . $e->getMessage());
    }
}

// Performance metrics
$performance_metrics = [
    'total_bids' => 0,
    'accepted_bids' => 0,
    'acceptance_rate' => 0,
    'avg_bid_amount' => 0,
    'total_earnings' => 0
];
if ($carrier_id) {
    try {
        // Total bids
        $total_bids_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids WHERE carrier_id = ?");
        $total_bids_stmt->execute([$carrier_id]);
        $performance_metrics['total_bids'] = $total_bids_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Accepted bids
        $accepted_bids_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids WHERE carrier_id = ? AND status = 'accepted'");
        $accepted_bids_stmt->execute([$carrier_id]);
        $performance_metrics['accepted_bids'] = $accepted_bids_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Acceptance rate
        $performance_metrics['acceptance_rate'] = $performance_metrics['total_bids'] > 0 ? round(($performance_metrics['accepted_bids'] / $performance_metrics['total_bids']) * 100, 1) : 0;

        // Avg bid amount
        $avg_bid_stmt = $pdo->prepare("SELECT AVG(bid_amount) as avg FROM bids WHERE carrier_id = ?");
        $avg_bid_stmt->execute([$carrier_id]);
        $avg = $avg_bid_stmt->fetch(PDO::FETCH_ASSOC)['avg'];
        $performance_metrics['avg_bid_amount'] = $avg ? round($avg, 2) : 0;

        // Total earnings (sum of accepted bids)
        $earnings_stmt = $pdo->prepare("SELECT SUM(bid_amount) as total FROM bids WHERE carrier_id = ? AND status = 'accepted'");
        $earnings_stmt->execute([$carrier_id]);
        $performance_metrics['total_earnings'] = $earnings_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Performance metrics error: " . $e->getMessage());
    }
}

// Fetch carrier's my loads (accepted bids)
$my_loads = [];
if ($carrier_id) {
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'bids'");
        if ($table_check->rowCount() > 0) {
            $my_loads_stmt = $pdo->prepare("
                SELECT c.*, b.bid_amount, b.created_at as bid_date, b.status as bid_status,
                       s.company_name as shipper_company,
                       s.contact_person as shipper_contact,
                       u.phone as shipper_phone,
                       u.email as shipper_email
                FROM cargo c
                LEFT JOIN bids b ON c.id = b.load_id
                LEFT JOIN shippers s ON c.shipper_id = s.id
                LEFT JOIN users u ON s.syc_id = u.syc_id
                WHERE b.carrier_id = ? AND b.status = 'accepted'
                ORDER BY b.created_at DESC
            ");
            $my_loads_stmt->execute([$carrier_id]);
            $my_loads = $my_loads_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("My loads fetch error: " . $e->getMessage());
    }
}

// Fetch carrier's trucks
if ($carrier_id) {
    try {
        $trucks_stmt = $pdo->prepare("
            SELECT * FROM trucks 
            WHERE carrier_id = ? 
            ORDER BY created_at DESC
        ");
        $trucks_stmt->execute([$carrier_id]);
        $trucks = $trucks_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Trucks fetch error: " . $e->getMessage());
        $trucks = [];
    }
} else {
    $trucks = [];
}

// Initialize notification and message variables
$notifications = [];
$messages = [];
$unread_notifications_count = 0;
$unread_messages_count = 0;

// Fetch notifications
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

// Fetch messages
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($table_check->rowCount() > 0) {
        $messages_stmt = $pdo->prepare("
            SELECT m.*, u.username as sender_name
            FROM messages m
            LEFT JOIN users u ON m.sender_syc_id = u.syc_id
            WHERE m.receiver_syc_id = ?
            ORDER BY m.created_at DESC
            LIMIT 5
        ");
        $messages_stmt->execute([$user_syc_id]);
        $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_syc_id = ? AND is_read = 0");
        $unread_stmt->execute([$user_syc_id]);
        $unread_messages_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
} catch (PDOException $e) {
    error_log("Messages error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Carrier Dashboard</title>
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

        /* Sidebar */
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

        .user-profile-link {
            display: block;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .user-profile-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
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

        .header-profile-btn, .header-logout-btn {
            color: var(--dark-gray);
            text-decoration: none;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-profile-btn:hover, .header-logout-btn:hover {
            background: var(--light-gray);
        }

        .header-profile-btn .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-yellow);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
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
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }

        .stat-icon.blue {
            background: rgba(0, 51, 102, 0.1);
            color: var(--primary-blue);
        }

        .stat-icon.green {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .stat-icon.orange {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .stat-icon.purple {
            background: rgba(156, 39, 176, 0.1);
            color: #9C27B0;
        }

        .stat-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--text-light);
            font-size: 14px;
        }

        /* Dashboard Sections */
        .dashboard-section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            color: var(--primary-blue);
        }

        .view-all {
            color: var(--secondary-blue);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .loads-table {
            width: 100%;
            border-collapse: collapse;
        }

        .loads-table th {
            text-align: left;
            padding: 15px 20px;
            background: var(--light-gray);
            color: var(--primary-blue);
            font-weight: 600;
        }

        .loads-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .loads-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #D4EDDA;
            color: #155724;
        }

        .status-badge.inactive {
            background: #F8D7DA;
            color: #721C24;
        }

        .status-badge.pending {
            background: #FFF3CD;
            color: #856404;
        }

        .action-btn {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
            margin-right: 5px;
        }

        .action-btn:hover {
            background: var(--secondary-blue);
        }

        .action-btn.secondary {
            background: var(--light-gray);
            color: var(--dark-gray);
        }

        .action-btn.secondary:hover {
            background: #e0e0e0;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-blue);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }

        .modal-body {
            padding: 25px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-blue);
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

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: var(--secondary-blue);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }

        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }

        /* Profile Info */
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .info-card h3 {
            color: var(--primary-blue);
            margin-bottom: 15px;
            font-size: 18px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-dark);
        }

        .info-value {
            color: var(--text-light);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .sidebar.active {
            left: 0;
        }

        /* Available Loads Styles */
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .weight-filter {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 15px;
            align-items: end;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .loads-grid {
            display: grid;
            gap: 25px;
        }

        .load-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-blue);
        }

        .load-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            
            .profile-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                left: -100%;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .top-header {
                padding: 0 15px;
            }

            .search-bar {
                width: 150px;
            }

            .header-actions {
                gap: 10px;
            }

            .dashboard-content {
                padding: 20px 15px;
            }

            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .weight-filter {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
        }

        .menu-toggle {
            display: none !important;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary-blue);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'assets/php/sidebar-carrier.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search loads, shippers...">
            </div>
            
            <div class="header-actions">
                <div class="notification-btn" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars($unread_notifications_count); ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="dropdown-header">
                        <h4>Notifications</h4>
                        <a href="#" onclick="markAllAsRead('notifications')">Mark all as read</a>
                    </div>
                    <div class="dropdown-content">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="dropdown-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                                    onclick="markNotificationAsRead(<?php echo $notification['id']; ?>)">
                                    <div class="dropdown-text">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dropdown-item">
                                <div class="dropdown-text">
                                    <p>No new notifications</p>
                                    <small>You'll see updates here</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="#" onclick="viewAllNotifications()">View all notifications</a>
                    </div>
                </div>
                <a href="contact.php?from=carrier-dashboard" id="messagesBtn" class="support-icon" title="Need Help? Contact Support">
                    <i class="fa-solid fa-headset"></i>
                </a>
                <div class="messages-dropdown" id="messagesDropdown" style="display:none"></div>


            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="welcome-text">
                        <h2>Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</h2>
                        <p>SYC Carrier ID: <strong><?php echo $syc_carrier_id ?: 'Not assigned'; ?></strong></p>
                        <p>You have <?php echo count($trucks); ?> registered truck(s). <?php echo count(array_filter($trucks, function($truck) { return ($truck['status'] ?? '') === 'active'; })); ?> active.</p>
                        <?php if (isset($carrier_error)): ?>
                            <p style="color: #FFD700; font-weight: 500;"><?php echo htmlspecialchars($carrier_error); ?></p>
                        <?php endif; ?>
                    </div>
                    <button class="banner-cta" data-tab="available-loads">Find Loads</button>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($trucks); ?></h3>
                            <p>Total Trucks</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count(array_filter($trucks, function($truck) { return ($truck['status'] ?? '') === 'active'; })); ?></h3>
                            <p>Active Trucks</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($active_bids); ?></h3>
                            <p>Active Bids</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3>ETB 0</h3>
                            <p>Earnings This Month</p>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="two-column" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                    <!-- Available Loads -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Available Loads</h2>
                            <a href="#" class="view-all" data-tab="available-loads">View All</a>
                        </div>
                        <div class="card">
                            <?php if (!empty(array_slice($available_loads, 0, 3))): ?>
                                <table class="loads-table">
                                    <thead>
                                        <tr>
                                            <th>Load ID</th>
                                            <th>Origin</th>
                                            <th>Destination</th>
                                            <th>Weight</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($available_loads, 0, 3) as $load): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($load['cargo_id'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($load['pickup_location'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($load['dropoff_location'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($load['weight'] ?? '0'); ?> kg</td>
                                                <td>
                                                    <button class="action-btn secondary" onclick="openLoadDetails(<?php echo htmlspecialchars(json_encode($load)); ?>)">
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px;">
                                    <i class="fas fa-truck-loading" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                    <h3 style="color: #666; margin-bottom: 10px;">No Available Loads</h3>
                                    <p style="color: #999;">Check the Available Loads tab for more options.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Active Bids -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Active Bids</h2>
                            <a href="#" class="view-all">View All</a>
                        </div>
                        <div class="card">
                            <?php if (!empty($active_bids)): ?>
                                <table class="loads-table">
                                    <thead>
                                        <tr>
                                            <th>Load ID</th>
                                            <th>Bid Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($active_bids, 0, 3) as $bid): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($bid['cargo_id'] ?? 'Unknown'); ?></td>
                                                <td>$<?php echo htmlspecialchars($bid['bid_amount'] ?? '0'); ?></td>
                                                <td>
                                                    <span class="status-badge pending">Pending</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px;">
                                    <i class="fas fa-gavel" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                    <h3 style="color: #666; margin-bottom: 10px;">No Active Bids</h3>
                                    <p style="color: #999;">Submit bids on available loads to get started.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Trucks Tab -->
            <div id="my-trucks" class="tab-content">
                <div class="section-header">
                    <h2>My Trucks</h2>
                    <button class="btn-primary" id="addTruckBtn2">
                        <i class="fas fa-plus"></i> Add New Truck
                    </button>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($carrier_error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($carrier_error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <?php if (count($trucks) > 0): ?>
                        <table class="loads-table">
                            <thead>
                                <tr>
                                    <th>License Plate</th>
                                    <th>Model</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trucks as $truck): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($truck['license_plate']); ?></td>
                                        <td><?php echo htmlspecialchars($truck['truck_model']); ?></td>
                                        <td><?php echo htmlspecialchars($truck['capacity']); ?> kg</td>
                                        <td>
                                            <span class="status-badge <?php echo ($truck['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($truck['status'] ?? 'unknown')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn secondary" onclick="editTruck(<?php echo $truck['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="action-btn secondary" onclick="deleteTruck(<?php echo $truck['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-truck" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                            <h3 style="color: #666; margin-bottom: 10px;">No Trucks Registered</h3>
                            <p style="color: #999; margin-bottom: 20px;">Get started by adding your first truck to your fleet.</p>
                            <button class="btn-primary" id="addTruckBtn3">
                                <i class="fas fa-plus"></i> Add Your First Truck
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Available Loads Tab -->
            <div id="available-loads" class="tab-content">
                <div class="section-header">
                    <h2>Available Loads</h2>
                    <p>Find and bid on available shipping loads</p>
                </div>
                
                <?php if (isset($bid_success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($bid_success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($bid_error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($bid_error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters Card -->
                <div class="filters-card">
                    <h3 style="margin-bottom: 20px; color: var(--primary-blue);">Filter Available Loads</h3>
                    
                    <form method="GET" action="">
                        <input type="hidden" name="tab" value="available-loads">
                        
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="origin">Origin Location</label>
                                <input type="text" id="origin" name="origin" value="<?php echo htmlspecialchars($loads_filters['origin']); ?>" placeholder="e.g., Addis Ababa">
                            </div>
                            
                            <div class="form-group">
                                <label for="destination">Destination</label>
                                <input type="text" id="destination" name="destination" value="<?php echo htmlspecialchars($loads_filters['destination']); ?>" placeholder="e.g., Hawassa">
                            </div>
                            
                            <div class="form-group">
                                <label for="cargo_type">Cargo Type</label>
                                <select id="cargo_type" name="cargo_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($cargo_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $loads_filters['cargo_type'] == $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="weight-filter">
                            <div class="form-group">
                                <label for="weight_min">Min Weight (kg)</label>
                                <input type="number" id="weight_min" name="weight_min" value="<?php echo htmlspecialchars($loads_filters['weight_min']); ?>" placeholder="0">
                            </div>
                            
                            <span style="align-self: center; margin: 0 10px;">to</span>
                            
                            <div class="form-group">
                                <label for="weight_max">Max Weight (kg)</label>
                                <input type="number" id="weight_max" name="weight_max" value="<?php echo htmlspecialchars($loads_filters['weight_max']); ?>" placeholder="No limit">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="?tab=available-loads" class="action-btn secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Loads Display -->
                <?php if (!empty($available_loads)): ?>
                    <div class="loads-grid" id="loads-container">
                        <?php foreach ($available_loads as $load): ?>
                            <div class="load-card">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 10px 0; color: var(--primary-blue);">
                                            <?php echo htmlspecialchars($load['cargo_id'] ?? 'Unknown Load'); ?>
                                        </h4>
                                        <p style="margin: 0 0 8px 0; color: var(--text-light); font-size: 14px;">
                                            <i class="fas fa-box"></i> <?php echo htmlspecialchars($load['description'] ?? 'No description'); ?>
                                        </p>
                                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 14px; color: var(--text-light);">
                                                <i class="fas fa-weight-hanging"></i> <?php echo htmlspecialchars($load['weight'] ?? '0'); ?> kg
                                            </span>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 14px; color: var(--text-light);">
                                                <i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($load['dimensions'] ?? 'N/A'); ?>
                                            </span>
                                            <span style="display: inline-block; background: rgba(0, 51, 102, 0.1); color: var(--primary-blue); padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                                <?php echo htmlspecialchars($load['cargo_type'] ?? 'General'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="status-badge pending">Available</span>
                                    </div>
                                </div>
                                
                                <!-- Route Information -->
                                <div style="display: flex; align-items: center; justify-content: space-between; background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                                    <div style="text-align: center; flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">Pickup</div>
                                        <div style="font-size: 14px; color: var(--text-dark);">
                                            <i class="fas fa-map-marker-alt" style="color: #4CAF50;"></i>
                                            <?php echo htmlspecialchars($load['pickup_location'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: center; flex: 0 0 auto; padding: 0 20px;">
                                        <i class="fas fa-arrow-right" style="color: var(--text-light);"></i>
                                    </div>
                                    <div style="text-align: center; flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">Delivery</div>
                                        <div style="font-size: 14px; color: var(--text-dark);">
                                            <i class="fas fa-flag-checkered" style="color: #FF9800;"></i>
                                            <?php echo htmlspecialchars($load['dropoff_location'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Shipper Information -->
                                <div style="margin-bottom: 20px;">
                                    <h5 style="margin: 0 0 10px 0; color: var(--secondary-blue); font-size: 14px;">Shipper Information</h5>
                                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                        <span style="font-size: 14px; color: var(--text-light);">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($load['shipper_company'] ?? 'Unknown Company'); ?>
                                        </span>
                                        <span style="font-size: 14px; color: var(--text-light);">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($load['shipper_contact'] ?? 'Unknown Contact'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn-primary view-load-details" 
                                            style="flex: 1; padding: 12px 15px; font-size: 14px;"
                                            onclick="openLoadDetails(<?php echo htmlspecialchars(json_encode($load)); ?>)">
                                        <i class="fas fa-info-circle"></i> View Details
                                    </button>
                                    <button class="action-btn secondary submit-bid-btn" 
                                            style="flex: 1; padding: 12px 15px; font-size: 14px;"
                                            data-load-id="<?php echo $load['id']; ?>"
                                            data-load-description="<?php echo htmlspecialchars($load['description'] ?? 'Load'); ?>">
                                        <i class="fas fa-gavel"></i> Submit Bid
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-light); background: white; border-radius: 15px; box-shadow: var(--card-shadow);">
                        <i class="fas fa-truck-loading" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                        <h3 style="color: #666; margin-bottom: 10px;">No Available Loads</h3>
                        <p>No loads match your current filters. Try adjusting your search criteria or check back later.</p>
                        <a href="?tab=available-loads" class="btn-primary" style="margin-top: 15px; display: inline-block;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Loads Tab -->
            <div id="my-loads" class="tab-content">
                <div class="section-header">
                    <h2>My Loads</h2>
                    <p>Manage your accepted loads and track delivery status</p>
                </div>

                <?php if (!empty($my_loads)): ?>
                    <div class="loads-grid">
                        <?php foreach ($my_loads as $load): ?>
                            <div class="load-card">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 10px 0; color: var(--primary-blue);">
                                            <?php echo htmlspecialchars($load['cargo_id'] ?? 'Unknown Load'); ?>
                                        </h4>
                                        <p style="margin: 0 0 8px 0; color: var(--text-light); font-size: 14px;">
                                            <i class="fas fa-box"></i> <?php echo htmlspecialchars($load['description'] ?? 'No description'); ?>
                                        </p>
                                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 14px; color: var(--text-light);">
                                                <i class="fas fa-weight-hanging"></i> <?php echo htmlspecialchars($load['weight'] ?? '0'); ?> kg
                                            </span>
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 14px; color: var(--text-light);">
                                                <i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($load['dimensions'] ?? 'N/A'); ?>
                                            </span>
                                            <span style="display: inline-block; background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                                <?php echo htmlspecialchars($load['cargo_type'] ?? 'General'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="status-badge active">Accepted</span>
                                        <div style="margin-top: 10px; font-size: 14px; color: var(--text-light);">
                                            <strong>$<?php echo htmlspecialchars($load['bid_amount'] ?? '0'); ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Route Information -->
                                <div style="display: flex; align-items: center; justify-content: space-between; background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                                    <div style="text-align: center; flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">Pickup</div>
                                        <div style="font-size: 14px; color: var(--text-dark);">
                                            <i class="fas fa-map-marker-alt" style="color: #4CAF50;"></i>
                                            <?php echo htmlspecialchars($load['pickup_location'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: center; flex: 0 0 auto; padding: 0 20px;">
                                        <i class="fas fa-arrow-right" style="color: var(--text-light);"></i>
                                    </div>
                                    <div style="text-align: center; flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">Delivery</div>
                                        <div style="font-size: 14px; color: var(--text-dark);">
                                            <i class="fas fa-flag-checkered" style="color: #FF9800;"></i>
                                            <?php echo htmlspecialchars($load['dropoff_location'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shipper Information -->
                                <div style="margin-bottom: 20px;">
                                    <h5 style="margin: 0 0 10px 0; color: var(--secondary-blue); font-size: 14px;">Shipper Information</h5>
                                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                        <span style="font-size: 14px; color: var(--text-light);">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($load['shipper_company'] ?? 'Unknown Company'); ?>
                                        </span>
                                        <span style="font-size: 14px; color: var(--text-light);">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($load['shipper_contact'] ?? 'Unknown Contact'); ?>
                                        </span>
                                        <span style="font-size: 14px; color: var(--text-light);">
                                            <i class="fas fa-calendar"></i> Bid: <?php echo date('M j, Y', strtotime($load['bid_date'] ?? 'N/A')); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn-primary view-load-details"
                                            style="flex: 1; padding: 12px 15px; font-size: 14px;"
                                            onclick="openLoadDetails(<?php echo htmlspecialchars(json_encode($load)); ?>)">
                                        <i class="fas fa-info-circle"></i> View Details
                                    </button>
                                    <button class="action-btn secondary"
                                            style="flex: 1; padding: 12px 15px; font-size: 14px;"
                                            onclick="markAsDelivered(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-check"></i> Mark Delivered
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-light); background: white; border-radius: 15px; box-shadow: var(--card-shadow);">
                        <i class="fas fa-shipping-fast" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                        <h3 style="color: #666; margin-bottom: 10px;">No Accepted Loads</h3>
                        <p>You haven't accepted any loads yet. Check the Available Loads tab to find and bid on loads.</p>
                        <button class="btn-primary" style="margin-top: 15px;" data-tab="available-loads">
                            <i class="fas fa-truck-loading"></i> Browse Available Loads
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div id="invoices" class="tab-content">
                <div class="section-header">
                    <h2>Invoices</h2>
                    <p>Manage your invoices and payments</p>
                </div>
                
                <?php if (!empty($invoices)): ?>
                    <div class="loads-grid">
                        <?php foreach ($invoices as $invoice): ?>
                            <div class="load-card">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 10px 0; color: var(--primary-blue);">
                                            <?php echo htmlspecialchars($invoice['cargo_id'] ?? 'Unknown Load'); ?>
                                        </h4>
                                        <p style="margin: 0 0 8px 0; color: var(--text-light); font-size: 14px;">
                                            <i class="fas fa-box"></i> <?php echo htmlspecialchars($invoice['description'] ?? 'No description'); ?>
                                        </p>
                                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                            <span style="display: flex; align-items: center; gap: 5px; font-size: 14px; color: var(--text-light);">
                                                <i class="fas fa-dollar-sign"></i> $<?php echo htmlspecialchars($invoice['amount'] ?? '0'); ?>
                                            </span>
                                            <span style="display: inline-block; background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                                <?php echo htmlspecialchars(ucfirst($invoice['status'] ?? 'pending')); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="status-badge <?php echo ($invoice['status'] ?? 'pending') === 'paid' ? 'active' : 'pending'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($invoice['status'] ?? 'pending')); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Route Information -->
                                <div style="display: flex; align-items: center; justify-content: space-between; background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                                    <div style="text-align: center; flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">Pickup</div>
                                        <div style="font-size: 14px; color: var(--text-dark);">
                                            <i class="fas fa-map-marker-alt" style="color: #4CAF50;"></i>
                                            <?php echo htmlspecialchars($invoice['pickup_location'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: center; flex: 0 0 auto; padding: 0 20px;">
                                        <i class="fas fa-arrow-right" style="color: var(--text-light);"></i>
                                    </div>
                                    <div style="text-align: center; flex: 1;">
                                        <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">Delivery</div>
                                        <div style="font-size: 14px; color: var(--text-dark);">
                                            <i class="fas fa-flag-checkered" style="color: #FF9800;"></i>
                                            <?php echo htmlspecialchars($invoice['dropoff_location'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button class="btn-primary" onclick="downloadInvoice(<?php echo $invoice['id']; ?>)">
                                        <i class="fas fa-download"></i> Download PDF
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-light); background: white; border-radius: 15px; box-shadow: var(--card-shadow);">
                        <i class="fas fa-file-invoice" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                        <h3 style="color: #666; margin-bottom: 10px;">No Invoices Yet</h3>
                        <p>Complete deliveries to generate invoices.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="performance" class="tab-content">
                <div class="section-header">
                    <h2>Performance</h2>
                    <p>Track your carrier metrics and analytics</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $performance_metrics['total_bids']; ?></h3>
                            <p>Total Bids Submitted</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $performance_metrics['accepted_bids']; ?></h3>
                            <p>Accepted Bids</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $performance_metrics['acceptance_rate']; ?>%</h3>
                            <p>Acceptance Rate</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3>$<?php echo number_format($performance_metrics['total_earnings'], 2); ?></h3>
                            <p>Total Earnings</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <h3>$<?php echo number_format($performance_metrics['avg_bid_amount'], 2); ?></h3>
                            <p>Avg Bid Amount</p>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="padding: 25px;">
                    <h3 style="color: var(--primary-blue); margin-bottom: 20px;">Recent Activity</h3>
                    <p style="color: var(--text-light);">Detailed performance charts and history coming soon.</p>
                </div>
            </div>

            <div id="settings" class="tab-content">
                <div class="section-header">
                    <h2>Settings</h2>
                    <p>Manage your account preferences</p>
                </div>
                
                <div class="profile-info">
                    <div class="info-card">
                        <h3>Account Information</h3>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($carrier_data['email'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($carrier_data['phone'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">SYC ID:</span>
                            <span class="info-value"><?php echo htmlspecialchars($syc_carrier_id ?: 'Not assigned'); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3>Change Password</h3>
                        <form id="changePasswordForm" method="POST" style="display: grid; gap: 15px;">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn-primary" style="width: 100%;">Update Password</button>
                        </form>
                    </div>
                </div>
                
                <?php
                // Handle password change
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
                    $current_password = $_POST['current_password'] ?? '';
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';
                    
                    if ($new_password !== $confirm_password) {
                        $password_error = "New passwords do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $password_error = "New password must be at least 6 characters.";
                    } else {
                        try {
                            // Verify current password
                            $pass_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                            $pass_stmt->execute([$user_id]);
                            $user_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC)['password'];
                            
                            if (password_verify($current_password, $user_pass)) {
                                $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
                                $update_pass_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                                $update_pass_stmt->execute([$hashed_new, $user_id]);
                                
                                $password_success = "Password updated successfully!";
                            } else {
                                $password_error = "Current password is incorrect.";
                            }
                        } catch (PDOException $e) {
                            error_log("Password update error: " . $e->getMessage());
                            $password_error = "Error updating password.";
                        }
                    }
                }
                
                if (isset($password_success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($password_success); ?></div>
                <?php endif; ?>
                
                <?php if (isset($password_error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($password_error); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Truck Modal -->
    <div class="modal" id="addTruckModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Truck</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addTruckForm" method="POST">
                    <div class="form-group">
                        <label for="license_plate">License Plate *</label>
                        <input type="text" id="license_plate" name="license_plate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="truck_model">Truck Model *</label>
                        <input type="text" id="truck_model" name="truck_model" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity (kg) *</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" step="0.1" min="1" required>
                    </div>
                    <button type="submit" name="register_truck" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> Add Truck
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm" method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label for="company_name">Company Name *</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo htmlspecialchars($carrier_data['company_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Contact Person *</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($carrier_data['contact_person'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($carrier_data['phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Submit Bid Modal -->
    <div class="modal" id="submitBidModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Submit Bid</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="submitBidForm" method="POST">
                    <input type="hidden" name="submit_bid" value="1">
                    <input type="hidden" id="bid_load_id" name="load_id" value="">
                    
                    <div class="form-group">
                        <label for="bid_amount">Bid Amount ($)</label>
                        <input type="number" id="bid_amount" name="bid_amount" class="form-control" step="0.01" min="0" required placeholder="Enter your bid amount">
                    </div>
                    
                    <div class="form-group">
                        <label for="bid_notes">Notes (Optional)</label>
                        <textarea id="bid_notes" name="bid_notes" class="form-control" rows="4" placeholder="Add any additional notes about your bid..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Submit Bid
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
// Tab Navigation
document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const tabId = this.getAttribute('data-tab');

        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabId).classList.add('active');

        // Update active nav link
        document.querySelectorAll('.sidebar-nav a').forEach(navLink => {
            navLink.classList.remove('active');
        });
        this.classList.add('active');

        // Close sidebar on mobile after selecting a menu item
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.remove('active');
        }
    });
});

// Mark as Delivered function
function markAsDelivered(loadId) {
    if (confirm('Are you sure you have delivered this load? This will generate an invoice.')) {
        fetch('api/mark_as_delivered.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'load_id=' + loadId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Refresh the page or update UI
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error marking as delivered.');
        });
    }
}

// View All Notifications function
function viewAllNotifications() {
    // For now, expand the dropdown to show more; later, redirect to full page
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.style.maxHeight = '600px';
    // Load more notifications via AJAX if needed
    fetch('api/get_unread_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.notifications > 5) {
                // Could fetch more, but for simplicity, just expand
                alert('Showing more notifications. Full page coming soon.');
            }
        });
}

// Download Invoice placeholder
function downloadInvoice(invoiceId) {
    alert('Download PDF functionality for invoice ID: ' + invoiceId + ' will be implemented soon.');
}

// Password change validation
document.getElementById('changePasswordForm')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('Passwords do not match.');
    } else if (newPass.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters.');
    }
});

// AJAX for load filters (basic implementation)
document.querySelector('.filters-card form')?.addEventListener('submit', function(e) {
    // For now, allow default POST; later, implement AJAX fetch for loads
    console.log('Filters applied - consider AJAX for live updates.');
});

// Handle URL parameter for initial tab activation
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        // Show the specified tab if it exists
        const targetTab = document.getElementById(tabParam);
        if (targetTab) {
            targetTab.classList.add('active');
            // Update active nav link
            document.querySelectorAll('.sidebar-nav a').forEach(navLink => {
                navLink.classList.remove('active');
                if (navLink.getAttribute('data-tab') === tabParam) {
                    navLink.classList.add('active');
                }
            });
        }
    }
});

// Modal functionality
const modals = document.querySelectorAll('.modal');
const addTruckBtns = document.querySelectorAll('#addTruckBtn, #addTruckBtn2, #addTruckBtn3');
const editProfileBtns = document.querySelectorAll('#editProfileBtn, #editProfileBtn2');
const closeBtns = document.querySelectorAll('.modal-close');

// Open Add Truck Modal
addTruckBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('addTruckModal').classList.add('active');
    });
});

// Open Edit Profile Modal
editProfileBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editProfileModal').classList.add('active');
    });
});

// Close Modals
closeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        modals.forEach(modal => modal.classList.remove('active'));
    });
});

// Close modal when clicking outside
modals.forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});

// Notification and Messages Dropdowns
const notificationBtn = document.getElementById('notificationBtn');
const messagesBtn = document.getElementById('messagesBtn');
const notificationDropdown = document.getElementById('notificationDropdown');
const messagesDropdown = document.getElementById('messagesDropdown');

notificationBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notificationDropdown.classList.toggle('show');
    messagesDropdown.classList.remove('show');
});

// Support icon is a direct link; no dropdown behavior needed.

// Close dropdowns when clicking outside
document.addEventListener('click', () => {
    notificationDropdown.classList.remove('show');
    messagesDropdown.classList.remove('show');
});

// Prevent dropdowns from closing when clicking inside
notificationDropdown.addEventListener('click', (e) => e.stopPropagation());
// No messages dropdown content to interact with.


function updateNotificationBadge() {
    fetch('api/get_unread_count.php')
        .then(response => response.json())
        .then(data => {
            const notificationBtn = document.getElementById('notificationBtn');
            let badge = notificationBtn.querySelector('.notification-badge');
            if (data.count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-badge';
                    notificationBtn.appendChild(badge);
                }
                badge.textContent = data.count;
                badge.style.display = 'flex';
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching unread count:', error);
        });
}

function markAllAsRead(type) {
    if (type === 'notifications') {
        fetch('api/mark_all_notifications_read.php')  // Remove the api/ prefix
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove unread styling from all notifications
                    document.querySelectorAll('.notification-dropdown .dropdown-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    // Update badge count to zero
                    updateNotificationBadge();
                    // Show success message
                    alert('All notifications marked as read!');
                } else {
                    alert('Error marking notifications as read: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking notifications as read.');
            });
    } else if (type === 'messages') {
        // You can implement similar functionality for messages later
        alert('Mark all messages as read functionality coming soon!');
    }
}

function markNotificationAsRead(notificationId) {
    fetch('api/mark_notification_read.php?id=' + notificationId)  // Remove the api/ prefix
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Instantly update UI without page refresh
                const notificationElement = document.querySelector('.dropdown-item[onclick*="' + notificationId + '"]');
                if (notificationElement) {
                    notificationElement.classList.remove('unread');
                }
                updateNotificationBadge(); // Update count immediately
            } else {
                console.error('Error marking notification as read:', data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Mobile menu toggle - FIXED VERSION
document.getElementById('menuToggle').addEventListener('click', (e) => {
    e.stopPropagation();
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close sidebar when clicking outside on mobile - FIXED VERSION
document.addEventListener('click', (e) => {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    // Only close if sidebar is active and click is outside
    if (sidebar.classList.contains('active') && 
        !sidebar.contains(e.target) && 
        e.target !== menuToggle) {
        sidebar.classList.remove('active');
    }
});

// Prevent clicks inside sidebar from closing it
document.querySelector('.sidebar').addEventListener('click', (e) => {
    e.stopPropagation();
});

// Quick action buttons that switch tabs
document.querySelectorAll('.action-btn[data-tab]').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabId).classList.add('active');
        
        // Update active nav link
        document.querySelectorAll('.sidebar-nav a').forEach(navLink => {
            navLink.classList.remove('active');
            if (navLink.getAttribute('data-tab') === tabId) {
                navLink.classList.add('active');
            }
        });
        
        // Close sidebar on mobile after action
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.remove('active');
        }
    });
});

// Placeholder functions for truck management
function editTruck(truckId) {
    alert('Edit truck functionality for truck ID: ' + truckId + ' will be implemented soon.');
}

function deleteTruck(truckId) {
    if (confirm('Are you sure you want to delete this truck? This action cannot be undone.')) {
        alert('Delete truck functionality for truck ID: ' + truckId + ' will be implemented soon.');
    }
}

// Available Loads functionality
document.addEventListener('DOMContentLoaded', function() {
    // Submit Bid buttons
    const submitBidBtns = document.querySelectorAll('.submit-bid-btn');
    submitBidBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const loadId = this.dataset.loadId;
            const loadDescription = this.dataset.loadDescription;
            openBidModal(loadId, loadDescription);
        });
    });
});

// Load Details Modal
function openLoadDetails(load) {
    const modalHtml = `
        <div class="modal active" id="load-details-modal">
            <div class="modal-content" style="max-width: 700px;">
                <div class="modal-header">
                    <h3>Load Details - ${load.cargo_id || 'Unknown'}</h3>
                    <button class="modal-close" onclick="closeModal('load-details-modal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                        <div>
                            <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Load Information</h4>
                            <div style="display: grid; gap: 10px;">
                                <div><strong>Cargo ID:</strong> ${load.cargo_id || 'N/A'}</div>
                                <div><strong>Description:</strong> ${load.description || 'N/A'}</div>
                                <div><strong>Weight:</strong> ${load.weight || '0'} kg</div>
                                <div><strong>Dimensions:</strong> ${load.dimensions || 'N/A'}</div>
                                <div><strong>Cargo Type:</strong> ${load.cargo_type || 'General'}</div>
                                <div><strong>Status:</strong> <span class="status-badge pending">Available</span></div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Route Information</h4>
                            <div style="display: grid; gap: 10px;">
                                <div><strong>Pickup Location:</strong> ${load.pickup_location || 'Unknown'}</div>
                                <div><strong>Dropoff Location:</strong> ${load.dropoff_location || 'Unknown'}</div>
                                <div><strong>Created:</strong> ${load.created_at ? new Date(load.created_at).toLocaleDateString() : 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Shipper Information</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div><strong>Company:</strong> ${load.shipper_company || 'N/A'}</div>
                            <div><strong>Contact:</strong> ${load.shipper_contact || 'N/A'}</div>
                            <div><strong>Email:</strong> ${load.shipper_email || 'N/A'}</div>
                            <div><strong>Phone:</strong> ${load.shipper_phone || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button class="action-btn secondary" onclick="closeModal('load-details-modal')">Close</button>
                        <button class="btn-primary" onclick="openBidModal(${load.id}, '${load.description || 'Load'}')">
                            <i class="fas fa-gavel"></i> Submit Bid
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// Submit Bid Modal
function openBidModal(loadId, loadDescription) {
    // Close any open modals first
    closeModal('load-details-modal');
    
    // Set the load ID in the form
    document.getElementById('bid_load_id').value = loadId;
    
    // Open the bid modal
    document.getElementById('submitBidModal').classList.add('active');
}

// Generic modal close function
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.remove();
    }
}

// Auto-check for new notifications every 30 seconds
setInterval(updateNotificationBadge, 30000);

// Also check when user focuses on the window  
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        updateNotificationBadge();
    }
});

    </script>
</body>
</html>