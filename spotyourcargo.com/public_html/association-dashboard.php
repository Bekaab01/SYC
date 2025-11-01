<?php
// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();

// Include database connection
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

// Check if user is an association
if ($_SESSION['user_type'] !== 'association') {
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

        // Check if association has completed registration
        $association_check_stmt = $pdo->prepare("SELECT id, registration_status FROM associations WHERE user_id = ?");
        $association_check_stmt->execute([$user_id]);
        $existing_association = $association_check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing_association) {
            // Association hasn't completed registration, redirect to registration page
            header('Location: association-registration.php');
            exit();
        } elseif ($existing_association['registration_status'] === 'pending') {
            // Association is pending approval, redirect to pending approval page
            header('Location: association-pending-approval.php');
            exit();
        } elseif ($existing_association['registration_status'] === 'rejected') {
            // Association was rejected, redirect to pending approval page to show status
            header('Location: association-pending-approval.php');
            exit();
        }
    }
} catch (PDOException $e) {
    error_log("User data fetch error: " . $e->getMessage());
    $association_error = "Error fetching user data: " . $e->getMessage();
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
$association_name = "Truck Association";
$association_id = null;
$syc_association_id = null;
$association_data = [];
$trucks = [];
$drivers = [];
$association_error = "";
$success_message = "";

// Fetch association profile data
try {
    $association_stmt = $pdo->prepare("
        SELECT
            u.*,
            a.id as association_id,
            a.syc_id as syc_association_id,
            a.name as association_name,
            a.contact_person,
            a.phone,
            a.email as association_email,
            a.region,
            a.license_number,
            a.number_of_trucks,
            a.registration_status,
            a.created_at as association_created_at
        FROM users u
        LEFT JOIN associations a ON u.syc_id = a.syc_id
        WHERE u.id = ?
    ");
    $association_stmt->execute([$user_id]);
    $association_data = $association_stmt->fetch(PDO::FETCH_ASSOC);

    if ($association_data) {
        // Set display name
        $display_name = $association_data['association_name'] ?? ($association_data['name'] ?? $association_data['username'] ?? 'User');
        $user_name = $display_name;

        // Extract first name for welcome message
        $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
        if (strpos($welcome_name, '@') !== false) {
            $welcome_name = explode('@', $welcome_name)[0];
        }
        $welcome_name = ucfirst(strtolower($welcome_name));

        $user_initials = strtoupper(substr($display_name, 0, 2));
        $association_name = $association_data['association_name'] ?? 'Truck Association';
        $association_id = $association_data['association_id'] ?? null;
        $syc_association_id = $association_data['syc_association_id'] ?? $user_syc_id;
    } else {
        $syc_association_id = $user_syc_id;
        $display_name = 'User';
        $user_name = $display_name;
        $welcome_name = $display_name;
        $user_initials = 'U';
    }
} catch (PDOException $e) {
    error_log("Association data fetch error: " . $e->getMessage());
    $association_error = "Error loading association profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $contact_person = $_POST['contact_person'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $region = $_POST['region'] ?? '';

    try {
        $update_stmt = $pdo->prepare("
            UPDATE associations
            SET contact_person = ?, phone = ?, region = ?, updated_at = NOW()
            WHERE syc_id = ?
        ");
        $update_stmt->execute([$contact_person, $phone, $region, $user_syc_id]);

        if ($update_stmt->rowCount() > 0) {
            $success_message = "Profile updated successfully!";

            // Add notification for profile update
            $notification_message = "Profile updated: Contact information modified";
            createNotification($pdo, $user_syc_id, $notification_message);

            // Refresh association data
            $association_stmt->execute([$user_id]);
            $association_data = $association_stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $association_error = "Error updating profile: " . $e->getMessage();
    }
}

// Example: Add welcome notification for first-time users (only once)
if ($association_data && $user_syc_id) {
    // Check if welcome notification already exists for this user
    $welcome_check_stmt = $pdo->prepare("SELECT id FROM notifications WHERE syc_id = ? AND message LIKE 'Welcome to SYC Association Dashboard%' LIMIT 1");
    $welcome_check_stmt->execute([$user_syc_id]);
    $existing_welcome = $welcome_check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_welcome) {
        $welcome_notification = "Welcome to SYC Association Dashboard! Start managing your fleet and drivers.";
        createNotification($pdo, $user_syc_id, $welcome_notification);
    }
}

// Handle truck registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_truck'])) {
    $plate_number = $_POST['plate_number'] ?? '';
    $truck_type = $_POST['truck_type'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $driver_id = $_POST['driver_id'] ?? null;
    $status = 'active';

    if ($association_id) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO association_trucks (association_id, plate_number, truck_type, capacity, driver_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$association_id, $plate_number, $truck_type, $capacity, $driver_id, $status]);

            if ($stmt->rowCount() > 0) {
                $success_message = "Truck registered successfully!";

                // Update number of trucks in associations table
                $update_truck_count = $pdo->prepare("
                    UPDATE associations
                    SET number_of_trucks = (SELECT COUNT(*) FROM association_trucks WHERE association_id = ?),
                    updated_at = NOW()
                    WHERE id = ?
                ");
                $update_truck_count->execute([$association_id, $association_id]);

                // Add notification for truck registration
                $notification_message = "New truck registered: " . htmlspecialchars($plate_number) . " (" . htmlspecialchars($truck_type) . ")";
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $association_error = "Error registering truck. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Truck registration error: " . $e->getMessage());
            $association_error = "Error registering truck: " . $e->getMessage();
        }
    } else {
        $association_error = "Cannot register truck. Association profile not found.";
    }
}

// Handle driver registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_driver'])) {
    $full_name = $_POST['full_name'] ?? '';
    $license_number = $_POST['license_number'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $experience_years = (int)($_POST['experience_years'] ?? 0);

    if ($association_id) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO drivers (association_id, full_name, license_number, phone, experience_years, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'available', NOW())
            ");
            $stmt->execute([$association_id, $full_name, $license_number, $phone, $experience_years]);

            if ($stmt->rowCount() > 0) {
                $success_message = "Driver registered successfully!";

                // Add notification for driver registration
                $notification_message = "New driver registered: " . htmlspecialchars($full_name);
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $association_error = "Error registering driver. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Driver registration error: " . $e->getMessage());
            $association_error = "Error registering driver: " . $e->getMessage();
        }
    } else {
        $association_error = "Cannot register driver. Association profile not found.";
    }
}

