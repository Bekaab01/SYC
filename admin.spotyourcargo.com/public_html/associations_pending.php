<?php
// Admin Associations Pending Page - Secure Access Required

// Include session configuration
include_once __DIR__ . '/../../spotyourcargo.com/private/session_config.php';

// Start session
session_start();

// Session timeout (30 minutes)
$session_timeout = 30 * 60; // 30 minutes in seconds

// Check if admin is logged in and session hasn't expired
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > $session_timeout) {
    session_destroy();
    header('Location: admin-login.php');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

include_once __DIR__ . '/../../spotyourcargo.com/private/db.php';

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query conditions
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "a.registration_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(a.name LIKE ? OR a.contact_person LIKE ? OR a.email LIKE ? OR a.license_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM associations a $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get associations with pagination
$sql = "SELECT a.*, u.email as user_email
        FROM associations a
        LEFT JOIN users u ON a.user_id = u.id
        $where_clause
        ORDER BY FIELD(a.registration_status, 'pending', 'manual_review', 'approved', 'rejected'), a.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$associations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get document counts for each association
$document_counts = [];
if (!empty($associations)) {
    $ids = array_column($associations, 'id');
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';

    $doc_stmt = $pdo->prepare("SELECT association_id, COUNT(*) as count FROM association_documents WHERE association_id IN ($placeholders) GROUP BY association_id");
    $doc_stmt->execute($ids);
    $doc_results = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($doc_results as $doc) {
        $document_counts[$doc['association_id']] = $doc['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Association Management</title>
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

            --primary-gradient: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #FF9800 0%, #f57c00 100%);
            --danger-gradient: linear-gradient(135deg, #F44336 0%, #d32f2f 100%);
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);

            --light-bg: #f8f9fa;
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-light);
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;

            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header */
        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.03"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.03"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.02"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.02"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }

        .page-header p {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: -2rem auto 4rem;
            padding: 0 2rem;
        }

        /* Back Navigation */
        .back-nav {
            margin-bottom: 2rem;
            position: relative;
            z-index: 10;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            padding: 0.875rem 1.5rem;
            background: var(--white);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 11;
        }

        .back-link:hover {
            transform: translateX(-4px) translateY(-2px);
            box-shadow: var(--shadow-xl);
            color: var(--primary-purple);
        }

        /* Filters and Search */
        .filters-section {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .filters-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filters-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-title i {
            color: var(--primary-blue);
        }

        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .filter-select, .search-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-md);
            font-size: 0.875rem;
            transition: var(--transition-fast);
            background: var(--white);
            min-width: 160px;
        }

        .filter-select:focus, .search-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: var(--transition-normal);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-md);
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .clear-filters {
            color: #ef4444;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            transition: var(--transition-fast);
            border: 1px solid #fecaca;
        }

        .clear-filters:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            transition: var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .stat-icon {
            width: 4rem;
            height: 4rem;
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            position: relative;
        }

        .stat-icon.total { background: var(--primary-gradient); color: white; }
        .stat-icon.pending { background: var(--warning-gradient); color: white; }
        .stat-icon.approved { background: var(--success-gradient); color: white; }
        .stat-icon.rejected { background: var(--danger-gradient); color: white; }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--text-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-info p {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 500;
        }

        /* Table */
        .data-table {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .table-header {
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-header h2 i {
            color: var(--primary-blue);
        }

        .table-results {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 1.25rem 1.5rem;
            text-align: left;
            background: #f8fafc;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition-fast);
        }

        tr:hover td {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%);
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: var(--border-radius-xl);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .status-badge.manual_review {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        .status-badge.approved {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #10b981;
        }
        .status-badge.rejected {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .action-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: var(--transition-normal);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            margin-right: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .action-btn.success {
            background: var(--success-gradient);
        }

        .action-btn.success:hover {
            box-shadow: 0 8px 25px -8px rgba(79, 172, 254, 0.5);
        }

        .action-btn.danger {
            background: var(--danger-gradient);
        }

        .action-btn.danger:hover {
            box-shadow: 0 8px 25px -8px rgba(255, 107, 107, 0.5);
        }

        .action-btn.sm {
            padding: 0.5rem 0.875rem;
            font-size: 0.75rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
            padding: 2rem;
        }

        .pagination a, .pagination span {
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-fast);
            border: 2px solid transparent;
        }

        .pagination a {
            color: var(--primary-blue);
            border-color: var(--border-color);
            background: var(--white);
        }

        .pagination a:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-1px);
        }

        .pagination .current {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
        }

        .pagination .disabled {
            color: var(--text-muted);
            border-color: var(--border-color);
            cursor: not-allowed;
            background: #f8fafc;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--text-muted);
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 1rem;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                margin: -1rem auto 2rem;
                padding: 0 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 3rem 0 2rem;
            }

            .page-header h1 {
                font-size: 2.5rem;
            }

            .page-header p {
                font-size: 1.125rem;
            }

            .container {
                margin: -0.5rem auto 1rem;
                padding: 0 1rem;
            }

            .filters-section {
                padding: 1.5rem;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                justify-content: space-between;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-content {
                gap: 1rem;
            }

            .stat-icon {
                width: 3rem;
                height: 3rem;
                font-size: 1.25rem;
            }

            .stat-info h3 {
                font-size: 2rem;
            }

            .table-container {
                font-size: 0.875rem;
            }

            th, td {
                padding: 1rem 0.75rem;
            }

            .action-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.75rem;
                margin-right: 0.25rem;
            }

            .table-header {
                padding: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 2rem;
            }

            .filters-section {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .table-container {
                font-size: 0.75rem;
            }

            th, td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Loading Animation */
        @keyframes shimmer {
            0% { background-position: -200px 0; }
            100% { background-position: calc(200px + 100%) 0; }
        }

        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200px 100%;
            animation: shimmer 1.5s infinite;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="page-header">
        <h1>Association Management</h1>
        <p>Review and manage association registrations</p>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Back Navigation -->
        <nav class="back-nav">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </nav>

        <!-- Filters and Search -->
        <div class="filters-section">
            <div class="filter-group">
                <label class="filter-label">Status:</label>
                <select class="filter-select" onchange="applyFilters()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="manual_review" <?php echo $status_filter === 'manual_review' ? 'selected' : ''; ?>>Manual Review</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Search:</label>
                <input type="text" class="search-input" placeholder="Name, contact, email, or license..." value="<?php echo htmlspecialchars($search); ?>" onkeypress="handleSearchKeypress(event)">
                <button class="search-btn" onclick="applyFilters()">Search</button>
            </div>

            <?php if ($status_filter !== 'pending' || !empty($search)): ?>
            <a href="associations_pending.php" class="clear-filters">Clear Filters</a>
            <?php endif; ?>
        </div>

        <!-- Stats Cards -->
        <?php
        // Get stats for current filter
        $stats_where = "";
        $stats_params = [];
        if ($status_filter !== 'all') {
            $stats_where = "WHERE registration_status = ?";
            $stats_params = [$status_filter];
        }

        $stats_query = "SELECT
            COUNT(CASE WHEN registration_status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN registration_status = 'manual_review' THEN 1 END) as manual_review,
            COUNT(CASE WHEN registration_status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN registration_status = 'rejected' THEN 1 END) as rejected,
            COUNT(*) as total
            FROM associations $stats_where";

        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute($stats_params);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Associations</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-table">
            <div class="table-header">
                <h2>Associations</h2>
                <div class="table-results">
                    Showing <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> associations
                </div>
            </div>

            <div class="table-container">
                <?php if (!empty($associations)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Association Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>License #</th>
                            <th>Region</th>
                            <th>Status</th>
                            <th>Documents</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($associations as $association): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($association['id']); ?></td>
                            <td><?php echo htmlspecialchars($association['name']); ?></td>
                            <td><?php echo htmlspecialchars($association['contact_person']); ?></td>
                            <td><?php echo htmlspecialchars($association['email']); ?></td>
                            <td><?php echo htmlspecialchars($association['license_number']); ?></td>
                            <td><?php echo htmlspecialchars($association['region']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $association['registration_status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $association['registration_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="document-count">
                                    <?php echo $document_counts[$association['id']] ?? 0; ?> files
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($association['created_at'])); ?></td>
                            <td>
                                <a href="association_view.php?id=<?php echo $association['id']; ?>" class="action-btn sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($association['registration_status'] === 'pending'): ?>
                                <form method="POST" action="association_action.php" style="display: inline;">
                                    <input type="hidden" name="association_id" value="<?php echo $association['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(32)); ?>">
                                    <button type="submit" class="action-btn success sm" onclick="return confirm('Are you sure you want to approve this association?')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" action="association_action.php" style="display: inline;">
                                    <input type="hidden" name="association_id" value="<?php echo $association['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(32)); ?>">
                                    <button type="submit" class="action-btn danger sm" onclick="return confirm('Are you sure you want to reject this association?')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-handshake"></i>
                    <h3>No associations found</h3>
                    <p><?php echo !empty($search) || $status_filter !== 'pending' ? 'Try adjusting your filters.' : 'No associations match the current criteria.'; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">&laquo; Previous</a>
            <?php else: ?>
                <span class="disabled">&laquo; Previous</span>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1): ?>
                <a href="?page=1&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">1</a>
                <?php if ($start_page > 2): ?><span>...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?><span>...</span><?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function applyFilters() {
            const status = document.querySelector('.filter-select').value;
            const search = document.querySelector('.search-input').value.trim();

            let url = 'associations_pending.php?';
            const params = [];

            if (status !== 'all') params.push('status=' + encodeURIComponent(status));
            if (search) params.push('search=' + encodeURIComponent(search));

            url += params.join('&');
            window.location.href = url;
        }

        function handleSearchKeypress(event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        }
    </script>
</body>
</html>
