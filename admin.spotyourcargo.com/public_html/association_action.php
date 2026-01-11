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
$action = $_POST['action'] ?? '';
$notes = trim($_POST['notes'] ?? '');
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

// Handle document actions
if (in_array($action, ['approve_document', 'reject_document'])) {
    if (!isset($_POST['document_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing document_id parameter']);
        exit;
    }

    $document_id = (int)$_POST['document_id'];

    // Validate action
    if (!in_array($action, ['approve_document', 'reject_document'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document action']);
        exit;
    }
} else {
    // Handle association actions
    if (!isset($_POST['association_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing association_id parameter']);
        exit;
    }

    $association_id = (int)$_POST['association_id'];

    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid association action']);
        exit;
    }
}

// CSRF protection (basic implementation)
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || strlen($csrf_token) !== 64) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

include_once __DIR__ . '/../../spotyourcargo.com/private/db.php';
include_once __DIR__ . '/../../spotyourcargo.com/private/brevo_simple.php';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Handle document actions
    if (in_array($action, ['approve_document', 'reject_document'])) {
        // Fetch document details
        $doc_stmt = $pdo->prepare("SELECT * FROM association_documents WHERE id = ?");
        $doc_stmt->execute([$document_id]);
        $document = $doc_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            throw new Exception('Document not found');
        }

        // Check if document is in pending status
        if (($document['status'] ?? 'pending') !== 'pending') {
            throw new Exception('Document is not in pending status');
        }

        // Determine new status
        $new_status = ($action === 'approve_document') ? 'approved' : 'rejected';

        // Update document status
        $update_stmt = $pdo->prepare("
            UPDATE association_documents
            SET status = ?, rejection_reason = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?
            WHERE id = ?
        ");
        $update_stmt->execute([
            $new_status,
            ($action === 'reject_document') ? $rejection_reason : null,
            $_SESSION['admin_user_id'] ?? null,
            $document_id
        ]);

        // Log the action in audit table
        $audit_stmt = $pdo->prepare("
            INSERT INTO association_audit (
                association_id, admin_user_id, admin_username, action,
                notes, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $audit_stmt->execute([
            $document['association_id'],
            $_SESSION['admin_user_id'] ?? null,
            $_SESSION['admin_username'] ?? 'Unknown Admin',
            $action,
            ($action === 'reject_document') ? $rejection_reason : 'Document approved',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => "Document " . ($action === 'approve_document' ? 'approved' : 'rejected') . " successfully",
            'new_status' => $new_status
        ]);

    } else {
        // Handle association actions
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

        // Send email notification using Brevo
        $brevo = new BrevoSimpleEmail();
        $email_sent = $brevo->sendAssociationNotification($association, $action, $notes, $new_status);

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => "Association " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully",
            'email_sent' => $email_sent,
            'new_status' => $new_status
        ]);
    }

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


?>