// Handle driver assignment to truck
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_driver'])) {
    $truck_id = $_POST['truck_id'] ?? '';
    $driver_id = $_POST['driver_id'] ?? '';

    if ($association_id && $truck_id && $driver_id) {
        try {
            // First, unassign driver from any other truck
            $unassign_stmt = $pdo->prepare("UPDATE trucks SET driver_id = NULL WHERE driver_id = ? AND association_id = ?");
            $unassign_stmt->execute([$driver_id, $association_id]);

            // Assign driver to selected truck
            $assign_stmt = $pdo->prepare("UPDATE trucks SET driver_id = ?, updated_at = NOW() WHERE id = ? AND association_id = ?");
            $assign_stmt->execute([$driver_id, $truck_id, $association_id]);

            if ($assign_stmt->rowCount() > 0) {
                $success_message = "Driver assigned to truck successfully!";

                // Update driver status
                $update_driver = $pdo->prepare("UPDATE drivers SET status = 'assigned', updated_at = NOW() WHERE id = ?");
                $update_driver->execute([$driver_id]);

                // Add notification
                $truck_info = $pdo->prepare("SELECT plate_number FROM trucks WHERE id = ?");
                $truck_info->execute([$truck_id]);
                $truck_data = $truck_info->fetch(PDO::FETCH_ASSOC);

                $driver_info = $pdo->prepare("SELECT full_name FROM drivers WHERE id = ?");
                $driver_info->execute([$driver_id]);
                $driver_data = $driver_info->fetch(PDO::FETCH_ASSOC);

                $notification_message = "Driver " . htmlspecialchars($driver_data['full_name']) . " assigned to truck " . htmlspecialchars($truck_data['plate_number']);
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $association_error = "Error assigning driver to truck. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Driver assignment error: " . $e->getMessage());
            $association_error = "Error assigning driver: " . $e->getMessage();
        }
    } else {
        $association_error = "Please select both a truck and a driver.";
    }
}

// Handle service request acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_service_request'])) {
    $service_request_id = $_POST['service_request_id'] ?? '';
    $truck_id = $_POST['truck_id'] ?? '';
    $driver_id = $_POST['driver_id'] ?? '';

    if ($association_id && $service_request_id && $truck_id && $driver_id) {
        try {
            $update_stmt = $pdo->prepare("
                UPDATE service_requests 
                SET association_id = ?, truck_id = ?, driver_id = ?, delivery_status = 'accepted', updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$association_id, $truck_id, $driver_id, $service_request_id]);

            if ($update_stmt->rowCount() > 0) {
                $success_message = "Service request accepted successfully!";

                // Update truck and driver status
                $update_truck = $pdo->prepare("UPDATE trucks SET status = 'on-trip', updated_at = NOW() WHERE id = ?");
                $update_truck->execute([$truck_id]);

                $update_driver = $pdo->prepare("UPDATE drivers SET status = 'on-trip', updated_at = NOW() WHERE id = ?");
                $update_driver->execute([$driver_id]);

                // Add notification
                $notification_message = "Service request #" . $service_request_id . " accepted and assigned to truck/driver";
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $association_error = "Error accepting service request. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Service request acceptance error: " . $e->getMessage());
            $association_error = "Error accepting service request: " . $e->getMessage();
        }
    } else {
        $association_error = "Please select both a truck and a driver for this service request.";
    }
}

