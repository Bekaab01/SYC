<?php

// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();

// Include database connection
include_once __DIR__ . '/../private/db.php';

// Handle tab parameter
$allowed_tabs = [
    'dashboard','requests','drivers','registered-trucks',
    'available-carriers','matching','active-requests','reports','profile'
];
$current_tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'dashboard';
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
        } elseif ($existing_association['registration_status'] === 'approved') {
            // Check if all documents are approved
            $doc_check_stmt = $pdo->prepare("SELECT COUNT(*) as total_docs, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_docs FROM association_documents WHERE association_id = ?");
            $doc_check_stmt->execute([$existing_association['id']]);
            $doc_status = $doc_check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($doc_status['total_docs'] == 0 || $doc_status['approved_docs'] != $doc_status['total_docs']) {
                // Not all documents are approved, redirect to pending approval page
                header('Location: association-pending-approval.php');
                exit();
            }
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
        $welcome_notification = "Welcome to Spot Your Cargo Association Dashboard! Start managing your fleet and drivers.";
        createNotification($pdo, $user_syc_id, $welcome_notification);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_truck'])) {
    $plate_number = $_POST['plate_number'] ?? '';
    $truck_type = $_POST['truck_type'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $driver_id = $_POST['driver_id'] ?? null;

    // Convert empty driver_id to NULL to avoid foreign key violation
    if (empty($driver_id) || !is_numeric($driver_id)) {
        $driver_id = null;
    }

    $status = 'active';

    if ($association_id) {
        try {
            $temp_error = "";
            if ($driver_id) {
                $check_driver = $pdo->prepare("SELECT id FROM drivers WHERE id = ? AND association_id = ? AND status = 'available'");
                $check_driver->execute([$driver_id, $association_id]);
                if (!$check_driver->fetch()) {
                    $temp_error = "Selected driver is not available or does not exist.";
                } else {
                    // Unassign driver from any other truck
                    $unassign_stmt = $pdo->prepare("UPDATE association_trucks SET driver_id = NULL WHERE driver_id = ? AND association_id = ?");
                    $unassign_stmt->execute([$driver_id, $association_id]);
                }
            }

            if (empty($temp_error)) {
                $stmt = $pdo->prepare("
                    INSERT INTO association_trucks (association_id, plate_number, truck_type, capacity, driver_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$association_id, $plate_number, $truck_type, $capacity, $driver_id, $status]);

                if ($stmt->rowCount() > 0) {
                    if ($driver_id) {
                        // Update driver status
                        $update_driver = $pdo->prepare("UPDATE drivers SET status = 'assigned', updated_at = NOW() WHERE id = ?");
                        $update_driver->execute([$driver_id]);
                    }

                    $success_message = "Truck registered successfully!";

                    // Update number of trucks in associations table
                    $update_truck_count = $pdo->prepare("
                        UPDATE associations
                        SET number_of_trucks = (SELECT COUNT(*) FROM association_trucks WHERE association_id = ? AND deleted_at IS NULL),
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
            } else {
                $association_error = $temp_error;
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
            $unassign_stmt = $pdo->prepare("UPDATE association_trucks SET driver_id = NULL WHERE driver_id = ? AND association_id = ?");
            $unassign_stmt->execute([$driver_id, $association_id]);

            // Assign driver to selected truck
            $assign_stmt = $pdo->prepare("UPDATE association_trucks SET driver_id = ?, updated_at = NOW() WHERE id = ? AND association_id = ?");
            $assign_stmt->execute([$driver_id, $truck_id, $association_id]);

            if ($assign_stmt->rowCount() > 0) {
                $success_message = "Driver assigned to truck successfully!";

                // Update driver status
                $update_driver = $pdo->prepare("UPDATE drivers SET status = 'assigned', updated_at = NOW() WHERE id = ?");
                $update_driver->execute([$driver_id]);

                // Add notification
                $truck_info = $pdo->prepare("SELECT plate_number FROM association_trucks WHERE id = ?");
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

// Handle driver unassignment from truck
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign_driver'])) {
    $driver_id = $_POST['driver_id'] ?? '';

    if ($association_id && $driver_id) {
        try {
            // Unassign driver from truck
            $unassign_stmt = $pdo->prepare("UPDATE association_trucks SET driver_id = NULL, updated_at = NOW() WHERE driver_id = ? AND association_id = ?");
            $unassign_stmt->execute([$driver_id, $association_id]);

            if ($unassign_stmt->rowCount() > 0) {
                $success_message = "Driver unassigned from truck successfully!";

                // Update driver status to available
                $update_driver = $pdo->prepare("UPDATE drivers SET status = 'available', updated_at = NOW() WHERE id = ?");
                $update_driver->execute([$driver_id]);

                // Add notification
                $driver_info = $pdo->prepare("SELECT full_name FROM drivers WHERE id = ?");
                $driver_info->execute([$driver_id]);
                $driver_data = $driver_info->fetch(PDO::FETCH_ASSOC);

                $notification_message = "Driver " . htmlspecialchars($driver_data['full_name']) . " unassigned from truck";
                createNotification($pdo, $user_syc_id, $notification_message);

            } else {
                $association_error = "Error unassigning driver from truck. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Driver unassignment error: " . $e->getMessage());
            $association_error = "Error unassigning driver: " . $e->getMessage();
        }
    } else {
        $association_error = "Invalid driver selection.";
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
                $update_truck = $pdo->prepare("UPDATE association_trucks SET status = 'on-trip', updated_at = NOW() WHERE id = ?");
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
                        $update_truck = $pdo->prepare("UPDATE association_trucks SET status = 'active', updated_at = NOW() WHERE id = ?");
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
                       u.email as shipper_email,
                       sr.origin_geo,
                       sr.dest_geo
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
            WHERE t.association_id = ? AND t.deleted_at IS NULL
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
            SELECT d.*, t.plate_number as assigned_truck_plate
            FROM drivers d
            LEFT JOIN association_trucks t ON d.id = t.driver_id
            WHERE d.association_id = ?
            ORDER BY d.created_at DESC
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

    // Fetch invoices
    $invoices = [];
    if ($association_id) {
        try {
            $invoices_stmt = $pdo->prepare("
                SELECT i.*,
                       sr.origin_geo, sr.dest_geo,
                       sr.cargo_description, sr.cargo_weight, sr.weight_unit,
                       s.company_name as shipper_name,
                       t.company_name as transitor_name
                FROM invoices i
                LEFT JOIN service_requests sr ON i.load_id = sr.id
                LEFT JOIN shippers s ON sr.cargo_owner_id = s.id
                LEFT JOIN transitors t ON sr.transitor_id = t.id
                WHERE i.association_id = ?
                ORDER BY i.created_at DESC
            ");
            $invoices_stmt->execute([$association_id]);
            $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Invoices fetch error: " . $e->getMessage());
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot Your Cargo - Association Dashboard</title>
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
            transition: var(--transition);
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
            transition: var(--transition);
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

        .header-left {
            display: flex;
            align-items: center;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 10px;
            margin-right: 10px;
            border-radius: 5px;
            transition: var(--transition);
        }

        .menu-toggle:hover {
            background: rgba(0, 0, 0, 0.05);
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
        }

        /* Additional responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                left: -100%;
            }

            .sidebar.mobile-open {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
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

            /* Mobile menu toggle button */
            .menu-toggle {
                display: block;
            }

            /* Sidebar overlay for mobile */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 98;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        /* Hide mobile menu toggle on desktop */
        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
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

        /* Dashboard Content */
        .dashboard-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
        }

        .welcome-text h2 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }

        .banner-cta {
            background: var(--primary-yellow);
            color: var(--primary-blue);
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .banner-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .stat-title {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }

        .stat-change {
            font-size: 12px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change i {
            color: var(--primary-blue);
        }

        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid #f0f0f0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-gray);
            margin: 0;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-blue);
            transform: translateY(-1px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Forms */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-gray);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            max-width: 100%;
            box-sizing: border-box;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        .status-on-trip {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-available {
            background: rgba(0, 123, 255, 0.1);
            color: #004085;
        }

        .status-accepted {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-in-transit {
            background: rgba(0, 123, 255, 0.1);
            color: #004085;
        }

        .status-delivered {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 60%;
            max-width: 600px;
            max-height: 80vh;
            padding: 20px 30px;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
            margin: 20px auto;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-gray);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: #f8f9fa;
            color: var(--dark-gray);
        }

        .modal-body {
            padding: 30px;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Top Fixed Section: Logo -->
        <div class="sidebar-top">
            <a href="index.php" class="sidebar-logo">
                <img src="assets/img/SYC-Transparent.png" alt="Spot Your Cargo" style="filter: brightness(0) invert(1);">
                <span class="sidebar-logo-text">Spot Your Cargo.</span>
            </a>
        </div>

        <!-- Middle Scrollable Section: Navigation Menu -->
        <div class="sidebar-middle">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="?tab=dashboard" class="<?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="?tab=requests" class="<?php echo $current_tab === 'requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Service Requests</a></li>
                    <li><a href="?tab=registered-trucks" class="<?php echo $current_tab === 'registered-trucks' ? 'active' : ''; ?>"><i class="fas fa-truck"></i> Registered Trucks</a></li>
                    <li><a href="?tab=drivers" class="<?php echo $current_tab === 'drivers' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Drivers</a></li>
                    <li><a href="?tab=available-carriers" class="<?php echo $current_tab === 'available-carriers' ? 'active' : ''; ?>"><i class="fas fa-truck-moving"></i> Available Carriers</a></li>
                    <li><a href="?tab=matching" class="<?php echo $current_tab === 'matching' ? 'active' : ''; ?>"><i class="fas fa-handshake"></i> Matching</a></li>
                    <li><a href="?tab=active-requests" class="<?php echo $current_tab === 'active-requests' ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Active Requests</a></li>
                    <li><a href="?tab=reports" class="<?php echo $current_tab === 'reports' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="?tab=invoices" class="<?php echo $current_tab === 'invoices' ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
                    <li><a href="?tab=profile" class="<?php echo $current_tab === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </nav>
        </div>

        <!-- Bottom Fixed Section: User Info + Logout -->
        <div class="sidebar-bottom">
            <a href="?tab=profile" class="user-profile-link">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_name); ?></h4>
                        <p><?php echo htmlspecialchars($association_name); ?></p>
                    </div>
                </div>
            </a>
            <button class="logout-btn" onclick="window.location.href='?logout=1'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search..." id="global-search">
                </div>
            </div>

            <div class="header-actions">
                <a href="#" class="notification-icon" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </a>

                <a href="#" class="message-icon" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Error/Success Messages -->
            <?php if (!empty($association_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($association_error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($current_tab === 'dashboard'): ?>
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="welcome-text">
                        <h2>Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</h2>
                        <p>Manage your fleet, service requests, and operations from your association dashboard.</p>
                    </div>
                    <button class="banner-cta" onclick="showTruckModal()">
                        <i class="fas fa-plus"></i> Register Truck
                    </button>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Total Trucks</div>
                            <div class="stat-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo count($trucks); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i> Registered
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Active Requests</div>
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo count($active_requests); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-clock"></i> In progress
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Available Drivers</div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo count($available_drivers); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-check-circle"></i> Ready
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Completion Rate</div>
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $performance_metrics['completion_rate']; ?>%</div>
                        <div class="stat-change">
                            <i class="fas fa-trophy"></i> This month
                        </div>
                    </div>
                </div>

                <!-- Recent Service Requests -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Available Service Requests</h3>
                        <a href="?tab=requests" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>

                    <?php if (empty($available_requests)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No available service requests at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Shipper</th>
                                        <th>Origin</th>
                                        <th>Destination</th>
                                        <th>Cargo Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($available_requests, 0, 5) as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['id']); ?></td>
                                            <td><?php echo htmlspecialchars($request['shipper_company']); ?></td>
                                            <td><?php echo htmlspecialchars($request['origin_geo']); ?></td>
                                            <td><?php echo htmlspecialchars($request['dest_geo']); ?></td>
                                            <td><?php echo htmlspecialchars($request['cargo_type']); ?></td>
                                            <td>
                                                <button class="btn btn-success btn-sm" onclick="acceptRequest(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Trucks -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Your Fleet</h3>
                        <a href="?tab=registered-trucks" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>

                    <?php if (empty($trucks)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-truck" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No trucks registered yet. Add your first truck to get started!</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                            <?php foreach (array_slice($trucks, 0, 3) as $truck): ?>
                                <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                        <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; margin-right: 15px;">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0; font-size: 16px;"><?php echo htmlspecialchars($truck['plate_number']); ?></h4>
                                            <p style="margin: 5px 0 0 0; color: var(--text-light); font-size: 14px;">
                                                <?php echo htmlspecialchars($truck['truck_type']); ?> - <?php echo htmlspecialchars($truck['capacity']); ?> tons
                                            </p>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <small style="color: var(--text-light);">Status:</small>
                                            <span class="status-badge status-<?php echo strtolower($truck['status']); ?>">
                                                <?php echo htmlspecialchars($truck['status']); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($truck['driver_name'])): ?>
                                            <small style="color: var(--text-light);">Driver: <?php echo htmlspecialchars($truck['driver_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_tab === 'requests'): ?>
                <!-- Service Requests Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Available Service Requests</h3>
                    </div>

                    <?php if (empty($available_requests)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No available service requests at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Shipper</th>
                                        <th>Contact</th>
                                        <th>Origin</th>
                                        <th>Destination</th>
                                        <th>Cargo Type</th>
                                        <th>Weight</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['id']); ?></td>
                                            <td><?php echo htmlspecialchars($request['shipper_company']); ?></td>
                                            <td><?php echo htmlspecialchars($request['shipper_contact']); ?><br><small><?php echo htmlspecialchars($request['shipper_phone']); ?></small></td>
                                            <td><?php echo htmlspecialchars($request['origin_geo']); ?></td>
                                            <td><?php echo htmlspecialchars($request['dest_geo']); ?></td>
                                            <td><?php echo htmlspecialchars($request['cargo_type']); ?></td>
                                            <td><?php echo htmlspecialchars($request['weight'] . ' ' . $request['weight_unit']); ?></td>
                                            <td>
                                                <button class="btn btn-success btn-sm" onclick="acceptRequest(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_tab === 'drivers'): ?>
                <!-- Drivers Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Drivers</h3>
                        <button class="btn btn-primary" onclick="showDriverModal()">
                            <i class="fas fa-plus"></i> Register New Driver
                        </button>
                    </div>

                    <?php if (empty($drivers)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No drivers registered yet. Add your first driver to get started!</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Full Name</th>
                                        <th>License Number</th>
                                        <th>Phone</th>
                                        <th>Experience</th>
                                        <th>Status</th>
                                        <th>Assigned Truck</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($drivers as $driver): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($driver['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($driver['experience_years']); ?> years</td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($driver['status']); ?>">
                                                    <?php echo htmlspecialchars($driver['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($driver['assigned_truck_plate'] ?? 'Unassigned'); ?></td>
                                            <td>
                                                <button class="btn btn-secondary btn-sm" onclick="editDriver(<?php echo $driver['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($driver['status'] === 'assigned'): ?>
                                                    <button class="btn btn-warning btn-sm" onclick="unassignDriver(<?php echo $driver['id']; ?>)">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_tab === 'registered-trucks'): ?>
                <!-- Registered Trucks Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Registered Trucks</h3>
                        <button class="btn btn-primary" onclick="showTruckModal()">
                            <i class="fas fa-plus"></i> Register New Truck
                        </button>
                    </div>

                    <?php if (empty($trucks)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-truck" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No trucks registered yet. Add your first truck to get started!</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Plate Number</th>
                                        <th>Truck Type</th>
                                        <th>Capacity</th>
                                        <th>Driver</th>
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
                                            <td><?php echo htmlspecialchars($truck['driver_name'] ?? 'Unassigned'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($truck['status']); ?>">
                                                    <?php echo htmlspecialchars($truck['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-secondary btn-sm" onclick="assignDriver(<?php echo $truck['id']; ?>)">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_tab === 'active-requests'): ?>
                <!-- Active Requests Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Active Service Requests</h3>
                    </div>

                    <?php if (empty($active_requests)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-tasks" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No active service requests.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Shipper</th>
                                        <th>Origin</th>
                                        <th>Destination</th>
                                        <th>Truck</th>
                                        <th>Driver</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['id']); ?></td>
                                            <td><?php echo htmlspecialchars($request['shipper_company']); ?></td>
                                            <td><?php echo htmlspecialchars($request['origin_geo']); ?></td>
                                            <td><?php echo htmlspecialchars($request['dest_geo']); ?></td>
                                            <td><?php echo htmlspecialchars($request['truck_plate'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($request['driver_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['delivery_status'])); ?>">
                                                    <?php echo htmlspecialchars($request['delivery_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="updateStatus(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_tab === 'profile'): ?>
                <!-- Profile Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Association Profile</h3>
                    </div>

                    <form method="POST" style="max-width: 600px;">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Association Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($association_data['association_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($association_data['contact_person'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($association_data['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Region</label>
                                <input type="text" name="region" class="form-control" value="<?php echo htmlspecialchars($association_data['region'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">License Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($association_data['license_number'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Number of Trucks</label>
                                <input type="number" class="form-control" value="<?php echo htmlspecialchars($association_data['number_of_trucks'] ?? 0); ?>" readonly>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

            <?php elseif ($current_tab === 'reports'): ?>
                <!-- Reports Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Performance Reports</h3>
                    </div>

                    <!-- Performance Metrics Summary -->
                    <div class="stats-grid" style="margin-bottom: 30px;">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-title">Total Service Requests</div>
                                <div class="stat-icon">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $performance_metrics['total_requests']; ?></div>
                            <div class="stat-change">
                                <i class="fas fa-calendar"></i> All time
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-title">Completed Requests</div>
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $performance_metrics['completed_requests']; ?></div>
                            <div class="stat-change">
                                <i class="fas fa-trophy"></i> Successful deliveries
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-title">Completion Rate</div>
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $performance_metrics['completion_rate']; ?>%</div>
                            <div class="stat-change">
                                <i class="fas fa-arrow-up"></i> Efficiency metric
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-title">Active Trucks</div>
                                <div class="stat-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $performance_metrics['active_trucks']; ?></div>
                            <div class="stat-change">
                                <i class="fas fa-cogs"></i> Operational fleet
                            </div>
                        </div>
                    </div>

                    <!-- Completed Service Requests Report -->
                    <div class="content-section" style="margin-top: 30px;">
                        <div class="section-header">
                            <h3 class="section-title">Completed Service Requests</h3>
                        </div>

                        <?php if (empty($completed_requests)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-light);">
                                <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 20px;"></i>
                                <p>No completed service requests yet.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Shipper</th>
                                            <th>Origin</th>
                                            <th>Destination</th>
                                            <th>Truck</th>
                                            <th>Driver</th>
                                            <th>Completed At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completed_requests as $request): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                                <td><?php echo htmlspecialchars($request['shipper_company']); ?></td>
                                                <td><?php echo htmlspecialchars($request['origin_geo']); ?></td>
                                                <td><?php echo htmlspecialchars($request['dest_geo']); ?></td>
                                                <td><?php echo htmlspecialchars($request['truck_plate'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($request['driver_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($request['updated_at']))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Invoice Summary Report -->
                    <div class="content-section" style="margin-top: 30px;">
                        <div class="section-header">
                            <h3 class="section-title">Invoice Summary</h3>
                        </div>

                        <?php
                        // Calculate invoice totals
                        $total_invoiced = 0;
                        $paid_invoices = 0;
                        $pending_invoices = 0;
                        foreach ($invoices as $invoice) {
                            $total_invoiced += $invoice['amount'] ?? 0;
                            if (strtolower($invoice['status'] ?? '') === 'paid') {
                                $paid_invoices += $invoice['amount'] ?? 0;
                            } else {
                                $pending_invoices += $invoice['amount'] ?? 0;
                            }
                        }
                        ?>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Total Invoiced</div>
                                    <div class="stat-icon">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                </div>
                                <div class="stat-value">$<?php echo number_format($total_invoiced, 2); ?></div>
                                <div class="stat-change">
                                    <i class="fas fa-receipt"></i> All invoices
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Paid Amount</div>
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <div class="stat-value">$<?php echo number_format($paid_invoices, 2); ?></div>
                                <div class="stat-change">
                                    <i class="fas fa-money-bill-wave"></i> Received
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Pending Amount</div>
                                    <div class="stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <div class="stat-value">$<?php echo number_format($pending_invoices, 2); ?></div>
                                <div class="stat-change">
                                    <i class="fas fa-hourglass-half"></i> Outstanding
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Total Invoices</div>
                                    <div class="stat-icon">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </div>
                                </div>
                                <div class="stat-value"><?php echo count($invoices); ?></div>
                                <div class="stat-change">
                                    <i class="fas fa-list"></i> Generated
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_tab === 'invoices'): ?>
                <!-- Invoices Tab -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">Invoices</h3>
                    </div>

                    <?php if (empty($invoices)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No invoices found.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice ID</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                                            <td>$<?php echo htmlspecialchars(number_format($invoice['amount'] ?? 0, 2)); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($invoice['status'] ?? 'pending'); ?>">
                                                    <?php echo htmlspecialchars($invoice['status'] ?? 'Pending'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['created_at']))); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Invoice Detail Modal -->
                <div class="modal" id="invoice-modal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
                    <div class="modal-content" style="max-width: 900px; width: 100%; max-height: 80vh; overflow-y: auto; padding: 20px;">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee;">
                            <h3 class="modal-title">Invoice Details</h3>
                            <button class="modal-close" onclick="closeModal('invoice-modal')">&times;</button>
                        </div>
                        <div class="modal-body" id="invoice-detail-content" style="padding: 20px;">
                            <!-- Invoice details will be rendered here dynamically -->
                            <p>Loading invoice details...</p>
                        </div>
                    </div>
                </div>

                <script>
                    function closeModal(modalId) {
                        const modal = document.getElementById(modalId);
                        if (modal) modal.classList.remove('show');
                    }

                    function viewInvoice(invoiceId) {
                        const modal = document.getElementById('invoice-modal');
                        const content = document.getElementById('invoice-detail-content');
                        if (!modal || !content) return;

                        // Show loading text
                        content.innerHTML = '<p>Loading invoice details...</p>';
                        modal.classList.add('show');

                        fetch('api/get_invoice.php?invoice_id=' + invoiceId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                content.innerHTML = '<p style="color:red;">Error: ' + data.error + '</p>';
                                return;
                            }

                            // Render invoice details based on data using invoice-template.html structure
                            const html = `
                                <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333;">
                                    <h3>Invoice #${data.id}</h3>
                                    <p><strong>Date:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>
                                    <p><strong>Status:</strong> ${data.status || 'Pending'}</p>
                                    <h4>Billed To (Shipper)</h4>
                                    <p><strong>Name:</strong> ${data.shipper_name || 'N/A'}</p>
                                    <p><strong>Phone:</strong> ${data.shipper_phone || 'N/A'}</p>
                                    <p><strong>Company:</strong> ${data.shipper_name || 'N/A'}</p>
                                    <p><strong>Address:</strong> ${data.shipper_address || 'N/A'}</p>
                                    <p><strong>Email:</strong> ${data.shipper_email || 'N/A'}</p>
                                    <h4>Billing Breakdown</h4>
                                    <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                                        <thead>
                                            <tr>
                                                <th style="border: 1px solid #ccc; padding: 6px;">Description</th>
                                                <th style="border: 1px solid #ccc; padding: 6px;">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="border: 1px solid #ccc; padding: 6px;">Amount</td>
                                                <td style="border: 1px solid #ccc; padding: 6px;">$${(data.amount || 0).toFixed(2)}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <h4>Related Load Details</h4>
                                    <p><strong>Origin:</strong> ${data.origin_geo || ''}</p>
                                    <p><strong>Destination:</strong> ${data.dest_geo || ''}</p>
                                    <p><strong>Cargo Description:</strong> ${data.cargo_description || 'N/A'}</p>
                                    <p><strong>Cargo Weight:</strong> ${(data.cargo_weight || 'N/A')} ${data.weight_unit || ''}</p>
                                    <h4>Other Details</h4>
                                    <p><strong>Transitor:</strong> ${data.transitor_name || 'N/A'}</p>
                                    <p><em>Invoice created at: ${new Date(data.created_at).toLocaleString()}</em></p>
                                </div>
                            `;
                            content.innerHTML = html;
                        })
                        .catch(err => {
                            content.innerHTML = '<p style="color:red;">Error fetching invoice details.</p>';
                            console.error(err);
                        });
                    }
                </script>
                        <h3 class="section-title">Invoices</h3>
                    </div>

                    <?php if (empty($invoices)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <p>No invoices found.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice ID</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                                            <td>$<?php echo htmlspecialchars(number_format($invoice['amount'] ?? 0, 2)); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($invoice['status'] ?? 'pending'); ?>">
                                                    <?php echo htmlspecialchars($invoice['status'] ?? 'Pending'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['created_at']))); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modals -->
    <!-- Truck Registration Modal -->
    <div class="modal" id="truck-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Register New Truck</h3>
                <button class="modal-close" onclick="closeModal('truck-modal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="register_truck" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Plate Number</label>
                        <input type="text" name="plate_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Truck Type</label>
                        <select name="truck_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Flatbed">Flatbed</option>
                            <option value="Refrigerated">Refrigerated</option>
                            <option value="Tanker">Tanker</option>
                            <option value="Box Truck">Box Truck</option>
                            <option value="Dump Truck">Dump Truck</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Capacity (tons)</label>
                        <input type="number" name="capacity" step="0.1" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assign Driver (Optional)</label>
                        <select name="driver_id" class="form-control">
                            <option value="">No driver assigned</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('truck-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Truck</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Driver Registration Modal -->
    <div class="modal" id="driver-registration-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Register New Driver</h3>
                <button class="modal-close" onclick="closeModal('driver-registration-modal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="register_driver" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">License Number</label>
                        <input type="text" name="license_number" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Experience (years)</label>
                        <input type="number" name="experience_years" min="0" class="form-control" required>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('driver-registration-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Driver</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Driver Assignment Modal -->
    <div class="modal" id="driver-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Driver to Truck</h3>
                <button class="modal-close" onclick="closeModal('driver-modal')">&times;</button>
            </div>
            <form method="POST" id="driver-assignment-form">
                <input type="hidden" name="assign_driver" value="1">
                <input type="hidden" name="truck_id" id="assign-truck-id" value="">

                <div class="form-group">
                    <label class="form-label">Select Driver</label>
                    <select name="driver_id" class="form-control" required>
                        <option value="">Choose a driver</option>
                        <?php foreach ($available_drivers as $driver): ?>
                            <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?> (<?php echo htmlspecialchars($driver['license_number']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('driver-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Driver</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal" id="status-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Delivery Status</h3>
                <button class="modal-close" onclick="closeModal('status-modal')">&times;</button>
            </div>
            <form method="POST" id="status-update-form">
                <input type="hidden" name="update_delivery_status" value="1">
                <input type="hidden" name="service_request_id" id="status-request-id" value="">

                <div class="form-group">
                    <label class="form-label">Delivery Status</label>
                    <select name="delivery_status" class="form-control" required>
                        <option value="accepted">Accepted</option>
                        <option value="in transit">In Transit</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('status-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('show');
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('show');
        });

        // Close sidebar when clicking on a link (for mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('show');
            });
        });

        // Modal functions
        function showTruckModal() {
            document.getElementById('truck-modal').classList.add('show');
        }

        function showDriverModal() {
            document.getElementById('driver-registration-modal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function assignDriver(truckId) {
            document.getElementById('assign-truck-id').value = truckId;
            document.getElementById('driver-modal').classList.add('show');
        }

        function updateStatus(requestId) {
            document.getElementById('status-request-id').value = requestId;
            document.getElementById('status-modal').classList.add('show');
        }

        function acceptRequest(requestId) {
            if (confirm('Are you sure you want to accept this service request? You will need to assign a truck and driver.')) {
                // Create a form to submit the acceptance
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accept_service_request" value="1">
                    <input type="hidden" name="service_request_id" value="${requestId}">
                    <input type="hidden" name="truck_id" value="">
                    <input type="hidden" name="driver_id" value="">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function unassignDriver(driverId) {
            if (confirm('Are you sure you want to unassign this driver from their truck?')) {
                // Create a form to submit the unassignment
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="unassign_driver" value="1">
                    <input type="hidden" name="driver_id" value="${driverId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editDriver(driverId) {
            // For now, just show an alert. Could be expanded to open an edit modal
            alert('Edit driver functionality coming soon. Driver ID: ' + driverId);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Global search functionality
        document.getElementById('global-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            // Implement search functionality based on current tab
            console.log('Searching for:', searchTerm);
        });

        // Auto-refresh notifications count (optional)
        setInterval(function() {
            // Could implement AJAX to refresh notification counts
        }, 30000); // Every 30 seconds
    </script>
</body>
</html>