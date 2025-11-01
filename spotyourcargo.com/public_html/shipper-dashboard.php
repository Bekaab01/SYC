<?php
include_once __DIR__ . '/../private/session_config.php';
session_start();
error_log("Shipper Dashboard accessed - User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", User Type: " . ($_SESSION['user_type'] ?? 'Not set'));

include_once __DIR__ . '/../private/db.php';

// Function to generate SYC-CG ID for cargo
function generateCargoId($pdo) {
    // Get the last cargo ID
    $stmt = $pdo->query("SELECT cargo_id FROM cargo ORDER BY id DESC LIMIT 1");
    $last_cargo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_cargo && preg_match('/SYC-CG-(\d+)/', $last_cargo['cargo_id'], $matches)) {
        $last_number = (int)$matches[1];
        $new_number = $last_number + 1;
    } else {
        // If no SYC-CG IDs exist yet, check if we have numeric IDs to convert
        $stmt = $pdo->query("SELECT cargo_id FROM cargo WHERE cargo_id REGEXP '^[0-9]+$' ORDER BY id DESC LIMIT 1");
        $numeric_id = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($numeric_id && is_numeric($numeric_id['cargo_id'])) {
            $new_number = (int)$numeric_id['cargo_id'] + 1;
        } else {
            $new_number = 1;
        }
    }
    
    return 'SYC-CG-' . str_pad($new_number, 6, '0', STR_PAD_LEFT);
}

// Function to generate SYC-TR ID for trucks
function generateTruckId($pdo) {
    // Check if trucks table has truck_id column
    $check_column = $pdo->query("SHOW COLUMNS FROM trucks LIKE 'truck_id'");
    
    if ($check_column->rowCount() > 0) {
        $stmt = $pdo->query("SELECT truck_id FROM trucks ORDER BY id DESC LIMIT 1");
        $last_truck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_truck && preg_match('/SYC-TR-(\d+)/', $last_truck['truck_id'], $matches)) {
            $last_number = (int)$matches[1];
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
    } else {
        // If no truck_id column, just use sequential numbering
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM trucks");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_number = $result['max_id'] ? $result['max_id'] + 1 : 1;
    }
    
    return 'SYC-TR-' . str_pad($new_number, 6, '0', STR_PAD_LEFT);
}

// Session validation - check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: access.php');
    exit();
}

// Access control - verify user is a shipper
if ($_SESSION['user_type'] !== 'shipper') {
    header('Location: access.php');
    exit();
}

// Get shipper ID from session
$shipper_id = $_SESSION['user_id'];

// Initialize default values
$user_name = "User";
$user_initials = "U";
$company_name = "Shipping Company";
$welcome_name = "User";
$shipper_data = [];
$total_shipments = 0;
$delivered_shipments = 0;
$in_transit_shipments = 0;
$pending_shipments = 0;
$recent_shipments = [];
$notifications = [];
$messages = [];
$unread_notifications_count = 0;
$unread_messages_count = 0;

