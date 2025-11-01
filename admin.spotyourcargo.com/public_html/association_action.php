<?php
// Admin Association Action Handler - Secure POST endpoint

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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required parameters
if (!isset($_POST['association_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$association_id = (int)$_POST['association_id'];
$action = $_POST['action'];
$notes = trim($_POST['notes'] ?? '');

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// CSRF protection (basic implementation)
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || strlen($csrf_token) !== 64) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

include_once __DIR__ . '/../../spotyourcargo.com/private/db.php';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Fetch current association status
    $stmt = $pdo->prepare("SELECT registration_status, user_id, email, name FROM associations WHERE id = ?");
    $stmt->execute([$association_id]);
    $association = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$association) {
        throw new Exception('Association not found');
    }

    // Check if association is in pending status
    if ($association['registration_status'] !== 'pending') {
        throw new Exception('Association is not in pending status');
    }

    // Determine new status
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    $old_status = $association['registration_status'];

    // Update association status
    $update_stmt = $pdo->prepare("
        UPDATE associations
        SET registration_status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $update_stmt->execute([$new_status, $association_id]);

    // Log the action in audit table
    $audit_stmt = $pdo->prepare("
        INSERT INTO association_audit (
            association_id, admin_user_id, admin_username, action,
            old_status, new_status, notes, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $audit_stmt->execute([
        $association_id,
        $_SESSION['admin_user_id'] ?? null,
        $_SESSION['admin_username'] ?? 'Unknown Admin',
        $action,
        $old_status,
        $new_status,
        $notes,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    // Send email notification
    $email_sent = sendNotificationEmail($association, $action, $notes, $new_status);

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Association " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully",
        'email_sent' => $email_sent,
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Send notification email to association
 */
function sendNotificationEmail($association, $action, $notes, $new_status) {
    $to = $association['email'];
    $subject = "SYC Association Registration " . ucfirst($action) . "d";

    $message = "
    <html>
    <head>
        <title>{$subject}</title>
        <style>
            body { font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #003366; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .status { font-size: 18px; font-weight: bold; color: " . ($action === 'approve' ? '#2ecc71' : '#e74c3c') . "; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Spot Your Cargo</h1>
            <p>Association Registration Update</p>
        </div>

        <div class='content'>
            <h2>Dear {$association['name']},</h2>

            <p>Your association registration has been <span class='status'>" . ucfirst($action) . "d</span>.</p>

            <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid " . ($action === 'approve' ? '#2ecc71' : '#e74c3c') . ";'>
                <h3>Registration Details:</h3>
                <ul>
                    <li><strong>Association:</strong> {$association['name']}</li>
                    <li><strong>Status:</strong> " . ucfirst($new_status) . "</li>
                    <li><strong>Decision Date:</strong> " . date('F j, Y \a\t g:i A') . "</li>
                </ul>
            </div>";

    if ($action === 'approve') {
        $message .= "
            <p><strong>Congratulations!</strong> Your association has been approved and you can now:</p>
            <ul>
                <li>Access your association dashboard</li>
                <li>Manage drivers and trucks</li>
                <li>Accept transportation requests</li>
                <li>View available shipments</li>
            </ul>

            <p>Please log in to your account to start using the platform.</p>";
    } else {
        $message .= "
            <p>Unfortunately, your association registration was not approved at this time.</p>

            <p>If you believe this decision was made in error or would like to reapply, please contact our support team.</p>";
    }

    if (!empty($notes)) {
        $message .= "
            <div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                <h4>Admin Notes:</h4>
                <p>" . nl2br(htmlspecialchars($notes)) . "</p>
            </div>";
    }

    $message .= "
            <p>If you have any questions, please contact our support team at <a href='mailto:support@spotyourcargo.com'>support@spotyourcargo.com</a>.</p>

            <p>Best regards,<br>The Spot Your Cargo Team</p>
        </div>

        <div class='footer'>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; " . date('Y') . " Spot Your Cargo. All rights reserved.</p>
        </div>
    </body>
    </html>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Spot Your Cargo <noreply@spotyourcargo.com>',
        'Reply-To: support@spotyourcargo.com'
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}
?>
