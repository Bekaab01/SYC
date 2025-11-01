<?php
// Admin Association View Page - Secure Access Required

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

// Check if association ID is provided
if (!isset($_GET['id'])) {
    header('Location: associations_pending.php');
    exit();
}

$association_id = (int)$_GET['id'];

include_once __DIR__ . '/../../spotyourcargo.com/private/db.php';

// Fetch association details
$stmt = $pdo->prepare("
    SELECT a.*, u.email as user_email, u.first_name as user_first_name, u.last_name as user_last_name
    FROM associations a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$association_id]);
$association = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$association) {
    header('Location: associations_pending.php');
    exit();
}

// Fetch association documents
$documents_stmt = $pdo->prepare("SELECT * FROM association_documents WHERE association_id = ? ORDER BY uploaded_at DESC");
$documents_stmt->execute([$association_id]);
$documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch audit log for this association
$audit_stmt = $pdo->prepare("
    SELECT aa.*, u.username as admin_username
    FROM association_audit aa
    LEFT JOIN users u ON aa.admin_user_id = u.id
    WHERE aa.association_id = ?
    ORDER BY aa.created_at DESC
");
$audit_stmt->execute([$association_id]);
$audit_log = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);

// Log the view action
$log_stmt = $pdo->prepare("
    INSERT INTO association_audit (association_id, admin_user_id, admin_username, action, ip_address, user_agent)
    VALUES (?, ?, ?, 'viewed', ?, ?)
");
$log_stmt->execute([
    $association_id,
    $_SESSION['admin_user_id'] ?? null,
    $_SESSION['admin_username'] ?? 'Unknown Admin',
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Association Details</title>
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
            --success-green: #025023ff;
            --danger-red: #ae1504ff;
            --warning-orange: #f39c12;
            --info-blue: #3498db;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
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
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 20px 0;
            text-align: center;
        }

        .page-header h1 {
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: -10px auto 20px;
            padding: 0 20px;
        }

        /* Back Navigation */
        .back-nav {
            margin-bottom: 24px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            padding: 12px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .back-link:hover {
            transform: translateX(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        /* Status Banner */
        .status-banner {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-info h2 {
            color: var(--primary-blue);
            font-size: 24px;
            margin-bottom: 4px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.manual_review { background: #dbeafe; color: #2563eb; }
        .status-badge.approved { background: #d1fae5; color: #065f46; }
        .status-badge.rejected { background: #fee2e2; color: #dc2626; }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Detail Cards */
        .detail-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .card-header {
            background: var(--light-gray);
            padding: 20px 24px;
            border-bottom: 1px solid #eee;
        }

        .card-header h3 {
            color: var(--primary-blue);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: var(--primary-yellow);
        }

        .card-body {
            padding: 24px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-gray);
            line-height: 1.4;
        }

        /* Documents Section */
        .documents-grid {
            display: grid;
            gap: 12px;
        }

        .document-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
        }

        .document-card:hover {
            border-color: var(--primary-blue);
            background: white;
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .document-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
            margin: 0 0 2px 0;
        }

        .document-details p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .document-action {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .document-action:hover {
            background: var(--secondary-blue);
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 24px;
            height: fit-content;
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .sidebar-section:last-child {
            margin-bottom: 0;
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-title i {
            color: var(--primary-yellow);
        }

        /* Audit Log */
        .audit-item {
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .audit-item:last-child {
            border-bottom: none;
        }

        .audit-action {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 4px;
        }

        .audit-meta {
            font-size: 12px;
            color: #64748b;
        }

        .audit-notes {
            font-size: 13px;
            color: var(--dark-gray);
            margin-top: 6px;
            font-style: italic;
        }

        /* Action Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            min-width: 140px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-blue);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-green);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-danger {
            background: var(--danger-red);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
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

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
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
            font-weight: 700;
            color: var(--primary-blue);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 6px;
        }

        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: -10px auto 20px;
                padding: 0 16px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .status-banner {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .action-buttons {
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="page-header">
        <h1>Association Details</h1>
        <p>Review complete association information</p>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Back Navigation -->
        <nav class="back-nav">
            <a href="associations_pending.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Associations
            </a>
        </nav>

        <!-- Status Banner -->
        <div class="status-banner">
            <div class="status-info">
                <h2><?php echo htmlspecialchars($association['name']); ?></h2>
                <span class="status-badge <?php echo $association['registration_status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $association['registration_status'])); ?>
                </span>
            </div>

            <?php if ($association['registration_status'] === 'pending'): ?>
            <div class="action-buttons">
                <button class="btn btn-success" onclick="showActionModal('approve')">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button class="btn btn-danger" onclick="showActionModal('reject')">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Basic Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Association Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">License Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['license_number']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Region</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['region']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Number of Trucks</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['number_of_trucks']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Person</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['contact_person']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Phone</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['phone']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($association['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Registration Date</span>
                                <span class="info-value"><?php echo date('M j, Y H:i', strtotime($association['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> Documents (<?php echo count($documents); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($documents)): ?>
                        <div class="documents-grid">
                            <?php foreach ($documents as $doc): ?>
                            <div class="document-card">
                                <div class="document-info">
                                    <div class="document-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="document-details">
                                        <h4><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))); ?></h4>
                                        <p><?php echo htmlspecialchars($doc['original_filename']); ?> • <?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?></p>
                                    </div>
                                </div>
                                <a href="serve-file.php?id=<?php echo $doc['id']; ?>&type=association" class="document-action" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; color: #64748b; padding: 40px 20px;">
                            <i class="fas fa-file-alt" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                            <p>No documents uploaded yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Account Information -->
                <div class="sidebar-section">
                    <h4 class="sidebar-title">
                        <i class="fas fa-user"></i>
                        Account Info
                    </h4>
                    <div class="info-item" style="margin-bottom: 12px;">
                        <span class="info-label">User ID</span>
                        <span class="info-value"><?php echo htmlspecialchars($association['user_id']); ?></span>
                    </div>
                    <div class="info-item" style="margin-bottom: 12px;">
                        <span class="info-label">SYC ID</span>
                        <span class="info-value"><?php echo htmlspecialchars($association['syc_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">User Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($association['user_email']); ?></span>
                    </div>
                </div>

                <!-- Audit Log -->
                <div class="sidebar-section">
                    <h4 class="sidebar-title">
                        <i class="fas fa-history"></i>
                        Activity Log
                    </h4>
                    <?php if (!empty($audit_log)): ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($audit_log as $log): ?>
                        <div class="audit-item">
                            <div class="audit-action">
                                <?php echo ucfirst($log['action']); ?>
                                <?php if ($log['old_status'] && $log['new_status']): ?>
                                    (<?php echo ucfirst($log['old_status']); ?> → <?php echo ucfirst($log['new_status']); ?>)
                                <?php endif; ?>
                            </div>
                            <div class="audit-meta">
                                <?php echo htmlspecialchars($log['admin_username'] ?: 'System'); ?> •
                                <?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?>
                            </div>
                            <?php if (!empty($log['notes'])): ?>
                            <div class="audit-notes"><?php echo htmlspecialchars($log['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color: #64748b; font-size: 14px; text-align: center; padding: 20px;">
                        No activity recorded yet.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div class="modal" id="actionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>

            <form id="actionForm" method="POST" action="association_action.php">
                <input type="hidden" name="association_id" value="<?php echo $association_id; ?>">
                <input type="hidden" name="action" id="actionInput">
                <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(32)); ?>">

                <div class="form-group">
                    <label class="form-label" for="notes">Notes (Optional)</label>
                    <textarea class="form-textarea" id="notes" name="notes" rows="4" placeholder="Add any notes or comments about this decision..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showActionModal(action) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('modalTitle');
            const actionInput = document.getElementById('actionInput');
            const confirmBtn = document.getElementById('confirmBtn');

            actionInput.value = action;

            if (action === 'approve') {
                modalTitle.textContent = 'Approve Association';
                confirmBtn.textContent = 'Approve';
                confirmBtn.className = 'btn btn-success';
            } else {
                modalTitle.textContent = 'Reject Association';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn btn-danger';
            }

            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('actionModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