// Fetch user's syc_id
$user_syc_id = null;
try {
    $user_stmt = $pdo->prepare("SELECT syc_id FROM users WHERE id = ?");
    $user_stmt->execute([$shipper_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_syc_id = $user_data['syc_id'] ?? null;

    // Generate and set syc_id if missing
    if (!$user_syc_id) {
        $user_syc_id = 'SHP' . str_pad($shipper_id, 6, '0', STR_PAD_LEFT);
        $update_stmt = $pdo->prepare("UPDATE users SET syc_id = ? WHERE id = ?");
        $update_stmt->execute([$user_syc_id, $shipper_id]);
    }
} catch (PDOException $e) {
    error_log("User syc_id fetch error: " . $e->getMessage());
    $shipper_error = "Error fetching user data: " . $e->getMessage();
}

// Fetch shipper profile data
try {
    $shipper_stmt = $pdo->prepare("
        SELECT
            u.*,
            s.id as shipper_id,
            s.syc_id as syc_shipper_id,
            s.company_name,
            s.company_contact_name,
            s.email as shipper_email,
            s.created_at as shipper_created_at,
            u.first_name,
            u.last_name,
            u.full_name
        FROM users u
        LEFT JOIN shippers s ON u.syc_id = s.syc_id
        WHERE u.id = ?
    ");
    $shipper_stmt->execute([$shipper_id]);
    $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);

    if ($shipper_data) {
        // Prioritize shipper first_name and last_name if available
        $first_name = $shipper_data['first_name'] ?? null;
        $last_name = $shipper_data['last_name'] ?? null;
        $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($shipper_data['name'] ?? $shipper_data['username'] ?? 'User');
        $user_name = $display_name; // For sidebar and other uses

        // Extract first name for welcome message
        if ($first_name) {
            $welcome_name = ucfirst(strtolower(trim($first_name)));
        } else {
            $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
            // If welcome_name contains @ (email), extract username part
            if (strpos($welcome_name, '@') !== false) {
                $welcome_name = explode('@', $welcome_name)[0];
            }
            // Capitalize first letter
            $welcome_name = ucfirst(strtolower($welcome_name));
        }

        $user_initials = strtoupper(substr($display_name, 0, 2));
        $company_name = $shipper_data['company_name'] ?? 'Shipping Company';
        $shipper_id_db = $shipper_data['shipper_id'] ?? null;
        $syc_shipper_id = $shipper_data['syc_shipper_id'] ?? $user_syc_id;

        // If shipper profile doesn't exist but user is shipper type, create one
        if (!$shipper_id_db && $_SESSION['user_type'] === 'shipper') {
            // Extract first_name from username or email
            $username = $shipper_data['username'] ?? '';
            $email = $shipper_data['email'] ?? '';
            $extracted_first = '';
            if (strpos($username, '@') !== false) {
                $extracted_first = explode('@', $username)[0];
            } elseif (!empty($username)) {
                $extracted_first = $username;
            } elseif (!empty($email)) {
                $extracted_first = explode('@', $email)[0];
            }
            $first_name_to_set = ucfirst(strtolower(trim($extracted_first)));

            $create_shipper = $pdo->prepare("
                INSERT INTO shippers (syc_id, company_name, email, company_contact_name, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $create_shipper->execute([
                $user_syc_id,
                $company_name,
                $shipper_data['email'],
                $display_name,
                $first_name_to_set
            ]);
            $shipper_id_db = $pdo->lastInsertId();

            // Refresh shipper data
            $shipper_stmt->execute([$shipper_id]);
            $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);
            $syc_shipper_id = $shipper_data['syc_shipper_id'] ?? $user_syc_id;

            // Recompute names after refresh
            $first_name = $shipper_data['first_name'] ?? null;
            $last_name = $shipper_data['last_name'] ?? null;
            $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($shipper_data['name'] ?? $shipper_data['username'] ?? 'User');
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
        $syc_shipper_id = $user_syc_id;
        $display_name = 'User';
        $user_name = $display_name;
        $welcome_name = $display_name;
        $user_initials = 'U';
    }
} catch (PDOException $e) {
    error_log("Shipper data fetch error: " . $e->getMessage());
    $shipper_error = "Error loading shipper profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = $_POST['phone'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    
    try {
        $update_stmt = $pdo->prepare("
            UPDATE users
            SET phone = ?, company_name = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([$phone, $company_name, $shipper_id]);
        
        if ($update_stmt->rowCount() > 0) {
            // Refresh shipper data
            $shipper_stmt->execute([$shipper_id]);
            $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);
            $success_message = "Profile updated successfully!";
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = "Error updating profile. Please try again.";
    }
}

// Handle new cargo submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cargo'])) {
    $description = $_POST['description'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $pickup_location = $_POST['pickup_location'] ?? '';
    $dropoff_location = $_POST['dropoff_location'] ?? '';
    $dimensions = $_POST['dimensions'] ?? '';
    $cargo_type = $_POST['cargo_type'] ?? 'General';

    try {
        // Generate SYC-CG ID
        $cargo_id = generateCargoId($pdo);

        $insert_stmt = $pdo->prepare("
            INSERT INTO cargo (cargo_id, shipper_user_id, description, weight, pickup_location, dropoff_location, dimensions, cargo_type, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $insert_stmt->execute([$cargo_id, $shipper_id, $description, $weight, $pickup_location, $dropoff_location, $dimensions, $cargo_type]);

        if ($insert_stmt->rowCount() > 0) {
            $cargo_success_message = "Cargo added successfully! Cargo ID: " . $cargo_id;
        }
    } catch (PDOException $e) {
        error_log("Cargo insertion error: " . $e->getMessage());
        $cargo_error_message = "Error adding cargo. Please try again.";
    }
}

// Handle cargo update
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
            UPDATE cargo SET description = ?, weight = ?, pickup_location = ?, dropoff_location = ?, dimensions = ?, cargo_type = ?, updated_at = NOW()
            WHERE id = ? AND shipper_user_id = ?
        ");
        $update_stmt->execute([$description, $weight, $pickup_location, $dropoff_location, $dimensions, $cargo_type, $cargo_id, $shipper_id]);

        if ($update_stmt->rowCount() > 0) {
            $update_success_message = "Cargo updated successfully!";
        } else {
            $update_error_message = "No changes made or cargo not found.";
        }
    } catch (PDOException $e) {
        error_log("Cargo update error: " . $e->getMessage());
        $update_error_message = "Error updating cargo. Please try again.";
    }
}

// Handle match action (contact carrier for specific cargo-truck match)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_for_match'])) {
    $cargo_id = $_POST['cargo_id'] ?? '';
    $truck_id = $_POST['truck_id'] ?? '';
    $carrier_id = $_POST['carrier_id'] ?? '';
    $message = $_POST['message'] ?? '';
    
    try {
        // Create a shipment/match record
        $match_stmt = $pdo->prepare("
            INSERT INTO matches (cargo_id, truck_id, carrier_id, shipper_id, status, message, created_at) 
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $match_stmt->execute([$cargo_id, $truck_id, $carrier_id, $shipper_id, $message]);
        
        // Update cargo status to 'matched' or keep as 'pending'?
        $update_cargo = $pdo->prepare("UPDATE cargo SET status = 'matched' WHERE id = ?");
        $update_cargo->execute([$cargo_id]);
        
        $match_success_message = "Match request sent to carrier successfully!";
        
    } catch (PDOException $e) {
        error_log("Match creation error: " . $e->getMessage());
        $match_error_message = "Error creating match. Please try again.";
    }
}

// Get current tab from URL or default to dashboard
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Fetch cargo data based on current tab
try {
    // Check if cargo table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'cargo'");
    if ($table_check->rowCount() > 0) {
        // FIXED: Use fixed column names instead of dynamic detection
        $shipper_id_column = 'shipper_user_id';
        $status_column = 'status';
        
        // Get counts for dashboard
        $total_shipments_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cargo WHERE $shipper_id_column = ?");
        $total_shipments_stmt->execute([$shipper_id]);
        $total_shipments = $total_shipments_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $delivered_shipments_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cargo WHERE $shipper_id_column = ? AND $status_column LIKE '%delivered%'");
        $delivered_shipments_stmt->execute([$shipper_id]);
        $delivered_shipments = $delivered_shipments_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $in_transit_shipments_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cargo WHERE $shipper_id_column = ? AND ($status_column LIKE '%transit%' OR $status_column LIKE '%moving%')");
        $in_transit_shipments_stmt->execute([$shipper_id]);
        $in_transit_shipments = $in_transit_shipments_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $pending_shipments_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cargo WHERE $shipper_id_column = ? AND ($status_column LIKE '%pending%' OR $status_column LIKE '%waiting%')");
        $pending_shipments_stmt->execute([$shipper_id]);
        $pending_shipments = $pending_shipments_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get data for current tab
        switch ($current_tab) {
            case 'dashboard':
                $shipments_stmt = $pdo->prepare("SELECT * FROM cargo WHERE $shipper_id_column = ? ORDER BY created_at DESC LIMIT 5");
                $shipments_stmt->execute([$shipper_id]);
                $recent_shipments = $shipments_stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'shipments':
                $shipments_stmt = $pdo->prepare("SELECT * FROM cargo WHERE $shipper_id_column = ? ORDER BY created_at DESC");
                $shipments_stmt->execute([$shipper_id]);
                $shipments_data = $shipments_stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'my-cargo':
                $cargo_stmt = $pdo->prepare("SELECT * FROM cargo WHERE $shipper_id_column = ? ORDER BY created_at DESC");
                $cargo_stmt->execute([$shipper_id]);
                $cargo_data = $cargo_stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'active-shipments':
                $active_stmt = $pdo->prepare("SELECT * FROM cargo WHERE $shipper_id_column = ? AND ($status_column LIKE '%transit%' OR $status_column LIKE '%moving%') ORDER BY created_at DESC");
                $active_stmt->execute([$shipper_id]);
                $active_shipments = $active_stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
    }
} catch (PDOException $e) {
    error_log("Cargo data error: " . $e->getMessage());
}

// Fetch available trucks data
$available_trucks = [];
$trucks_filters = [
    'location' => $_GET['location'] ?? '',
    'capacity_min' => $_GET['capacity_min'] ?? '',
    'capacity_max' => $_GET['capacity_max'] ?? '',
    'truck_model' => $_GET['truck_model'] ?? ''
];

try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'trucks'");
    if ($table_check->rowCount() > 0) {
        // Build query with filters - FIXED: Use 'active' instead of 'available'
        $trucks_query = "
            SELECT t.*, 
                   c.company_name as carrier_company,
                   c.email as carrier_email,
                   c.phone as carrier_phone,
                   u.full_name as carrier_contact
            FROM trucks t
            LEFT JOIN carriers c ON t.carrier_id = c.id
            LEFT JOIN users u ON c.syc_id = u.syc_id
            WHERE t.status = 'active'  -- CHANGED: 'active' instead of 'available'
        ";
        
        $query_params = [];
        
        // Add location filter
        if (!empty($trucks_filters['location'])) {
            $trucks_query .= " AND (t.current_location LIKE ?)";
            $location_param = '%' . $trucks_filters['location'] . '%';
            $query_params[] = $location_param;
        }
        
        // Add capacity filters
        if (!empty($trucks_filters['capacity_min'])) {
            $trucks_query .= " AND t.capacity >= ?";
            $query_params[] = $trucks_filters['capacity_min'];
        }
        
        if (!empty($trucks_filters['capacity_max'])) {
            $trucks_query .= " AND t.capacity <= ?";
            $query_params[] = $trucks_filters['capacity_max'];
        }
        
        // Add truck model filter
        if (!empty($trucks_filters['truck_model'])) {
            $trucks_query .= " AND t.truck_model = ?";
            $query_params[] = $trucks_filters['truck_model'];
        }
        
        $trucks_query .= " ORDER BY t.created_at DESC";
        
        $trucks_stmt = $pdo->prepare($trucks_query);
        $trucks_stmt->execute($query_params);
        $available_trucks = $trucks_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    }
} catch (PDOException $e) {
    error_log("Available trucks fetch error: " . $e->getMessage());
}

// DEBUG: Check what's happening with the query
error_log("DEBUG: Available trucks query executed");
error_log("DEBUG: Number of trucks found: " . count($available_trucks));
error_log("DEBUG: Filters applied: " . print_r($trucks_filters, true));
error_log("DEBUG: SQL Query: " . $trucks_query);
error_log("DEBUG: Query params: " . print_r($query_params, true));


// Get unique truck models for filter dropdown (since no truck_type column)
$truck_models = [];
try {
    $models_stmt = $pdo->query("SELECT DISTINCT truck_model FROM trucks WHERE truck_model IS NOT NULL AND truck_model != ''");
    $truck_models = $models_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Truck models fetch error: " . $e->getMessage());
}

// Fetch cargo for matching and calculate matches
$user_cargo = [];
$matched_trucks = [];

try {
    // Get user's cargo that needs matching (pending status)
    $cargo_stmt = $pdo->prepare("SELECT * FROM cargo WHERE shipper_user_id = ? AND status = 'Pending' ORDER BY created_at DESC");
    $cargo_stmt->execute([$shipper_id]);
    $user_cargo = $cargo_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate matches for each cargo
    foreach ($user_cargo as $cargo) {
        $matched_trucks[$cargo['id']] = calculateTruckMatches($cargo, $pdo);
    }
    
} catch (PDOException $e) {
    error_log("Matching system error: " . $e->getMessage());
}

// Matching Algorithm Function
function calculateTruckMatches($cargo, $pdo) {
    $matches = [];
    
    try {
        // Get all active trucks
        $trucks_stmt = $pdo->prepare("
            SELECT t.*, 
                   c.company_name as carrier_company,
                   c.email as carrier_email,
                   c.phone as carrier_phone,
                   u.full_name as carrier_contact
            FROM trucks t
            LEFT JOIN carriers c ON t.carrier_id = c.id
            LEFT JOIN users u ON c.syc_id = u.syc_id
            WHERE t.status = 'active'
        ");
        $trucks_stmt->execute();
        $all_trucks = $trucks_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_trucks as $truck) {
            $match_score = calculateMatchScore($cargo, $truck);
            
            // Only include trucks with reasonable match score (50%+)
            if ($match_score >= 50) {
                $matches[] = [
                    'truck' => $truck,
                    'match_score' => $match_score,
                    'match_reasons' => getMatchReasons($cargo, $truck, $match_score)
                ];
            }
        }
        
        // Sort by match score (highest first)
        usort($matches, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });
        
    } catch (PDOException $e) {
        error_log("Match calculation error: " . $e->getMessage());
    }
    
    return $matches;
}

// Calculate match score between cargo and truck (0-100)
function calculateMatchScore($cargo, $truck) {
    $score = 0;
    $max_score = 100;
    
    // Capacity match (40% of total score)
    $cargo_weight = floatval($cargo['weight'] ?? 0);
    $truck_capacity = floatval($truck['capacity'] ?? 0);
    
    if ($truck_capacity > 0) {
        $capacity_ratio = $cargo_weight / $truck_capacity;
        if ($capacity_ratio <= 1.0) {
            // Perfect match or truck has extra capacity
            $capacity_score = 40 * (1 - abs($capacity_ratio - 0.8) / 0.8); // Best at 80% capacity utilization
        } else {
            // Truck too small
            $capacity_score = 40 * (1 / $capacity_ratio);
        }
        $score += max(0, min(40, $capacity_score));
    }
    
    // Location match (30% of total score)
    $cargo_pickup = strtolower($cargo['pickup_location'] ?? '');
    $truck_location = strtolower($truck['current_location'] ?? '');
    
    if ($cargo_pickup && $truck_location) {
        if (strpos($truck_location, $cargo_pickup) !== false || 
            strpos($cargo_pickup, $truck_location) !== false ||
            levenshtein($cargo_pickup, $truck_location) <= 3) {
            $score += 30; // Same location
        } elseif (strpos($truck['operating_route'] ?? '', $cargo_pickup) !== false) {
            $score += 20; // On operating route
        } else {
            $score += 10; // Different location
        }
    }
    
    // Cargo type compatibility (20% of total score)
    $cargo_type = strtolower($cargo['cargo_type'] ?? 'general');
    $score += 20; // Base score - all trucks can handle general cargo
    
    // Special requirements (10% of total score)
    // Add special feature checks here if needed
    $score += 10;
    
    return min(100, max(0, round($score)));
}

// Get reasons for match score
function getMatchReasons($cargo, $truck, $score) {
    $reasons = [];
    
    // Capacity reason
    $cargo_weight = floatval($cargo['weight'] ?? 0);
    $truck_capacity = floatval($truck['capacity'] ?? 0);
    
    if ($truck_capacity > 0) {
        $utilization = ($cargo_weight / $truck_capacity) * 100;
        if ($utilization <= 80) {
            $reasons[] = "Perfect capacity fit (" . round($utilization) . "% utilization)";
        } elseif ($utilization <= 100) {
            $reasons[] = "Good capacity match (" . round($utilization) . "% utilization)";
        } else {
            $reasons[] = "Adequate capacity (" . round($utilization) . "% utilization)";
        }
    }
    
    // Location reason
    $cargo_pickup = $cargo['pickup_location'] ?? '';
    $truck_location = $truck['current_location'] ?? '';
    
    if ($cargo_pickup && $truck_location) {
        if (strpos(strtolower($truck_location), strtolower($cargo_pickup)) !== false) {
            $reasons[] = "Same location: " . $truck_location;
        } elseif (strpos(strtolower($truck['operating_route'] ?? ''), strtolower($cargo_pickup)) !== false) {
            $reasons[] = "On operating route through " . $cargo_pickup;
        } else {
            $reasons[] = "Available in " . $truck_location;
        }
    }
    
    // High score reasons
    if ($score >= 80) {
        $reasons[] = "Excellent overall match";
    } elseif ($score >= 60) {
        $reasons[] = "Good overall compatibility";
    }
    
    return $reasons;
}

// Fetch match history
$match_history = [];
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'matches'");
    if ($table_check->rowCount() > 0) {
        $history_stmt = $pdo->prepare("
            SELECT m.*, c.cargo_id, c.description as cargo_description, t.truck_model, car.company_name as carrier_company
            FROM matches m
            LEFT JOIN cargo c ON m.cargo_id = c.id
            LEFT JOIN trucks t ON m.truck_id = t.id
            LEFT JOIN carriers car ON m.carrier_id = car.id
            WHERE m.shipper_id = ?
            ORDER BY m.created_at DESC
            LIMIT 10
        ");
        $history_stmt->execute([$shipper_id]);
        $match_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Match history error: " . $e->getMessage());
}

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
            LEFT JOIN users u ON m.sender_id = u.user_id 
            WHERE m.receiver_id = ? 
            ORDER BY m.created_at DESC 
            LIMIT 5
        ");
        $messages_stmt->execute([$shipper_id]);
        $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $unread_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
        $unread_stmt->execute([$shipper_id]);
        $unread_messages_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
} catch (PDOException $e) {
    error_log("Messages error: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: access.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Shipper Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --header-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana;
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
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
            overflow: hidden;
        }

        .sidebar-top {
            padding: 0 25px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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

.sidebar-middle {
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

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .data-table th {
            text-align: left;
            padding: 15px 20px;
            background: var(--light-gray);
            color: var(--primary-blue);
            font-weight: 600;
            border-bottom: 1px solid #ddd;
        }

        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #FFF3CD;
            color: #856404;
        }

        .status-badge.in-transit {
            background: #CCE5FF;
            color: #004085;
        }

        .status-badge.delivered {
            background: #D4EDDA;
            color: #155724;
        }

        .status-badge.unknown {
            background: #E9ECEF;
            color: #495057;
        }

        .action-btn {
            color: var(--secondary-blue);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: var(--transition);
        }

        .action-btn:hover {
            background: rgba(30, 77, 143, 0.1);
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-gray);
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
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(30, 77, 143, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-blue);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark-gray);
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        /* Profile Section */
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .profile-header h2 {
            color: var(--primary-blue);
            margin: 0;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .info-value {
            color: var(--dark-gray);
            font-size: 16px;
        }

        /* Empty State */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-light);
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--primary-blue);
        }

        .view-all {
            color: var(--secondary-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
                overflow-y: auto;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .top-header {
                padding: 0 15px;
            }

            .search-bar {
                width: 200px;
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

            .profile-info {
                grid-template-columns: 1fr;
            }
        }

        .menu-toggle {
            display: block;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary-blue);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .menu-toggle:hover {
            background: var(--light-gray);
        }

        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }

        /* Sidebar backdrop for mobile */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 98;
        }

        @media (max-width: 768px) {
            .sidebar-backdrop.active {
                display: block;
            }
        }

            /* Available Trucks Styles */
        .truck-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

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

        .capacity-filter {
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

        .trucks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        @media (max-width: 768px) {
            .trucks-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .capacity-filter {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
        }

        /* Smart Matching Styles */
        .match-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-blue);
            transition: var(--transition);
        }

        .match-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .match-score {
            font-weight: bold;
            padding: 8px 15px;
            border-radius: 20px;
            color: white;
            text-align: center;
            min-width: 60px;
        }

        .cargo-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e8e8e8;
        }

        .matches-grid {
            display: grid;
            gap: 15px;
            margin-top: 15px;
        }

        .match-reason-tag {
            display: inline-block;
            background: rgba(0, 51, 102, 0.1);
            color: var(--primary-blue);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 8px;
            margin-bottom: 5px;
        }

    </style>
</head>
<body>
    <?php include_once __DIR__ . '/assets/php/sidebar-shipper.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search shipments, cargo, or invoices...">
            </div>
            
            <div class="header-actions">
                <div class="notification-btn" id="notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </div>

                <a href="contact.php?from=shipper-dashboard" id="messages-btn" class="support-icon" title="Need Help? Contact Support">
                    <i class="fa-solid fa-headset"></i>
                </a>



                <!-- Notifications Dropdown -->
                <div class="notification-dropdown" id="notification-dropdown">
                    <div class="dropdown-header">
                        <h4>Notifications</h4>
                        <div style="display: flex; gap: 10px;">
                            <a href="#" id="mark-all-read-btn" style="color: var(--secondary-blue); text-decoration: none; font-size: 12px;">Mark All Read</a>
                            <a href="notifications.php">View All</a>
                        </div>
                    </div>
                    <div class="dropdown-content">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="dropdown-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>" data-notification-id="<?php echo $notification['id']; ?>">
                                    <div class="dropdown-text">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dropdown-item">
                                <div class="dropdown-text">
                                    <p>No notifications</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="notifications.php">See all notifications</a>
                    </div>
                </div>
                
                <!-- Support: placeholder to satisfy existing scripts -->
                <div class="messages-dropdown" id="messages-dropdown" style="display:none"></div>
            </div>
        </header>
        
        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</h2>
                    <p>SYC Shipper ID: <strong><?php echo htmlspecialchars($syc_shipper_id ?? 'Not assigned'); ?></strong></p>
                    <p>You have <?php echo $total_shipments; ?> total shipment(s). <?php echo $delivered_shipments; ?> delivered, <?php echo $in_transit_shipments; ?> in transit.</p>
                </div>
                <button class="banner-cta" id="add-cargo-btn">+ Add New Cargo</button>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard-tab" class="tab-content <?php echo $current_tab == 'dashboard' ? 'active' : ''; ?>">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_shipments; ?></h3>
                            <p>Total Shipments</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $delivered_shipments; ?></h3>
                            <p>Delivered</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-truck-moving"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $in_transit_shipments; ?></h3>
                            <p>In Transit</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $pending_shipments; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Shipments -->
                <div class="section-header">
                    <h2>Recent Shipments</h2>
                    <a href="?tab=shipments" class="view-all">View All</a>
                </div>
                
                <?php if (!empty($recent_shipments)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cargo ID</th>
                                <th>Description</th>
                                <th>Weight</th>
                                <th>Pickup Location</th>
                                <th>Dropoff Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_shipments as $shipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($shipment['cargo_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['weight'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['pickup_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['dropoff_location'] ?? 'N/A'); ?></td>
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
                                    <td>
                                        <button class="action-btn">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shipping-fast"></i>
                        <h3>No Shipments Yet</h3>
                        <p>Get started by adding your first cargo shipment.</p>
                        <button class="btn btn-primary" id="add-cargo-btn-2" style="margin-top: 15px;">+ Add New Cargo</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Shipments Tab -->
            <div id="shipments-tab" class="tab-content <?php echo $current_tab == 'shipments' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>All Shipments</h2>
                </div>
                
                <?php if (!empty($shipments_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cargo ID</th>
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
                                    <td><?php echo htmlspecialchars($shipment['cargo_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['weight'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['pickup_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['dropoff_location'] ?? 'N/A'); ?></td>
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
                                    data-weight="<?php echo htmlspecialchars($shipment['weight'] ?? ''); ?>"
                                    data-dimensions="<?php echo htmlspecialchars($shipment['dimensions'] ?? ''); ?>"
                                    data-pickup-location="<?php echo htmlspecialchars($shipment['pickup_location'] ?? ''); ?>"
                                    data-dropoff-location="<?php echo htmlspecialchars($shipment['dropoff_location'] ?? ''); ?>"
                                    data-cargo-type="<?php echo htmlspecialchars($shipment['cargo_type'] ?? 'General'); ?>">
                                View
                            </button>
                            <button class="action-btn edit-cargo-btn"
                                    data-id="<?php echo $shipment['id']; ?>"
                                    data-description="<?php echo htmlspecialchars($shipment['description'] ?? ''); ?>"
                                    data-weight="<?php echo htmlspecialchars($shipment['weight'] ?? ''); ?>"
                                    data-dimensions="<?php echo htmlspecialchars($shipment['dimensions'] ?? ''); ?>"
                                    data-pickup-location="<?php echo htmlspecialchars($shipment['pickup_location'] ?? ''); ?>"
                                    data-dropoff-location="<?php echo htmlspecialchars($shipment['dropoff_location'] ?? ''); ?>"
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
                        <button class="btn btn-primary" id="add-cargo-btn-3" style="margin-top: 15px;">+ Add New Cargo</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- My Cargo Tab -->
            <div id="my-cargo-tab" class="tab-content <?php echo $current_tab == 'my-cargo' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>My Cargo</h2>
                    <button class="btn btn-primary" id="add-cargo-btn-4">+ Add New Cargo</button>
                </div>
                
                <?php if (!empty($cargo_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cargo ID</th>
                                <th>Description</th>
                                <th>Weight</th>
                                <th>Dimensions</th>
                                <th>Pickup Location</th>
                                <th>Dropoff Location</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cargo_data as $cargo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cargo['cargo_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cargo['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cargo['weight'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cargo['dimensions'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cargo['pickup_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cargo['dropoff_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cargo['cargo_type'] ?? 'General'); ?></td>
                                    <td>
                                        <?php
                                        $status = $cargo['status'] ?? $cargo['cargo_status'] ?? 'unknown';
                                        $status_class = 'unknown';
                                        if (stripos($status, 'pending') !== false) $status_class = 'pending';
                                        elseif (stripos($status, 'transit') !== false || stripos($status, 'moving') !== false) $status_class = 'in-transit';
                                        elseif (stripos($status, 'delivered') !== false) $status_class = 'delivered';
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($cargo['created_at']))); ?></td>
                                    <td>
                                        <button class="action-btn">View</button>
                                        <button class="action-btn">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-truck-loading"></i>
                        <h3>No Cargo Added</h3>
                        <p>Start by adding your first cargo shipment.</p>
                        <button class="btn btn-primary" id="add-cargo-btn-5" style="margin-top: 15px;">+ Add New Cargo</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Active Shipments Tab -->
            <div id="active-shipments-tab" class="tab-content <?php echo $current_tab == 'active-shipments' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Active Shipments</h2>
                </div>
                
                <?php if (!empty($active_shipments)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cargo ID</th>
                                <th>Description</th>
                                <th>Weight</th>
                                <th>Pickup Location</th>
                                <th>Dropoff Location</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_shipments as $shipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($shipment['cargo_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['weight'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['pickup_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['dropoff_location'] ?? 'N/A'); ?></td>
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
                                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($shipment['updated_at'] ?? $shipment['created_at']))); ?></td>
                                    <td>
                                        <button class="action-btn">Track</button>
                                        <button class="action-btn">Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-ship"></i>
                        <h3>No Active Shipments</h3>
                        <p>You don't have any shipments in transit at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Trucks Tab -->
            <div id="available-trucks-tab" class="tab-content <?php echo $current_tab == 'available-trucks' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Available Trucks</h2>
                    <p>Browse and connect with carriers offering truck transportation services</p>
                </div>

                <!-- Filters Card -->
                <div class="filters-card">
                    <h3 style="margin-bottom: 20px; color: var(--primary-blue);">Filter Available Trucks</h3>

                    <form method="GET" action="">
                        <input type="hidden" name="tab" value="available-trucks">

                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="location">Location</label>
                                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($trucks_filters['location']); ?>" placeholder="e.g., Addis Ababa">
                            </div>

                            <div class="form-group">
                                <label for="truck_model">Truck Model</label>
                                <select id="truck_model" name="truck_model">
                                    <option value="">All Models</option>
                                    <?php foreach ($truck_models as $model): ?>
                                        <option value="<?php echo htmlspecialchars($model); ?>" <?php echo $trucks_filters['truck_model'] == $model ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($model); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="capacity-filter">
                            <div class="form-group">
                                <label for="capacity_min">Min Capacity (kg)</label>
                                <input type="number" id="capacity_min" name="capacity_min" value="<?php echo htmlspecialchars($trucks_filters['capacity_min']); ?>" placeholder="0">
                            </div>

                            <span style="align-self: center; margin: 0 10px;">to</span>

                            <div class="form-group">
                                <label for="capacity_max">Max Capacity (kg)</label>
                                <input type="number" id="capacity_max" name="capacity_max" value="<?php echo htmlspecialchars($trucks_filters['capacity_max']); ?>" placeholder="No limit">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="?tab=available-trucks" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Trucks Display -->
                <?php if (!empty($available_trucks)): ?>
                    <div class="trucks-grid">
                        <?php foreach ($available_trucks as $truck): ?>
                            <div class="truck-card" style="background: white; border-radius: 15px; padding: 20px; box-shadow: var(--card-shadow); transition: var(--transition);">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 5px 0; color: var(--primary-blue);">
                                            <?php echo htmlspecialchars($truck['truck_model'] ?? 'Unknown Model'); ?>
                                        </h4>
                                        <p style="margin: 0; font-size: 14px; color: var(--text-light);">
                                            <?php echo htmlspecialchars($truck['carrier_company'] ?? 'Unknown Carrier'); ?> •
                                            Capacity: <?php echo htmlspecialchars($truck['capacity'] ?? '0'); ?>kg
                                        </p>
                                        <p style="margin: 5px 0 0 0; font-size: 13px; color: var(--text-light);">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($truck['current_location'] ?? 'Unknown Location'); ?>
                                        </p>
                                        <?php if (!empty($truck['operating_route'])): ?>
                                            <p style="margin: 5px 0 0 0; font-size: 13px; color: var(--text-light);">
                                                <i class="fas fa-route"></i>
                                                <?php echo htmlspecialchars($truck['operating_route']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="status-badge delivered">Available</span>
                                    </div>
                                </div>

                                <!-- Truck Details -->
                                <div style="margin-bottom: 15px;">
                                    <?php if (!empty($truck['license_plate'])): ?>
                                        <span style="display: inline-block; background: rgba(0, 51, 102, 0.1); color: var(--primary-blue); padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-right: 8px;">
                                            <?php echo htmlspecialchars($truck['license_plate']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($truck['truck_type'])): ?>
                                        <span style="display: inline-block; background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                            <?php echo htmlspecialchars($truck['truck_type']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn btn-primary view-truck-details"
                                            style="flex: 1; padding: 10px 15px; font-size: 14px;"
                                            onclick="openTruckDetails(<?php echo htmlspecialchars(json_encode($truck)); ?>)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button class="btn btn-secondary contact-truck-carrier"
                                            style="flex: 1; padding: 10px 15px; font-size: 14px;"
                                            onclick="contactCarrier(<?php echo htmlspecialchars($truck['id']); ?>, '<?php echo htmlspecialchars($truck['carrier_company'] ?? 'Carrier'); ?>')">
                                        <i class="fas fa-envelope"></i> Contact
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-truck"></i>
                        <h3>No Available Trucks</h3>
                        <p>No trucks match your current filters. Try adjusting your search criteria.</p>
                        <a href="?tab=available-trucks" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Smart Matching Tab -->
            <div id="matching-tab" class="tab-content <?php echo $current_tab == 'matching' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Smart Matching</h2>
                    <p>Intelligent cargo-to-truck matching based on your requirements</p>
                </div>
                
                <?php if (isset($match_success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($match_success_message); ?></div>
                <?php endif; ?>
                
                <?php if (isset($match_error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($match_error_message); ?></div>
                <?php endif; ?>
                
                <!-- Cargo Selection -->
                <div class="filters-card">
                    <h3 style="margin-bottom: 20px; color: var(--primary-blue);">Select Cargo for Matching</h3>
                    
                    <?php if (!empty($user_cargo)): ?>
                        <div class="cargo-selection">
                            <?php foreach ($user_cargo as $cargo): ?>
                                <div class="cargo-item" style="padding: 15px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; background: white;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; color: var(--primary-blue);">
                                                <?php echo htmlspecialchars($cargo['cargo_id']); ?> - <?php echo htmlspecialchars($cargo['description']); ?>
                                            </h4>
                                            <p style="margin: 0; color: var(--text-light); font-size: 14px;">
                                                <?php echo htmlspecialchars($cargo['weight']); ?>kg • 
                                                <?php echo htmlspecialchars($cargo['pickup_location']); ?> → 
                                                <?php echo htmlspecialchars($cargo['dropoff_location']); ?>
                                            </p>
                                        </div>
                                        <span class="status-badge pending">Pending Match</span>
                                    </div>
                                    
                                    <!-- Matched Trucks for this Cargo -->
                                    <?php if (!empty($matched_trucks[$cargo['id']])): ?>
                                        <div style="margin-top: 15px;">
                                            <h5 style="margin-bottom: 10px; color: var(--secondary-blue);">
                                                Top Matches (<?php echo count($matched_trucks[$cargo['id']]); ?> found)
                                            </h5>
                                            <div class="matches-grid" style="display: grid; gap: 15px;">
                                                <?php foreach (array_slice($matched_trucks[$cargo['id']], 0, 3) as $match): ?>
                                                    <div class="match-card" style="padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa;">
                                                        <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 10px;">
                                                            <div style="flex: 1;">
                                                                <h6 style="margin: 0 0 5px 0; color: var(--primary-blue);">
                                                                    <?php echo htmlspecialchars($match['truck']['truck_model'] ?? 'Unknown Model'); ?>
                                                                </h6>
                                                                <p style="margin: 0; font-size: 14px; color: var(--text-light);">
                                                                    <?php echo htmlspecialchars($match['truck']['carrier_company'] ?? 'Unknown Carrier'); ?> • 
                                                                    Capacity: <?php echo htmlspecialchars($match['truck']['capacity'] ?? '0'); ?>kg
                                                                </p>
                                                                <p style="margin: 5px 0 0 0; font-size: 13px; color: var(--text-light);">
                                                                    Location: <?php echo htmlspecialchars($match['truck']['current_location'] ?? 'Unknown'); ?>
                                                                </p>
                                                            </div>
                                                            <div style="text-align: right;">
                                                                <div class="match-score" style="
                                                                    background: <?php echo $match['match_score'] >= 80 ? '#4CAF50' : ($match['match_score'] >= 60 ? '#FF9800' : '#f44336'); ?>;
                                                                    color: white; 
                                                                    padding: 5px 10px; 
                                                                    border-radius: 20px; 
                                                                    font-weight: bold;
                                                                    font-size: 14px;
                                                                ">
                                                                    <?php echo $match['match_score']; ?>%
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Match Reasons -->
                                                        <div style="margin-bottom: 10px;">
                                                            <?php foreach ($match['match_reasons'] as $reason): ?>
                                                                <span style="
                                                                    display: inline-block;
                                                                    background: rgba(0, 51, 102, 0.1);
                                                                    color: var(--primary-blue);
                                                                    padding: 2px 8px;
                                                                    border-radius: 12px;
                                                                    font-size: 12px;
                                                                    margin-right: 5px;
                                                                    margin-bottom: 5px;
                                                                ">✓ <?php echo htmlspecialchars($reason); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        
                                                        <!-- Action Buttons -->
                                                        <div style="display: flex; gap: 10px;">
                                                            <button class="btn btn-primary contact-match-btn" 
                                                                    style="padding: 8px 15px; font-size: 14px;"
                                                                    data-cargo-id="<?php echo $cargo['id']; ?>"
                                                                    data-cargo-description="<?php echo htmlspecialchars($cargo['description']); ?>"
                                                                    data-truck-id="<?php echo $match['truck']['id']; ?>"
                                                                    data-truck-model="<?php echo htmlspecialchars($match['truck']['truck_model'] ?? 'Unknown'); ?>"
                                                                    data-carrier-id="<?php echo $match['truck']['carrier_id'] ?? ''; ?>"
                                                                    data-carrier-company="<?php echo htmlspecialchars($match['truck']['carrier_company'] ?? 'Unknown Carrier'); ?>">
                                                                <i class="fas fa-handshake"></i> Contact for Match
                                                            </button>
                                                            <button class="btn btn-secondary view-truck-details" 
                                                                    style="padding: 8px 15px; font-size: 14px;"
                                                                    onclick="openTruckDetails(<?php echo htmlspecialchars(json_encode($match['truck'])); ?>)">
                                                                <i class="fas fa-info-circle"></i> Details
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <?php if (count($matched_trucks[$cargo['id']]) > 3): ?>
                                                <div style="text-align: center; margin-top: 10px;">
                                                    <button class="btn btn-secondary view-all-matches" 
                                                            style="padding: 8px 15px; font-size: 14px;"
                                                            data-cargo-id="<?php echo $cargo['id']; ?>">
                                                        View All <?php echo count($matched_trucks[$cargo['id']]); ?> Matches
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top: 15px; padding: 20px; text-align: center; background: #f8f9fa; border-radius: 8px;">
                                            <i class="fas fa-search" style="font-size: 24px; color: var(--text-light); margin-bottom: 10px;"></i>
                                            <p style="margin: 0; color: var(--text-light);">No suitable matches found for this cargo.</p>
                                            <p style="margin: 5px 0 0 0; font-size: 14px; color: var(--text-light);">
                                                Try adjusting your requirements or check back later for new available trucks.
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-truck-loading"></i>
                            <h3>No Cargo Available for Matching</h3>
                            <p>Add some cargo with 'Pending' status to find matching trucks.</p>
                            <button class="btn btn-primary" id="add-cargo-for-matching" style="margin-top: 15px;">+ Add New Cargo</button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Match History -->
                <?php if (!empty($match_history)): ?>
                    <div class="section-header" style="margin-top: 40px;">
                        <h2>Match History</h2>
                    </div>
                    
                    <div class="filters-card">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cargo</th>
                                    <th>Truck</th>
                                    <th>Carrier</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($match_history as $match): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($match['cargo_description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($match['truck_model'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($match['carrier_company'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge 
                                                <?php echo $match['status'] == 'accepted' ? 'delivered' : 
                                                       ($match['status'] == 'pending' ? 'pending' : 'unknown'); ?>">
                                                <?php echo htmlspecialchars(ucfirst($match['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime($match['created_at']))); ?></td>
                                        <td>
                                            <button class="action-btn">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            
            <!-- Settings Tab -->
            <div id="settings-tab" class="tab-content <?php echo $current_tab == 'settings' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Settings</h2>
                </div>
                <div class="card" style="padding: 40px; text-align: center;">
                    <i class="fas fa-cog" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">Settings Panel Coming Soon</h3>
                    <p style="color: #999;">Configure your account settings here once the feature is available.</p>
                </div>
            </div>

            
            <!-- Other Tabs Placeholder -->
            <div id="invoices-tab" class="tab-content <?php echo $current_tab == 'invoices' ? 'active' : ''; ?>">
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>Invoices Coming Soon</h3>
                    <p>This feature is under development and will be available soon.</p>
                </div>
            </div>
            
            <div id="analytics-tab" class="tab-content <?php echo $current_tab == 'analytics' ? 'active' : ''; ?>">
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>Analytics Coming Soon</h3>
                    <p>This feature is under development and will be available soon.</p>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Cargo Modal -->
    <div class="modal" id="add-cargo-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Cargo</h3>
                <button class="modal-close" id="close-cargo-modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (isset($cargo_success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($cargo_success_message); ?></div>
                <?php endif; ?>
                
                <?php if (isset($cargo_error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($cargo_error_message); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="description">Cargo Description</label>
                        <input type="text" id="description" name="description" required placeholder="e.g., Electronics, Furniture, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" required placeholder="e.g., 500">
                    </div>
                    
                    <div class="form-group">
                        <label for="dimensions">Dimensions (LxWxH)</label>
                        <input type="text" id="dimensions" name="dimensions" placeholder="e.g., 10x5x3">
                    </div>
                    
                    <div class="form-group">
                        <label for="cargo_type">Cargo Type</label>
                        <select id="cargo_type" name="cargo_type">
                            <option value="General">General</option>
                            <option value="Fragile">Fragile</option>
                            <option value="Perishable">Perishable</option>
                            <option value="Hazardous">Hazardous</option>
                            <option value="Oversized">Oversized</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pickup_location">Pickup Location</label>
                        <input type="text" id="pickup_location" name="pickup_location" required placeholder="e.g., Hawassa">
                    </div>
                    
                    <div class="form-group">
                        <label for="dropoff_location">Dropoff Location</label>
                        <input type="text" id="dropoff_location" name="dropoff_location" required placeholder="e.g., Addis Ababa">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancel-cargo">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_cargo">Add Cargo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
    <div class="modal" id="edit-profile-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="modal-close" id="close-profile-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($shipper_data['phone'] ?? ''); ?>" placeholder="e.g., +1 (555) 123-4567">
                    </div>

                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($shipper_data['company_name'] ?? ''); ?>" placeholder="Your company name">
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancel-profile">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_profile">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Cargo Modal -->
    <div class="modal" id="view-cargo-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cargo Details</h3>
                <button class="modal-close" onclick="closeModal('view-cargo-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="cargo-details-content">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('view-cargo-modal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Cargo Modal -->
    <div class="modal" id="edit-cargo-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Cargo</h3>
                <button class="modal-close" onclick="closeModal('edit-cargo-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-cargo-form" method="POST" action="">
                    <input type="hidden" name="update_cargo" value="1">
                    <input type="hidden" name="cargo_id" id="edit-cargo-id">

                    <div class="form-group">
                        <label for="edit-description">Cargo Description</label>
                        <input type="text" id="edit-description" name="description" required>
                    </div>

                    <div class="form-group">
                        <label for="edit-weight">Weight (kg)</label>
                        <input type="number" id="edit-weight" name="weight" required>
                    </div>

                    <div class="form-group">
                        <label for="edit-dimensions">Dimensions (LxWxH)</label>
                        <input type="text" id="edit-dimensions" name="dimensions">
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

                    <div class="form-group">
                        <label for="edit-pickup-location">Pickup Location</label>
                        <input type="text" id="edit-pickup-location" name="pickup_location" required>
                    </div>

                    <div class="form-group">
                        <label for="edit-dropoff-location">Dropoff Location</label>
                        <input type="text" id="edit-dropoff-location" name="dropoff_location" required>
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
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
        document.getElementById('notification-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('notification-dropdown').classList.toggle('show');
            document.getElementById('messages-dropdown').classList.remove('show');
        });
        
        // Messages dropdown
        document.getElementById('messages-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('messages-dropdown').classList.toggle('show');
            document.getElementById('notification-dropdown').classList.remove('show');
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.getElementById('notification-dropdown').classList.remove('show');
            document.getElementById('messages-dropdown').classList.remove('show');
        });
        
        // Add Cargo Modal
        const addCargoModal = document.getElementById('add-cargo-modal');
        const addCargoBtns = document.querySelectorAll('#add-cargo-btn, #add-cargo-btn-2, #add-cargo-btn-3, #add-cargo-btn-4, #add-cargo-btn-5, #add-cargo-for-matching');
        const closeCargoModal = document.getElementById('close-cargo-modal');
        const cancelCargo = document.getElementById('cancel-cargo');
        
        addCargoBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                addCargoModal.classList.add('active');
            });
        });
        
        closeCargoModal.addEventListener('click', function() {
            addCargoModal.classList.remove('active');
        });
        
        cancelCargo.addEventListener('click', function() {
            addCargoModal.classList.remove('active');
        });
        
        // Edit Profile Modal
        const editProfileModal = document.getElementById('edit-profile-modal');
        const editProfileBtn = document.getElementById('edit-profile-btn');
        const closeProfileModal = document.getElementById('close-profile-modal');
        const cancelProfile = document.getElementById('cancel-profile');
        
        editProfileBtn.addEventListener('click', function() {
            editProfileModal.classList.add('active');
        });
        
        closeProfileModal.addEventListener('click', function() {
            editProfileModal.classList.remove('active');
        });
        
        cancelProfile.addEventListener('click', function() {
            editProfileModal.classList.remove('active');
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === addCargoModal) {
                addCargoModal.classList.remove('active');
            }
            if (event.target === editProfileModal) {
                editProfileModal.classList.remove('active');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Mark notification as read when clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.dropdown-item')) {
                const item = e.target.closest('.dropdown-item');
                const notificationId = item.dataset.notificationId;
                if (!notificationId || !item.classList.contains('unread')) {
                    return; // No ID or already read
                }

                fetch(`api/mark_notification_read.php?id=${notificationId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            item.classList.remove('unread');
                            // Update badge count
                            updateNotificationBadge();
                        }
                    })
                    .catch(error => console.error('Error marking notification as read:', error));
            }
        });

        // Function to update notification badge
        function updateNotificationBadge() {
            fetch('api/get_unread_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-badge');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        }

            // Available Trucks functionality
    document.getElementById('refresh-trucks-btn')?.addEventListener('click', function() {
        window.location.reload();
    });

    // Truck Details Modal
    function openTruckDetails(truck) {
        // Create modal HTML for truck details
        const modalHtml = `
            <div class="modal active" id="truck-details-modal">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h3>Truck Details</h3>
                        <button class="modal-close" onclick="closeModal('truck-details-modal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="truck-detail-section" style="margin-bottom: 25px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Truck Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <strong>Model:</strong> ${truck.truck_model || 'N/A'}
                                </div>
                                <div>
                                    <strong>Type:</strong> ${truck.truck_type || 'N/A'}
                                </div>
                                <div>
                                    <strong>Capacity:</strong> ${truck.capacity || '0'} kg
                                </div>
                                <div>
                                    <strong>License Plate:</strong> ${truck.license_plate || 'N/A'}
                                </div>
                                <div>
                                    <strong>Current Location:</strong> ${truck.current_location || 'Unknown'}
                                </div>
                                <div>
                                    <strong>Operating Route:</strong> ${truck.operating_route || 'N/A'}
                                </div>
                                <div>
                                    <strong>Available From:</strong> ${truck.available_from ? new Date(truck.available_from).toLocaleDateString() : 'Immediately'}
                                </div>
                                <div>
                                    <strong>Status:</strong> <span class="status-badge delivered">Available</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="carrier-detail-section" style="margin-bottom: 25px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Carrier Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <strong>Company:</strong> ${truck.carrier_company || 'N/A'}
                                </div>
                                <div>
                                    <strong>Contact:</strong> ${truck.carrier_contact || 'N/A'}
                                </div>
                                <div>
                                    <strong>Email:</strong> ${truck.carrier_email || 'N/A'}
                                </div>
                                <div>
                                    <strong>Phone:</strong> ${truck.carrier_phone || 'N/A'}
                                </div>
                            </div>
                        </div>
                        
                        ${truck.special_features ? `
                        <div class="features-section" style="margin-bottom: 25px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Special Features</h4>
                            <p>${truck.special_features}</p>
                        </div>
                        ` : ''}
                        
                        <div class="modal-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button class="btn btn-secondary" onclick="closeModal('truck-details-modal')">Close</button>
                            <button class="btn btn-primary" onclick="contactCarrier(${truck.id}, '${truck.carrier_company || 'Carrier'}')">
                                <i class="fas fa-envelope"></i> Contact Carrier
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    // Contact Carrier Function
    function contactCarrier(truckId, carrierName) {
        // Close any open modals first
        closeModal('truck-details-modal');
        
        // Create contact modal
        const modalHtml = `
            <div class="modal active" id="contact-carrier-modal">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>Contact ${carrierName}</h3>
                        <button class="modal-close" onclick="closeModal('contact-carrier-modal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="contact-carrier-form">
                            <input type="hidden" name="truck_id" value="${truckId}">
                            
                            <div class="form-group">
                                <label for="message-subject">Subject</label>
                                <input type="text" id="message-subject" name="subject" 
                                       value="Inquiry about available truck" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="message-content">Message</label>
                                <textarea id="message-content" name="message" rows="6" placeholder="Type your message to the carrier..." required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('contact-carrier-modal')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Handle form submission
        document.getElementById('contact-carrier-form').addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessageToCarrier(this);
        });
    }

    // Send message to carrier (placeholder function)
    function sendMessageToCarrier(form) {
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;
        
        // Simulate API call (replace with actual API endpoint)
        setTimeout(() => {
            // Show success message
            alert('Message sent successfully! The carrier will contact you soon.');
            closeModal('contact-carrier-modal');
            
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 1500);
    }

    // Generic modal close function
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.remove();
        }
    }

    // Smart Matching functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Contact for Match modal
        const contactMatchBtns = document.querySelectorAll('.contact-match-btn');
        contactMatchBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const cargoId = this.dataset.cargoId;
                const cargoDescription = this.dataset.cargoDescription;
                const truckId = this.dataset.truckId;
                const truckModel = this.dataset.truckModel;
                const carrierId = this.dataset.carrierId;
                const carrierCompany = this.dataset.carrierCompany;
                
                openContactMatchModal(cargoId, cargoDescription, truckId, truckModel, carrierId, carrierCompany);
            });
        });
        
        // View All Matches
        const viewAllMatchesBtns = document.querySelectorAll('.view-all-matches');
        viewAllMatchesBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const cargoId = this.dataset.cargoId;
                showAllMatchesForCargo(cargoId);
            });
        });
    });

    // Contact for Match Modal
    function openContactMatchModal(cargoId, cargoDescription, truckId, truckModel, carrierId, carrierCompany) {
        const modalHtml = `
            <div class="modal active" id="contact-match-modal">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h3>Contact Carrier for Match</h3>
                        <button class="modal-close" onclick="closeModal('contact-match-modal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: var(--primary-blue);">Match Details</h4>
                            <p style="margin: 5px 0;"><strong>Cargo:</strong> ${cargoDescription}</p>
                            <p style="margin: 5px 0;"><strong>Truck:</strong> ${truckModel}</p>
                            <p style="margin: 5px 0;"><strong>Carrier:</strong> ${carrierCompany}</p>
                        </div>
                        
                        <form id="contact-match-form" method="POST">
                            <input type="hidden" name="contact_for_match" value="1">
                            <input type="hidden" name="cargo_id" value="${cargoId}">
                            <input type="hidden" name="truck_id" value="${truckId}">
                            <input type="hidden" name="carrier_id" value="${carrierId}">
                            
                            <div class="form-group">
                                <label for="match-message">Message to Carrier</label>
                                <textarea id="match-message" name="message" rows="6" required placeholder="Hello, I'm interested in matching my cargo with your truck. Please let me know availability and rates...">
Hello, I'm interested in matching my cargo '${cargoDescription}' with your ${truckModel}. 

The cargo details are:
- Ready for pickup
- Standard handling requirements

Please let me know your availability and rates.

Thank you!
                                </textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('contact-match-modal')">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Match Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Handle form submission
        document.getElementById('contact-match-form').addEventListener('submit', function(e) {
            // Form will submit normally via POST
            // You could add AJAX here if needed
        });
    }

    // Show All Matches for a Cargo
    function showAllMatchesForCargo(cargoId) {
        // This would typically make an AJAX call to get all matches
        // For now, we'll just scroll to the cargo section
        const cargoElement = document.querySelector(`[data-cargo-id="${cargoId}"]`).closest('.cargo-item');
        if (cargoElement) {
            cargoElement.scrollIntoView({ behavior: 'smooth' });
        }
    }

    </script>
</body>
</html>