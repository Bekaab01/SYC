<?php
// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();
error_log("Profile page accessed - User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", User Type: " . ($_SESSION['user_type'] ?? 'Not set'));

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

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'unknown';

// Initialize variables
$user_name = "User";
$user_initials = "U";
$company_name = "Account";
$welcome_name = "User";
$profile_data = [];
$success_message = "";
$error_message = "";
$password_error = "";

// Fetch user's syc_id
$user_syc_id = null;
try {
    $user_stmt = $pdo->prepare("SELECT syc_id FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_syc_id = $user_data['syc_id'] ?? null;
} catch (PDOException $e) {
    error_log("User syc_id fetch error: " . $e->getMessage());
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// Fetch profile data based on user type
try {
    if ($user_type === 'carrier') {
        $profile_stmt = $pdo->prepare("
            SELECT
                u.*,
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
    } elseif ($user_type === 'shipper') {
        $profile_stmt = $pdo->prepare("
            SELECT
                u.*,
                s.id as shipper_id,
                s.syc_id as syc_shipper_id,
                s.company_name,
                s.company_contact_name,
                s.email as shipper_email,
                s.created_at as shipper_created_at,
                u.first_name,
                u.last_name
            FROM users u
            LEFT JOIN shippers s ON u.syc_id = s.syc_id
            WHERE u.id = ?
        ");
    } else {
        // For other user types or fallback
        $profile_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    }
    
    $profile_stmt->execute([$user_id]);
    $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile_data) {
        // Determine display name
        $first_name = $profile_data['first_name'] ?? null;
        $last_name = $profile_data['last_name'] ?? null;
        $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($profile_data['name'] ?? $profile_data['username'] ?? 'User');
        $user_name = $display_name;

        // Extract first name for welcome message
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
        $company_name = $profile_data['company_name'] ?? ($user_type === 'carrier' ? 'Carrier Account' : 'Shipping Company');
        
        // Set specific IDs based on user type
        if ($user_type === 'carrier') {
            $syc_id = $profile_data['syc_carrier_id'] ?? $user_syc_id;
        } elseif ($user_type === 'shipper') {
            $syc_id = $profile_data['syc_shipper_id'] ?? $user_syc_id;
        } else {
            $syc_id = $user_syc_id;
        }
    }
} catch (PDOException $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    $error_message = "Error loading profile data: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    
    try {
        // Update users table
        $update_user_stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $update_user_stmt->execute([$first_name, $last_name, $phone, $user_id]);
        
        // Update carrier/shipper table if applicable
        if ($user_type === 'carrier') {
            $update_profile_stmt = $pdo->prepare("
                UPDATE carriers 
                SET company_name = ?, contact_person = ?, updated_at = NOW() 
                WHERE syc_id = ?
            ");
            $update_profile_stmt->execute([$company_name, $first_name . ' ' . $last_name, $user_syc_id]);
        } elseif ($user_type === 'shipper') {
            $update_profile_stmt = $pdo->prepare("
                UPDATE shippers 
                SET company_name = ?, company_contact_name = ?, updated_at = NOW() 
                WHERE syc_id = ?
            ");
            $update_profile_stmt->execute([$company_name, $first_name . ' ' . $last_name, $user_syc_id]);
        }
        
        if ($update_user_stmt->rowCount() > 0) {
            $success_message = "Profile updated successfully!";
            
            // Refresh profile data
            $profile_stmt->execute([$user_id]);
            $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update display name
            $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($profile_data['name'] ?? $profile_data['username'] ?? 'User');
            $user_name = $display_name;
            $user_initials = strtoupper(substr($display_name, 0, 2));
            $welcome_name = $first_name ? ucfirst(strtolower(trim($first_name))) : $welcome_name;
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "Password must be at least 8 characters long.";
    } else {
        try {
            // Verify current password
            $password_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $password_stmt->execute([$user_id]);
            $user_password = $password_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_password && password_verify($current_password, $user_password['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password_stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $update_password_stmt->execute([$hashed_password, $user_id]);
                
                if ($update_password_stmt->rowCount() > 0) {
                    $success_message = "Password updated successfully!";
                } else {
                    $password_error = "Error updating password. Please try again.";
                }
            } else {
                $password_error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            $password_error = "Error updating password: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - My Profile</title>
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

        .sidebar-middle {
            flex: 1;
            padding: 30px 0;
            overflow-y: auto;
            max-height: calc(100vh - 200px);
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

        .notification-btn, .messages-btn {
            position: relative;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .notification-btn:hover, .messages-btn:hover {
            background: var(--light-gray);
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

        /* Profile Content */
        .profile-content {
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

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Card */
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

        /* Profile Picture */
        .profile-picture {
            text-align: center;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-yellow);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 36px;
            margin: 0 auto 15px;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .profile-role {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: var(--light-gray);
            border-radius: 10px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
            position: relative;
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

        .form-control.valid {
            border-color: #4CAF50;
        }

        .form-control.invalid {
            border-color: #f44336;
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-hint i {
            font-size: 14px;
        }

        .form-feedback {
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-feedback.valid {
            color: #4CAF50;
            display: block;
        }

        .form-feedback.invalid {
            color: #f44336;
            display: block;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: var(--secondary-blue);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark-gray);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 25px;
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

        .tab:hover {
            color: var(--primary-blue);
        }

        /* Responsive Design */
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

            .profile-content {
                padding: 20px 15px;
            }

            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .tabs {
                overflow-x: auto;
                white-space: nowrap;
            }

            .profile-stats {
                grid-template-columns: 1fr;
            }
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary-blue);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 5px;
            height: 4px;
            border-radius: 2px;
            background: #eee;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }

        .password-strength.weak .password-strength-bar {
            background: #f44336;
            width: 33%;
        }

        .password-strength.medium .password-strength-bar {
            background: #FF9800;
            width: 66%;
        }

        .password-strength.strong .password-strength-bar {
            background: #4CAF50;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-top">
            <a href="index.php" class="sidebar-logo">
                <img src="assets/img/SYC-Transparent.png" alt="SYC" style="filter: brightness(0) invert(1);">
                <span class="sidebar-logo-text">SYC<span class="sidebar-logo-dot">.</span></span>
            </a>
        </div>

        <nav class="sidebar-middle">
            <ul>
                <?php if ($user_type === 'carrier'): ?>
                    <li><a href="carrier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="carrier-dashboard.php?tab=my-trucks"><i class="fas fa-truck"></i> My Trucks</a></li>
                    <li><a href="carrier-dashboard.php?tab=available-loads"><i class="fas fa-truck-loading"></i> Available Loads</a></li>
                    <li><a href="carrier-dashboard.php?tab=my-loads"><i class="fas fa-shipping-fast"></i> My Loads</a></li>
                <?php elseif ($user_type === 'shipper'): ?>
                    <li><a href="shipper-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="shipper-dashboard.php?tab=my-cargo"><i class="fas fa-truck-loading"></i> My Cargo</a></li>
                    <li><a href="shipper-dashboard.php?tab=available-trucks"><i class="fas fa-truck"></i> Available Trucks</a></li>
                    <li><a href="shipper-dashboard.php?tab=matching"><i class="fas fa-heart"></i> Smart Matching</a></li>
                <?php endif; ?>
                <li><a href="#" class="active"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <a href="profile.php" class="user-profile-link">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                    <div class="user-info">
                        <p><?php echo htmlspecialchars($user_name); ?></p>
                    </div>
                </div>
            </a>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Log Out</a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            
            <div class="header-actions">
                <a href="profile.php" class="header-profile-btn">
                    <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                </a>
            </div>
        </header>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>My Profile</h2>
                    <p>Manage your account settings and personal information</p>
                </div>
                <div class="user-avatar" style="width: 60px; height: 60px; font-size: 20px;">
                    <?php echo htmlspecialchars($user_initials); ?>
                </div>
            </div>

            <!-- Profile Container -->
            <div class="profile-container">
                <!-- Left Column - Profile Overview -->
                <div>
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-picture">
                            <div class="profile-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                            <div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="profile-role">
                                <?php 
                                if ($user_type === 'carrier') {
                                    echo 'Carrier Account';
                                } elseif ($user_type === 'shipper') {
                                    echo 'Shipper Account';
                                } else {
                                    echo 'User Account';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php 
                                    if ($user_type === 'carrier') {
                                        echo 'TRK';
                                    } elseif ($user_type === 'shipper') {
                                        echo 'SHP';
                                    } else {
                                        echo 'USR';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Account Type</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo htmlspecialchars($syc_id ?? 'N/A'); ?></div>
                                <div class="stat-label">SYC ID</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Info Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <h2>Account Information</h2>
                        </div>
                        <div class="profile-info">
                            <div class="info-item">
                                <div class="info-label">Account Status</div>
                                <div class="info-value">
                                    <span style="color: #4CAF50; font-weight: 500;">
                                        <i class="fas fa-check-circle"></i> Active
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo htmlspecialchars(date('M j, Y', strtotime($profile_data['created_at'] ?? 'N/A'))); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value"><?php echo htmlspecialchars(date('M j, Y', strtotime($profile_data['updated_at'] ?? $profile_data['created_at'] ?? 'N/A'))); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Profile Settings -->
                <div>
                    <!-- Tabs -->
                    <div class="tabs">
                        <div class="tab active" data-tab="profile-settings">Profile Settings</div>
                        <div class="tab" data-tab="password-settings">Password & Security</div>
                    </div>
                    
                    <!-- Messages -->
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Profile Settings Tab -->
                    <div id="profile-settings" class="tab-content active">
                        <div class="profile-card">
                            <div class="profile-header">
                                <h2>Personal Information</h2>
                            </div>
                            
                            <form id="profile-form" method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['first_name'] ?? ''); ?>" 
                                           placeholder="Enter your first name">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i> Your legal first name
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['last_name'] ?? ''); ?>" 
                                           placeholder="Enter your last name">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i> Your legal last name
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['email'] ?? $profile_data['carrier_email'] ?? $profile_data['shipper_email'] ?? ''); ?>" 
                                           placeholder="Enter your email address" readonly>
                                    <div class="form-hint">
                                        <i class="fas fa-lock"></i> Email cannot be changed
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['phone'] ?? ''); ?>" 
                                           placeholder="Enter your phone number">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i> Your contact phone number
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_name">Company Name</label>
                                    <input type="text" id="company_name" name="company_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['company_name'] ?? ''); ?>" 
                                           placeholder="Enter your company name">
                                    <div class="form-hint">
                                        <i class="fas fa-building"></i> Your business or company name
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Password Settings Tab -->
                    <div id="password-settings" class="tab-content">
                        <div class="profile-card">
                            <div class="profile-header">
                                <h2>Change Password</h2>
                            </div>
                            
                            <?php if (isset($password_error)): ?>
                                <div class="alert alert-error">
                                    <?php echo htmlspecialchars($password_error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form id="password-form" method="POST">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control" 
                                           placeholder="Enter your current password">
                                    <div class="form-hint">
                                        <i class="fas fa-key"></i> Your current account password
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" 
                                           placeholder="Enter your new password">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i> Choose a strong password with at least 8 characters
                                    </div>
                                    <div class="password-strength" id="password-strength">
                                        <div class="password-strength-bar"></div>
                                    </div>
                                    <div class="form-feedback" id="password-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           placeholder="Confirm your new password">
                                    <div class="form-hint">
                                        <i class="fas fa-check-circle"></i> Re-enter your new password
                                    </div>
                                    <div class="form-feedback" id="confirm-feedback"></div>
                                </div>
                                
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-key"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Menu Toggle for Mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Tab Switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and content
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Password Strength Indicator
        const passwordInput = document.getElementById('new_password');
        const passwordStrength = document.getElementById('password-strength');
        const passwordFeedback = document.getElementById('password-feedback');
        const confirmInput = document.getElementById('confirm_password');
        const confirmFeedback = document.getElementById('confirm-feedback');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let feedback = '';
            
            // Check password length
            if (password.length >= 8) {
                strength += 1;
            } else {
                feedback = 'Password should be at least 8 characters long';
            }
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) {
                strength += 1;
            } else {
                feedback = 'Add uppercase letters for stronger password';
            }
            
            // Check for numbers
            if (/[0-9]/.test(password)) {
                strength += 1;
            } else {
                feedback = 'Add numbers for stronger password';
            }
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 1;
            } else {
                feedback = 'Add special characters for strongest password';
            }
            
            // Update UI
            passwordStrength.className = 'password-strength';
            passwordFeedback.className = 'form-feedback';
            
            if (password.length === 0) {
                passwordFeedback.style.display = 'none';
                return;
            }
            
            if (strength <= 1) {
                passwordStrength.classList.add('weak');
                passwordFeedback.classList.add('invalid');
                passwordFeedback.textContent = 'Weak password';
            } else if (strength <= 3) {
                passwordStrength.classList.add('medium');
                passwordFeedback.classList.add('valid');
                passwordFeedback.textContent = 'Medium strength password';
            } else {
                passwordStrength.classList.add('strong');
                passwordFeedback.classList.add('valid');
                passwordFeedback.textContent = 'Strong password';
            }
        });

        // Password Confirmation Validation
        confirmInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirm = this.value;
            
            confirmFeedback.className = 'form-feedback';
            
            if (confirm.length === 0) {
                confirmFeedback.style.display = 'none';
                return;
            }
            
            if (password === confirm) {
                confirmInput.classList.remove('invalid');
                confirmInput.classList.add('valid');
                confirmFeedback.classList.add('valid');
                confirmFeedback.textContent = 'Passwords match';
            } else {
                confirmInput.classList.remove('valid');
                confirmInput.classList.add('invalid');
                confirmFeedback.classList.add('invalid');
                confirmFeedback.textContent = 'Passwords do not match';
            }
        });

        // Form Validation
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            let valid = true;
            
            // Validate required fields
            const requiredFields = this.querySelectorAll('input[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('invalid');
                    valid = false;
                } else {
                    field.classList.remove('invalid');
                }
            });
            
            // Validate email format
            const emailField = document.getElementById('email');
            if (emailField.value && !isValidEmail(emailField.value)) {
                emailField.classList.add('invalid');
                valid = false;
            } else {
                emailField.classList.remove('invalid');
            }
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });

        document.getElementById('password-form').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all password fields.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return;
            }
        });

        // Helper function to validate email
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const successMessages = document.querySelectorAll('.alert-success');
            successMessages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s ease';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>