// Handle delivery status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_status'])) {
    $service_request_id = $_POST['service_request_id'] ?? '';
    $delivery_status = $_POST['delivery_status'] ?? '';

    if ($association_id && $service_request_id && $delivery_status) {
        try {
            $update_stmt = $pdo->prepare("
                UPDATE service_requests
                SET delivery_status = ?, updated_at = NOW()
                WHERE id = ? AND association_id = ?
            ");
            $update_stmt->execute([$delivery_status, $service_request_id, $association_id]);

            if ($update_stmt->rowCount() > 0) {
                $success_message = "Delivery status updated successfully!";

                // If delivery is completed, free up truck and driver
                if ($delivery_status === 'delivered') {
                    $request_info = $pdo->prepare("SELECT truck_id, driver_id FROM service_requests WHERE id = ?");
                    $request_info->execute([$service_request_id]);
                    $request_data = $request_info->fetch(PDO::FETCH_ASSOC);

                    if ($request_data) {
                        $update_truck = $pdo->prepare("UPDATE trucks SET status = 'active', updated_at = NOW() WHERE id = ?");
                        $update_truck->execute([$request_data['truck_id']]);

                        $update_driver = $pdo->prepare("UPDATE drivers SET status = 'available', updated_at = NOW() WHERE id = ?");
                        $update_driver->execute([$request_data['driver_id']]);
                    }
                }

                // Add notification
                $notification_message = "Delivery status updated to: " . htmlspecialchars($delivery_status) . " for request #" . $service_request_id;
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $association_error = "Error updating delivery status. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Delivery status update error: " . $e->getMessage());
            $association_error = "Error updating delivery status: " . $e->getMessage();
        }
    } else {
        $association_error = "Please select a valid delivery status.";
    }
}

// Handle mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE syc_id = ? AND is_read = 0");
        $update_stmt->execute([$user_syc_id]);

        if ($update_stmt->rowCount() > 0) {
            $success_message = "All notifications marked as read!";
        }
    } catch (PDOException $e) {
        error_log("Mark all notifications read error: " . $e->getMessage());
        $association_error = "Error updating notifications: " . $e->getMessage();
    }
}

