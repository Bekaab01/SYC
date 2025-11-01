<?php
// Admin Dashboard - No authentication required
// WARNING: This page has full admin access without login for development purposes

include_once __DIR__ . '/../private/db.php';

// Handle POST actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['approve_carrier'])) {
            $carrier_id = $_POST['carrier_id'];
            $stmt = $pdo->prepare("UPDATE carriers SET status = 'verified', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$carrier_id]);
            $message = "Carrier approved successfully!";
        } elseif (isset($_POST['reject_carrier'])) {
            $carrier_id = $_POST['carrier_id'];
            $stmt = $pdo->prepare("UPDATE carriers SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$carrier_id]);
            $message = "Carrier rejected successfully!";
        } elseif (isset($_POST['change_user_type'])) {
            $user_id = $_POST['user_id'];
            $new_type = $_POST['user_type'];
            $stmt = $pdo->prepare("UPDATE users SET user_type = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_type, $user_id]);
            $message = "User type updated successfully!";
        } elseif (isset($_POST['delete_user'])) {
            $user_id = $_POST['user_id'];
            // Delete from users table (cascade will handle related tables)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = "User deleted successfully!";
        } elseif (isset($_POST['accept_bid'])) {
            $bid_id = $_POST['bid_id'];
            $stmt = $pdo->prepare("UPDATE bids SET status = 'accepted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$bid_id]);
            $message = "Bid accepted successfully!";
        } elseif (isset($_POST['reject_bid'])) {
            $bid_id = $_POST['bid_id'];
            $stmt = $pdo->prepare("UPDATE bids SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$bid_id]);
            $message = "Bid rejected successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch dashboard stats
$stats = [];
try {
    // User stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'carrier'");
    $stats['total_carriers'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'shipper'");
    $stats['total_shippers'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'admin'");
    $stats['total_admins'] = $stmt->fetch()['total'];

    // Carrier stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM carriers WHERE status = 'pending'");
    $stats['pending_carriers'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM carriers WHERE status = 'verified'");
    $stats['verified_carriers'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM carriers WHERE status = 'rejected'");
    $stats['rejected_carriers'] = $stmt->fetch()['total'];

    // Load stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cargo");
    $stats['total_loads'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bids");
    $stats['total_bids'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bids WHERE status = 'accepted'");
    $stats['accepted_bids'] = $stmt->fetch()['total'];

} catch (PDOException $e) {
    $stats = array_fill_keys(['total_users', 'total_carriers', 'total_shippers', 'total_admins', 'pending_carriers', 'verified_carriers', 'rejected_carriers', 'total_loads', 'total_bids', 'accepted_bids'], 0);
}

// Fetch all users
$all_users = [];
try {
    $stmt = $pdo->query("
        SELECT u.*, c.status as carrier_status, c.company_name
        FROM users u
        LEFT JOIN carriers c ON u.syc_id = c.syc_id
        ORDER BY u.created_at DESC
    ");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_users = [];
}

// Fetch all carriers ordered by status with pending first
$all_carriers = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, u.email, u.first_name, u.last_name, c.phone, u.created_at as user_created_at
        FROM carriers c
        LEFT JOIN users u ON c.user_id = u.id
        ORDER BY FIELD(c.status, 'pending', 'manual_review', 'verified', 'rejected'), c.created_at DESC
    ");
    $all_carriers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_carriers = [];
}

// Fetch all loads
$all_loads = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, s.company_name as shipper_company, u.email as shipper_email
        FROM cargo c
        LEFT JOIN shippers s ON c.shipper_id = s.id
        LEFT JOIN users u ON s.syc_id = u.syc_id
        ORDER BY c.created_at DESC
    ");
    $all_loads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_loads = [];
}

// Fetch all bids
$all_bids = [];
try {
    $stmt = $pdo->query("
        SELECT b.*, c.cargo_id, c.description, c.pickup_location, c.dropoff_location,
               ca.company_name as carrier_company, u.email as carrier_email
        FROM bids b
        LEFT JOIN cargo c ON b.load_id = c.id
        LEFT JOIN carriers ca ON b.carrier_id = ca.id
        LEFT JOIN users u ON ca.syc_id = u.syc_id
        ORDER BY b.created_at DESC
    ");
    $all_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_bids = [];
}

// Fetch all notifications
$all_notifications = [];
try {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100");
    $all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_notifications = [];
}

// Fetch all messages
$all_messages = [];
try {
    $stmt = $pdo->query("
        SELECT m.*, u1.username as sender_name, u2.username as receiver_name
        FROM messages m
        LEFT JOIN users u1 ON m.sender_syc_id = u1.syc_id
        LEFT JOIN users u2 ON m.receiver_syc_id = u2.syc_id
        ORDER BY m.created_at DESC LIMIT 100
    ");
    $all_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_messages = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Admin Dashboard</title>
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

        .sidebar-header {
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

        .admin-notice {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid var(--primary-yellow);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 12px;
            color: var(--primary-yellow);
        }

        .admin-notice i {
            margin-right: 8px;
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

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-blue);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
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
            text-align: center;
        }

        .welcome-banner h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            opacity: 0.9;
            font-size: 16px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
        }

        .stat-icon.blue { background: rgba(0, 51, 102, 0.1); color: var(--primary-blue); }
        .stat-icon.green { background: rgba(76, 175, 80, 0.1); color: #4CAF50; }
        .stat-icon.orange { background: rgba(255, 152, 0, 0.1); color: #FF9800; }
        .stat-icon.purple { background: rgba(156, 39, 176, 0.1); color: #9C27B0; }
        .stat-icon.red { background: rgba(244, 67, 54, 0.1); color: #F44336; }

        .stat-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--text-light);
            font-size: 14px;
        }

        /* Tables */
        .data-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .table-header {
            padding: 20px 25px;
            background: var(--light-gray);
            border-bottom: 1px solid #eee;
        }

        .table-header h3 {
            color: var(--primary-blue);
            font-size: 18px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: var(--light-gray);
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending { background: #FFF3CD; color: #856404; }
        .status-badge.verified { background: #D4EDDA; color: #155724; }
        .status-badge.rejected { background: #F8D7DA; color: #721C24; }
        .status-badge.accepted { background: #D4EDDA; color: #155724; }
        .status-badge.active { background: #D4EDDA; color: #155724; }
        .status-badge.inactive { background: #F8D7DA; color: #721C24; }

        .action-btn {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
            margin-right: 5px;
        }

        .action-btn:hover {
            background: var(--secondary-blue);
        }

        .action-btn.danger {
            background: #dc3545;
        }

        .action-btn.danger:hover {
            background: #c82333;
        }

        .action-btn.success {
            background: #28a745;
        }

        .action-btn.success:hover {
            background: #218838;
        }

        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--primary-blue);
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        /* Alerts */
        .alert {
            padding: 15px;
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

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive */
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo">
                <img src="assets/img/SYC-Transparent.png" alt="SYC" style="filter: brightness(0) invert(1);">
                <span class="sidebar-logo-text">SYC<span class="sidebar-logo-dot">.</span></span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li><a href="#" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#" data-tab="users"><i class="fas fa-users"></i> User Management</a></li>
                <li><a href="#" data-tab="carriers"><i class="fas fa-truck"></i> Carrier Management</a></li>
                <li><a href="#" data-tab="loads"><i class="fas fa-shipping-fast"></i> Load Management</a></li>
                <li><a href="#" data-tab="bids"><i class="fas fa-gavel"></i> Bid Management</a></li>
                <li><a href="#" data-tab="notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="#" data-tab="messages"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>ADMIN ACCESS</strong><br>
                No authentication required
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>

            <div class="page-title">Admin Dashboard</div>

            <div class="header-actions">
                <span style="font-size: 14px; color: var(--text-light);">
                    <i class="fas fa-shield-alt"></i> Full Admin Access
                </span>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <div class="welcome-banner">
                    <h2>Admin Dashboard</h2>
                    <p>Full administrative control over SYC platform</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_carriers']; ?></h3>
                            <p>Total Carriers</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_shippers']; ?></h3>
                            <p>Total Shippers</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_loads']; ?></h3>
                            <p>Total Loads</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['pending_carriers']; ?></h3>
                            <p>Pending Carriers</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['verified_carriers']; ?></h3>
                            <p>Verified Carriers</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['rejected_carriers']; ?></h3>
                            <p>Rejected Carriers</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_bids']; ?></h3>
                            <p>Total Bids</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h3>User Management</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>SYC ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['syc_id']); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="user_type" onchange="this.form.submit()" class="form-control" style="width: auto; display: inline;">
                                                <option value="shipper" <?php echo $user['user_type'] == 'shipper' ? 'selected' : ''; ?>>Shipper</option>
                                                <option value="carrier" <?php echo $user['user_type'] == 'carrier' ? 'selected' : ''; ?>>Carrier</option>
                                                <option value="admin" <?php echo $user['user_type'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <input type="hidden" name="change_user_type" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($user['carrier_status']): ?>
                                            <span class="status-badge <?php echo $user['carrier_status']; ?>">
                                                <?php echo ucfirst($user['carrier_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge active">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="action-btn danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Carriers Tab -->
            <div id="carriers" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h3>Carrier Management</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_carriers as $carrier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($carrier['id']); ?></td>
                                    <td><?php echo htmlspecialchars($carrier['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($carrier['first_name'] ?? '') . ' ' . ($carrier['last_name'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars($carrier['email']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $carrier['status']; ?>">
                                            <?php echo ucfirst($carrier['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($carrier['created_at'])); ?></td>
                                    <td>
                                        <a href="carrier-details.php?carrier_id=<?php echo htmlspecialchars($carrier['id']); ?>" class="action-btn" style="margin-right: 5px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($carrier['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="carrier_id" value="<?php echo $carrier['id']; ?>">
                                                <button type="submit" name="approve_carrier" class="action-btn success">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="submit" name="reject_carrier" class="action-btn danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Loads Tab -->
            <div id="loads" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h3>Load Management</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Description</th>
                                    <th>Shipper</th>
                                    <th>Origin</th>
                                    <th>Destination</th>
                                    <th>Weight</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_loads as $load): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($load['cargo_id'] ?? $load['id']); ?></td>
                                    <td><?php echo htmlspecialchars($load['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($load['shipper_company'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($load['pickup_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($load['dropoff_location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($load['weight'] ?? '0'); ?> kg</td>
                                    <td>
                                        <span class="status-badge <?php echo ($load['status'] ?? 'pending') === 'Pending' ? 'pending' : 'active'; ?>">
                                            <?php echo htmlspecialchars($load['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($load['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bids Tab -->
            <div id="bids" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h3>Bid Management</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Load</th>
                                    <th>Carrier</th>
                                    <th>Bid Amount</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_bids as $bid): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bid['id']); ?></td>
                                    <td><?php echo htmlspecialchars($bid['cargo_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($bid['carrier_company'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo htmlspecialchars($bid['bid_amount']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $bid['status']; ?>">
                                            <?php echo ucfirst($bid['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($bid['created_at'])); ?></td>
                                    <td>
                                        <?php if ($bid['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                                <button type="submit" name="accept_bid" class="action-btn success">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button type="submit" name="reject_bid" class="action-btn danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h3>All Notifications</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>SYC ID</th>
                                    <th>Message</th>
                                    <th>Read</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_notifications as $notification): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notification['id']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['syc_id']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $notification['is_read'] ? 'active' : 'pending'; ?>">
                                            <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Messages Tab -->
            <div id="messages" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h3>All Messages</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Message</th>
                                    <th>Read</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_messages as $message): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($message['id']); ?></td>
                                    <td><?php echo htmlspecialchars($message['sender_name'] ?? $message['sender_syc_id']); ?></td>
                                    <td><?php echo htmlspecialchars($message['receiver_name'] ?? $message['receiver_syc_id']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($message['message'], 0, 100)) . (strlen($message['message']) > 100 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $message['is_read'] ? 'active' : 'pending'; ?>">
                                            <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($message['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
            const tabContents = document.querySelectorAll('.tab-content');

            sidebarLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Remove active class from all links and tabs
                    sidebarLinks.forEach(l => l.classList.remove('active'));
                    tabContents.forEach(t => t.classList.remove('active'));

                    // Add active class to clicked link and corresponding tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        });
    </script>
</body>
</html>