// Fetch available service requests
$available_requests = [];
if ($association_id) {
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'service_requests'");
        if ($table_check->rowCount() > 0) {
            $requests_stmt = $pdo->prepare("
                SELECT sr.*, 
                       s.company_name as shipper_company,
                       s.contact_person as shipper_contact,
                       t.company_name as transitor_company,
                       u.phone as shipper_phone,
                       u.email as shipper_email
                FROM service_requests sr
                LEFT JOIN shippers s ON sr.cargo_owner_id = s.id
                LEFT JOIN transitors t ON sr.transitor_id = t.id
                LEFT JOIN users u ON s.syc_id = u.syc_id
                WHERE sr.association_id IS NULL AND sr.delivery_status = 'pending'
                ORDER BY sr.created_at DESC
            ");
            $requests_stmt->execute();
            $available_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Service requests fetch error: " . $e->getMessage());
    }
}

// Fetch association's active service requests
$active_requests = [];
if ($association_id) {
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'service_requests'");
        if ($table_check->rowCount() > 0) {
            $active_requests_stmt = $pdo->prepare("
                SELECT sr.*, 
                       s.company_name as shipper_company,
                       s.contact_person as shipper_contact,
                       t.company_name as transitor_company,
                       tr.plate_number as truck_plate,
                       d.full_name as driver_name
                FROM service_requests sr
                LEFT JOIN shippers s ON sr.cargo_owner_id = s.id
                LEFT JOIN transitors t ON sr.transitor_id = t.id
                LEFT JOIN trucks tr ON sr.truck_id = tr.id
                LEFT JOIN drivers d ON sr.driver_id = d.id
                WHERE sr.association_id = ? AND sr.delivery_status IN ('accepted', 'in transit')
                ORDER BY sr.created_at DESC
            ");
            $active_requests_stmt->execute([$association_id]);
            $active_requests = $active_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Active service requests fetch error: " . $e->getMessage());
    }
}

// Fetch completed service requests
$completed_requests = [];
if ($association_id) {
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'service_requests'");
        if ($table_check->rowCount() > 0) {
            $completed_requests_stmt = $pdo->prepare("
                SELECT sr.*, 
                       s.company_name as shipper_company,
                       t.company_name as transitor_company,
                       tr.plate_number as truck_plate,
                       d.full_name as driver_name
                FROM service_requests sr
                LEFT JOIN shippers s ON sr.cargo_owner_id = s.id
                LEFT JOIN transitors t ON sr.transitor_id = t.id
                LEFT JOIN trucks tr ON sr.truck_id = tr.id
                LEFT JOIN drivers d ON sr.driver_id = d.id
                WHERE sr.association_id = ? AND sr.delivery_status = 'delivered'
                ORDER BY sr.created_at DESC
            ");
            $completed_requests_stmt->execute([$association_id]);
            $completed_requests = $completed_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Completed service requests fetch error: " . $e->getMessage());
    }
}

// Fetch association's trucks
if ($association_id) {
    try {
        $trucks_stmt = $pdo->prepare("
            SELECT t.*, d.full_name as driver_name
            FROM association_trucks t
            LEFT JOIN drivers d ON t.driver_id = d.id
            WHERE t.association_id = ?
            ORDER BY t.created_at DESC
        ");
        $trucks_stmt->execute([$association_id]);
        $trucks = $trucks_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Trucks fetch error: " . $e->getMessage());
        $trucks = [];
    }
} else {
    $trucks = [];
}

// Fetch association's drivers
if ($association_id) {
    try {
        $drivers_stmt = $pdo->prepare("
            SELECT * FROM drivers
            WHERE association_id = ?
            ORDER BY created_at DESC
        ");
        $drivers_stmt->execute([$association_id]);
        $drivers = $drivers_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Drivers fetch error: " . $e->getMessage());
        $drivers = [];
    }
} else {
    $drivers = [];
}

// Fetch available drivers (not assigned)
$available_drivers = [];
if ($association_id) {
    try {
        $available_drivers_stmt = $pdo->prepare("
            SELECT * FROM drivers
            WHERE association_id = ? AND status = 'available'
            ORDER BY full_name
        ");
        $available_drivers_stmt->execute([$association_id]);
        $available_drivers = $available_drivers_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Available drivers fetch error: " . $e->getMessage());
    }
}

// Fetch available trucks (active and not on trip)
$available_trucks = [];
if ($association_id) {
    try {
        $available_trucks_stmt = $pdo->prepare("
            SELECT * FROM trucks
            WHERE association_id = ? AND status = 'active'
            ORDER BY plate_number
        ");
        $available_trucks_stmt->execute([$association_id]);
        $available_trucks = $available_trucks_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Available trucks fetch error: " . $e->getMessage());
    }
}

// Performance metrics
$performance_metrics = [
    'total_requests' => 0,
    'completed_requests' => 0,
    'completion_rate' => 0,
    'active_trucks' => 0,
    'available_drivers' => 0
];

if ($association_id) {
    try {
        // Total service requests
        $total_requests_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM service_requests WHERE association_id = ?");
        $total_requests_stmt->execute([$association_id]);
        $performance_metrics['total_requests'] = $total_requests_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Completed requests
        $completed_requests_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM service_requests WHERE association_id = ? AND delivery_status = 'delivered'");
        $completed_requests_stmt->execute([$association_id]);
        $performance_metrics['completed_requests'] = $completed_requests_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Completion rate
        $performance_metrics['completion_rate'] = $performance_metrics['total_requests'] > 0 ? 
            round(($performance_metrics['completed_requests'] / $performance_metrics['total_requests']) * 100, 1) : 0;

        // Active trucks
        $active_trucks_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM trucks WHERE association_id = ? AND status = 'active'");
        $active_trucks_stmt->execute([$association_id]);
        $performance_metrics['active_trucks'] = $active_trucks_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Available drivers
        $available_drivers_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM drivers WHERE association_id = ? AND status = 'available'");
        $available_drivers_stmt->execute([$association_id]);
        $performance_metrics['available_drivers'] = $available_drivers_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    } catch (PDOException $e) {
        error_log("Performance metrics error: " . $e->getMessage());
    }
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
    <title>SYC - Association Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        /* All the CSS styles from the original file remain the same */
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
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        /* Top Fixed Section */
        .sidebar-top {
            padding: 30px 25px 30px;
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

        /* Middle Scrollable Section */
        .sidebar-middle {
            flex: 1;
            overflow-y: auto;
            padding: 30px 0;
            max-height: calc(100vh - 200px); /* Adjust based on top and bottom sections */
        }

        .sidebar-nav {
            padding: 0;
        }

        .sidebar-nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
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

        /* Bottom Fixed Section */
        .sidebar-bottom {
            padding: 20px 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: var(--primary-blue);
            flex-shrink: 0;
        }

        .user-profile-link {
            display: block;
            text-decoration: none;
            color: inherit;
            margin-bottom: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
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
            color: white;
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
            text-decoration: none;
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

        .dropdown-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .dropdown-header a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 14px;
        }

        .dropdown-list {
            padding: 10px 0;
        }

        .dropdown-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: #f9f9f9;
        }

        .dropdown-item.unread {
            background: #f0f7ff;
        }

        .dropdown-item-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .dropdown-item-time {
            font-size: 12px;
            color: var(--text-light);
        }

        .dropdown-footer {
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .dropdown-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .notification-icon, .message-icon {
            position: relative;
            cursor: pointer;
            color: var(--dark-gray);
            font-size: 18px;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: var(--transition);
        }

        .user-menu:hover {
            background: var(--light-gray);
        }

        .user-menu-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-menu-name {
            font-weight: 500;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 200px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }

        .user-dropdown.show {
            display: block;
        }

        .user-dropdown a {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: var(--dark-gray);
            transition: var(--transition);
            border-bottom: 1px solid #f5f5f5;
        }

        .user-dropdown a:hover {
            background: var(--light-gray);
            color: var(--primary-blue);
        }

        .user-dropdown a:last-child {
            border-bottom: none;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 30px;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--text-light);
            font-size: 14px;
        }

        .stat-card.blue .stat-icon {
            background: rgba(0, 51, 102, 0.1);
            color: var(--primary-blue);
        }

        .stat-card.green .stat-icon {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-card.yellow .stat-icon {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary-yellow);
        }

        .stat-card.orange .stat-icon {
            background: rgba(255, 165, 0, 0.1);
            color: #ffa500;
        }

        /* Dashboard Sections */
        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-blue);
        }

        .section-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
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
            background: #e0e0e0;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-gray);
            border-bottom: 1px solid #eee;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: #f9f9f9;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-active {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-on-trip {
            background: #fff3cd;
            color: #856404;
        }

        .status-assigned {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
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
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Modal */
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

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-blue);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-light);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-light);
            border-bottom: 2px solid transparent;
            transition: var(--transition);
        }

        .tab.active {
            color: var(--primary-blue);
            border-bottom: 2px solid var(--primary-blue);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--dark-gray);
        }

        /* Responsive */
        @media (max-width: 1024px) {
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

            .mobile-menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .top-header {
                padding: 0 15px;
            }

            .search-bar {
                width: 200px;
            }

            .dashboard-content {
                padding: 20px 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 10px;
        }

        /* Profile section */
        .profile-section {
            display: flex;
            gap: 30px;
        }

        .profile-sidebar {
            width: 250px;
            flex-shrink: 0;
        }

        .profile-content {
            flex: 1;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 600;
            margin: 0 auto 15px;
        }

        .profile-info h3 {
            text-align: center;
            margin-bottom: 5px;
        }

        .profile-info p {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            justify-content: space-between;
            text-align: center;
        }

        .profile-stat h4 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .profile-stat p {
            font-size: 12px;
            color: var(--text-light);
        }

        /* Progress bars */
        .progress-bar {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-blue);
            border-radius: 4px;
        }

        /* Map container */
        .map-container {
            height: 300px;
            background: #f5f5f5;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 15px;
        }

        /* Service request cards */
        .request-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-blue);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .request-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--primary-blue);
        }

        .request-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .request-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: var(--text-light);
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <?php include 'assets/php/sidebar-association.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            
            <div class="header-actions">
                <div class="notification-icon" id="notificationToggle">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="dropdown-header">
                        <h3>Notifications</h3>
                        <a href="#" id="markAllReadBtn">Mark all as read</a>
                    </div>
                    <div class="dropdown-list">
                        <?php if (empty($notifications)): ?>
                            <div class="dropdown-item">
                                <div class="dropdown-item-title">No notifications</div>
                                <div class="dropdown-item-time">You're all caught up!</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="dropdown-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <div class="dropdown-item-title"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="dropdown-item-time"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="#">View all notifications</a>
                    </div>
                </div>
                
                <div class="message-icon" id="messageToggle">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="notification-dropdown" id="messageDropdown">
                    <div class="dropdown-header">
                        <h3>Messages</h3>
                        <a href="#">View all</a>
                    </div>
                    <div class="dropdown-list">
                        <?php if (empty($messages)): ?>
                            <div class="dropdown-item">
                                <div class="dropdown-item-title">No messages</div>
                                <div class="dropdown-item-time">Your inbox is empty</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="dropdown-item <?php echo $message['is_read'] ? '' : 'unread'; ?>">
                                    <div class="dropdown-item-title">From: <?php echo htmlspecialchars($message['sender_name'] ?? 'Unknown'); ?></div>
                                    <div class="dropdown-item-desc"><?php echo htmlspecialchars(substr($message['message'], 0, 50)); ?>...</div>
                                    <div class="dropdown-item-time"><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="#">Go to inbox</a>
                    </div>
                </div>
                
                <div class="user-menu" id="userMenuToggle">
                    <div class="user-menu-avatar"><?php echo $user_initials; ?></div>
                    <div class="user-menu-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <i class="fas fa-chevron-down"></i>
                </div>
                
                <div class="user-dropdown" id="userDropdown">
                    <a href="#profile"><i class="fas fa-user"></i> My Profile</a>
                    <a href="#settings"><i class="fas fa-cog"></i> Settings</a>
                    <a href="#help"><i class="fas fa-question-circle"></i> Help & Support</a>
                    <a href="?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1>Welcome, <?php echo htmlspecialchars($association_name); ?></h1>
                <p>Manage your fleet, drivers, and service requests from one dashboard</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($association_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($association_error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($trucks); ?></h3>
                        <p>Total Trucks</p>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($drivers); ?></h3>
                        <p>Registered Drivers</p>
                    </div>
                </div>
                
                <div class="stat-card yellow">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($active_requests); ?></h3>
                        <p>Active Deliveries</p>
                    </div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $performance_metrics['completion_rate']; ?>%</h3>
                        <p>Completion Rate</p>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Sections -->
            <div class="dashboard-sections">
                <!-- Association Profile Section -->
                <div class="dashboard-section" id="profile">
                    <div class="section-header">
                        <h2><i class="fas fa-user-circle"></i> Association Profile</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="openModal('editProfileModal')">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <div class="profile-sidebar">
                            <div class="profile-card">
                                <div class="profile-avatar"><?php echo $user_initials; ?></div>
                                <h3><?php echo htmlspecialchars($association_name); ?></h3>
                                <p>Truck Association</p>
                                
                                <div class="profile-stats">
                                    <div class="profile-stat">
                                        <h4><?php echo count($trucks); ?></h4>
                                        <p>Trucks</p>
                                    </div>
                                    <div class="profile-stat">
                                        <h4><?php echo count($drivers); ?></h4>
                                        <p>Drivers</p>
                                    </div>
                                    <div class="profile-stat">
                                        <h4><?php echo $performance_metrics['completed_requests']; ?></h4>
                                        <p>Deliveries</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-content">
                            <div class="profile-card">
                                <h3>Association Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Association Name</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo htmlspecialchars($association_data['association_name'] ?? 'Not set'); ?></p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">License Number</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo htmlspecialchars($association_data['license_number'] ?? 'Not set'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Contact Person</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo htmlspecialchars($association_data['contact_person'] ?? 'Not set'); ?></p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Phone</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo htmlspecialchars($association_data['phone'] ?? 'Not set'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Email</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo htmlspecialchars($association_data['association_email'] ?? $association_data['email'] ?? 'Not set'); ?></p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Region</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo htmlspecialchars($association_data['region'] ?? 'Not set'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Registration Status</label>
                                        <p>
                                            <span class="status-badge <?php 
                                                echo ($association_data['registration_status'] ?? 'pending') === 'approved' ? 'status-active' : 
                                                     (($association_data['registration_status'] ?? 'pending') === 'rejected' ? 'status-inactive' : 'status-pending'); 
                                            ?>">
                                                <?php echo ucfirst($association_data['registration_status'] ?? 'pending'); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Member Since</label>
                                        <p class="form-control" style="background: #f8f9fa;"><?php echo date('M j, Y', strtotime($association_data['association_created_at'] ?? $association_data['created_at'] ?? 'now')); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fleet Management Section -->
                <div class="dashboard-section" id="fleet">
                    <div class="section-header">
                        <h2><i class="fas fa-truck"></i> Fleet Management</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="openModal('addTruckModal')">
                                <i class="fas fa-plus"></i> Add Truck
                            </button>
                        </div>
                    </div>
                    
                    <?php if (empty($trucks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-truck"></i>
                            <h3>No trucks registered yet</h3>
                            <p>Add your first truck to get started with service requests</p>
                            <button class="btn btn-primary" onclick="openModal('addTruckModal')">
                                <i class="fas fa-plus"></i> Add First Truck
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Plate Number</th>
                                        <th>Truck Type</th>
                                        <th>Capacity</th>
                                        <th>Assigned Driver</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trucks as $truck): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($truck['plate_number']); ?></td>
                                            <td><?php echo htmlspecialchars($truck['truck_type']); ?></td>
                                            <td><?php echo htmlspecialchars($truck['capacity']); ?> tons</td>
                                            <td>
                                                <?php if ($truck['driver_name']): ?>
                                                    <?php echo htmlspecialchars($truck['driver_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php 
                                                    echo $truck['status'] === 'active' ? 'status-active' : 
                                                         ($truck['status'] === 'on-trip' ? 'status-on-trip' : 'status-inactive'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('-', ' ', $truck['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary" onclick="assignDriverToTruck(<?php echo $truck['id']; ?>)">
                                                    <i class="fas fa-user"></i> Assign Driver
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Drivers Management Section -->
                <div class="dashboard-section" id="drivers">
                    <div class="section-header">
                        <h2><i class="fas fa-id-card"></i> Drivers Management</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="openModal('addDriverModal')">
                                <i class="fas fa-plus"></i> Add Driver
                            </button>
                        </div>
                    </div>
                    
                    <?php if (empty($drivers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-id-card"></i>
                            <h3>No drivers registered yet</h3>
                            <p>Add your first driver to assign them to trucks</p>
                            <button class="btn btn-primary" onclick="openModal('addDriverModal')">
                                <i class="fas fa-plus"></i> Add First Driver
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>License Number</th>
                                        <th>Phone</th>
                                        <th>Experience</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($drivers as $driver): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($driver['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['experience_years'] ?? 0); ?> years</td>
                                            <td>
                                                <span class="status-badge <?php 
                                                    echo $driver['status'] === 'available' ? 'status-available' : 
                                                         ($driver['status'] === 'on-trip' ? 'status-on-trip' : 'status-assigned'); 
                                                ?>">
                                                    <?php echo ucfirst($driver['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary" onclick="assignDriver(<?php echo $driver['id']; ?>)">
                                                    <i class="fas fa-truck"></i> Assign to Truck
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Service Requests Section -->
                <div class="dashboard-section" id="requests">
                    <div class="section-header">
                        <h2><i class="fas fa-clipboard-list"></i> Available Service Requests</h2>
                    </div>
                    
                    <?php if (empty($available_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No available service requests</h3>
                            <p>New service requests from transitors will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="requests-list">
                            <?php foreach ($available_requests as $request): ?>
                                <div class="request-card">
                                    <div class="request-header">
                                        <div class="request-title">Request #<?php echo $request['id']; ?> - <?php echo htmlspecialchars($request['cargo_description']); ?></div>
                                        <span class="status-badge status-pending">Pending</span>
                                    </div>
                                    
                                    <div class="request-meta">
                                        <div class="request-meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($request['origin']); ?> → <?php echo htmlspecialchars($request['destination']); ?></span>
                                        </div>
                                        <div class="request-meta-item">
                                            <i class="fas fa-weight-hanging"></i>
                                            <span><?php echo htmlspecialchars($request['cargo_weight']); ?> tons</span>
                                        </div>
                                        <div class="request-meta-item">
                                            <i class="fas fa-user"></i>
                                            <span>Shipper: <?php echo htmlspecialchars($request['shipper_company'] ?? 'Unknown'); ?></span>
                                        </div>
                                        <div class="request-meta-item">
                                            <i class="fas fa-building"></i>
                                            <span>Transitor: <?php echo htmlspecialchars($request['transitor_company'] ?? 'Unknown'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="request-actions">
                                        <button class="btn btn-primary btn-sm" onclick="acceptServiceRequest(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-check"></i> Accept Request
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="viewRequestDetails(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Deliveries Section -->
                <div class="dashboard-section" id="active">
                    <div class="section-header">
                        <h2><i class="fas fa-shipping-fast"></i> Active Deliveries</h2>
                    </div>
                    
                    <?php if (empty($active_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shipping-fast"></i>
                            <h3>No active deliveries</h3>
                            <p>Accepted service requests will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Route</th>
                                        <th>Cargo</th>
                                        <th>Truck</th>
                                        <th>Driver</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_requests as $request): ?>
                                        <tr>
                                            <td>#<?php echo $request['id']; ?></td>
                                            <td><?php echo htmlspecialchars($request['origin']); ?> → <?php echo htmlspecialchars($request['destination']); ?></td>
                                            <td><?php echo htmlspecialchars($request['cargo_description']); ?> (<?php echo htmlspecialchars($request['cargo_weight']); ?> tons)</td>
                                            <td><?php echo htmlspecialchars($request['truck_plate'] ?? 'Not assigned'); ?></td>
                                            <td><?php echo htmlspecialchars($request['driver_name'] ?? 'Not assigned'); ?></td>
                                            <td>
                                                <span class="status-badge <?php 
                                                    echo $request['delivery_status'] === 'in transit' ? 'status-active' : 'status-pending'; 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $request['delivery_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="updateDeliveryStatus(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Update Status
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Delivery History Section -->
                <div class="dashboard-section" id="history">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i> Delivery History</h2>
                    </div>
                    
                    <?php if (empty($completed_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No delivery history</h3>
                            <p>Completed deliveries will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Route</th>
                                        <th>Cargo</th>
                                        <th>Truck</th>
                                        <th>Driver</th>
                                        <th>Completed Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_requests as $request): ?>
                                        <tr>
                                            <td>#<?php echo $request['id']; ?></td>
                                            <td><?php echo htmlspecialchars($request['origin']); ?> → <?php echo htmlspecialchars($request['destination']); ?></td>
                                            <td><?php echo htmlspecialchars($request['cargo_description']); ?> (<?php echo htmlspecialchars($request['cargo_weight']); ?> tons)</td>
                                            <td><?php echo htmlspecialchars($request['truck_plate'] ?? 'Not assigned'); ?></td>
                                            <td><?php echo htmlspecialchars($request['driver_name'] ?? 'Not assigned'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['updated_at'] ?? $request['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    
    <!-- Edit Profile Modal -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Association Profile</h3>
                <button class="modal-close" onclick="closeModal('editProfileModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($association_data['contact_person'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($association_data['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Region</label>
                        <select class="form-control" name="region" required>
                            <option value="">Select Region</option>
                            <option value="Addis Ababa" <?php echo ($association_data['region'] ?? '') === 'Addis Ababa' ? 'selected' : ''; ?>>Addis Ababa</option>
                            <option value="Afar" <?php echo ($association_data['region'] ?? '') === 'Afar' ? 'selected' : ''; ?>>Afar</option>
                            <option value="Amhara" <?php echo ($association_data['region'] ?? '') === 'Amhara' ? 'selected' : ''; ?>>Amhara</option>
                            <option value="Benishangul-Gumuz" <?php echo ($association_data['region'] ?? '') === 'Benishangul-Gumuz' ? 'selected' : ''; ?>>Benishangul-Gumuz</option>
                            <option value="Dire Dawa" <?php echo ($association_data['region'] ?? '') === 'Dire Dawa' ? 'selected' : ''; ?>>Dire Dawa</option>
                            <option value="Gambela" <?php echo ($association_data['region'] ?? '') === 'Gambela' ? 'selected' : ''; ?>>Gambela</option>
                            <option value="Harari" <?php echo ($association_data['region'] ?? '') === 'Harari' ? 'selected' : ''; ?>>Harari</option>
                            <option value="Oromia" <?php echo ($association_data['region'] ?? '') === 'Oromia' ? 'selected' : ''; ?>>Oromia</option>
                            <option value="Sidama" <?php echo ($association_data['region'] ?? '') === 'Sidama' ? 'selected' : ''; ?>>Sidama</option>
                            <option value="Somali" <?php echo ($association_data['region'] ?? '') === 'Somali' ? 'selected' : ''; ?>>Somali</option>
                            <option value="Southern Nations" <?php echo ($association_data['region'] ?? '') === 'Southern Nations' ? 'selected' : ''; ?>>Southern Nations</option>
                            <option value="Tigray" <?php echo ($association_data['region'] ?? '') === 'Tigray' ? 'selected' : ''; ?>>Tigray</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editProfileModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_profile">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Truck Modal -->
    <div class="modal" id="addTruckModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register New Truck</h3>
                <button class="modal-close" onclick="closeModal('addTruckModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Plate Number</label>
                            <input type="text" class="form-control" name="plate_number" placeholder="e.g., 3-AAA-1234" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Truck Type</label>
                            <select class="form-control" name="truck_type" required>
                                <option value="">Select Type</option>
                                <option value="flatbed">Flatbed</option>
                                <option value="refrigerated">Refrigerated</option>
                                <option value="container">Container</option>
                                <option value="tanker">Tanker</option>
                                <option value="dump">Dump Truck</option>
                                <option value="box">Box Truck</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Capacity (tons)</label>
                            <input type="number" class="form-control" name="capacity" step="0.1" min="1" max="50" placeholder="e.g., 10.5" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assign Driver (Optional)</label>
                            <select class="form-control" name="driver_id">
                                <option value="">Select Driver</option>
                                <?php foreach ($available_drivers as $driver): ?>
                                    <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?> (<?php echo htmlspecialchars($driver['license_number']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTruckModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="register_truck">Register Truck</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Driver Modal -->
    <div class="modal" id="addDriverModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register New Driver</h3>
                <button class="modal-close" onclick="closeModal('addDriverModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" placeholder="e.g., Alemayehu Kebede" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" placeholder="e.g., ET123456789" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="e.g., +251911223344" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" name="experience_years" min="0" max="50" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addDriverModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="register_driver">Register Driver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Driver Modal -->
    <div class="modal" id="assignDriverModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Driver to Truck</h3>
                <button class="modal-close" onclick="closeModal('assignDriverModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="assignDriverForm">
                    <input type="hidden" name="truck_id" id="assignTruckId">
                    <div class="form-group">
                        <label class="form-label">Select Driver</label>
                        <select class="form-control" name="driver_id" required>
                            <option value="">Select Driver</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?> (<?php echo htmlspecialchars($driver['license_number']); ?>) - Available</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('assignDriverModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="assign_driver">Assign Driver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Accept Service Request Modal -->
    <div class="modal" id="acceptRequestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Accept Service Request</h3>
                <button class="modal-close" onclick="closeModal('acceptRequestModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="acceptRequestForm">
                    <input type="hidden" name="service_request_id" id="serviceRequestId">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Select Truck</label>
                            <select class="form-control" name="truck_id" required>
                                <option value="">Select Truck</option>
                                <?php foreach ($available_trucks as $truck): ?>
                                    <option value="<?php echo $truck['id']; ?>"><?php echo htmlspecialchars($truck['plate_number']); ?> - <?php echo htmlspecialchars($truck['truck_type']); ?> (<?php echo htmlspecialchars($truck['capacity']); ?> tons)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Select Driver</label>
                            <select class="form-control" name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($available_drivers as $driver): ?>
                                    <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?> (<?php echo htmlspecialchars($driver['license_number']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('acceptRequestModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="accept_service_request">Accept Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Delivery Status Modal -->
    <div class="modal" id="updateStatusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Delivery Status</h3>
                <button class="modal-close" onclick="closeModal('updateStatusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="updateStatusForm">
                    <input type="hidden" name="service_request_id" id="statusRequestId">
                    <div class="form-group">
                        <label class="form-label">Delivery Status</label>
                        <select class="form-control" name="delivery_status" required>
                            <option value="accepted">Accepted</option>
                            <option value="in transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('updateStatusModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_delivery_status">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Notification dropdown
        document.getElementById('notificationToggle').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('notificationDropdown').classList.toggle('show');
            document.getElementById('messageDropdown').classList.remove('show');
            document.getElementById('userDropdown').classList.remove('show');
        });

        // Message dropdown
        document.getElementById('messageToggle').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('messageDropdown').classList.toggle('show');
            document.getElementById('notificationDropdown').classList.remove('show');
            document.getElementById('userDropdown').classList.remove('show');
        });

        // User menu dropdown
        document.getElementById('userMenuToggle').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('userDropdown').classList.toggle('show');
            document.getElementById('notificationDropdown').classList.remove('show');
            document.getElementById('messageDropdown').classList.remove('show');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.getElementById('notificationDropdown').classList.remove('show');
            document.getElementById('messageDropdown').classList.remove('show');
            document.getElementById('userDropdown').classList.remove('show');
        });

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Driver assignment functions
        function assignDriverToTruck(truckId) {
            document.getElementById('assignTruckId').value = truckId;
            openModal('assignDriverModal');
        }

        function assignDriver(driverId) {
            // This could be enhanced to show available trucks for this driver
            alert('Select a truck to assign this driver to');
        }

        // Service request functions
        function acceptServiceRequest(requestId) {
            document.getElementById('serviceRequestId').value = requestId;
            openModal('acceptRequestModal');
        }

        function viewRequestDetails(requestId) {
            alert('Viewing details for request #' + requestId);
            // In a real implementation, this would show a detailed view of the request
        }

        function updateDeliveryStatus(requestId) {
            document.getElementById('statusRequestId').value = requestId;
            openModal('updateStatusModal');
        }

        // Tab functionality
        function openTab(evt, tabName) {
            const tabcontent = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            const tablinks = document.getElementsByClassName("tab");
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Smooth scrolling for sidebar navigation
        document.querySelectorAll('.sidebar-nav a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    document.querySelector('.sidebar').classList.remove('active');
                }
            });
        });

        // Handle mark all notifications as read
        document.getElementById('markAllReadBtn').addEventListener('click', function(e) {
            e.preventDefault();

            // Create a form to submit POST request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'mark_all_read';
            input.value = '1';

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        });

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // In a real implementation, this would fetch new notifications via AJAX
            console.log('Checking for new notifications...');
        }, 30000);

        // Initialize the first tab as active
        document.addEventListener('DOMContentLoaded', function() {
            const firstTab = document.querySelector('.tab');
            if (firstTab) {
                firstTab.classList.add('active');
            }
            
            const firstTabContent = document.querySelector('.tab-content');
            if (firstTabContent) {
                firstTabContent.classList.add('active');
            }
        });
    </script>
</body>
</html>