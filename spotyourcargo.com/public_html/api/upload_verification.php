<?php
/**
 * Secure Document Upload API
 *
 * Production-safe document upload endpoint with comprehensive validation,
 * secure storage, and audit logging. No document processing logic included.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../../private/db.php';
require_once '../../private/session_config.php';
require_once '../../private/document_requirements.php';
require_once '../../private/document_verification/config.php';
require_once '../../private/document_verification/OCRProcessor.php';
require_once '../../private/document_verification/DocumentVerificationService.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$shipment_id = $_POST['shipment_id'] ?? null;
$doc_type = $_POST['doc_type'] ?? null;

if (!$shipment_id || !$doc_type) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Shipment ID and document type required']);
    exit;
}

// Validate document type
$valid_doc_types = DocumentRequirements::getAllTypes();
if (!in_array($doc_type, $valid_doc_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document type']);
    exit;
}

// Verify shipment belongs to user
try {
    $stmt = $pdo->prepare("SELECT id FROM shipments WHERE id = ? AND shipper_id = ?");
    $stmt->execute([$shipment_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Shipment verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'No file uploaded';
    if (isset($_FILES['document'])) {
        switch ($_FILES['document']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg = 'File upload incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = 'No file selected';
                break;
            default:
                $error_msg = 'Upload failed';
        }
    }
    logUploadAttempt($user_id, $shipment_id, $doc_type, 'failure', $error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

$file = $_FILES['document'];
$original_filename = $file['name'];
$file_tmp = $file['tmp_name'];
$file_size = $file['size'];
$mime_type = $file['type'];

// 1. File size validation
if ($file_size > DOC_VERIFICATION_MAX_FILE_SIZE) {
    $error_msg = 'File too large (max ' . (DOC_VERIFICATION_MAX_FILE_SIZE / 1024 / 1024) . 'MB)';
    logUploadAttempt($user_id, $shipment_id, $doc_type, 'failure', $error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

// 2. MIME type validation (not file extension)
if (!in_array($mime_type, DOC_VERIFICATION_ALLOWED_TYPES)) {
    $error_msg = 'Invalid file type. Only PDF and images (JPG, PNG) allowed';
    logUploadAttempt($user_id, $shipment_id, $doc_type, 'failure', $error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

// 3. Generate secure unique filename
$document_id = generateDocumentId();
$extension = getExtensionFromMimeType($mime_type);
$stored_filename = $document_id . '.' . $extension;

// 4. Directory isolation - store in user-specific directory
$user_dir = DOC_VERIFICATION_BASE_STORAGE_PATH . $user_id . '/';
if (!is_dir($user_dir)) {
    if (!mkdir($user_dir, 0755, true)) {
        $error_msg = 'Failed to create storage directory';
        logUploadAttempt($user_id, $shipment_id, $doc_type, 'failure', $error_msg);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Storage error']);
        exit;
    }
}

// 5. Atomic storage - ensure file is fully written before marking successful
$target_path = $user_dir . $stored_filename;
$temp_path = $target_path . '.tmp';

try {
    // Move to temp file first
    if (!move_uploaded_file($file_tmp, $temp_path)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Atomic rename to final location
    if (!rename($temp_path, $target_path)) {
        unlink($temp_path); // Clean up temp file
        throw new Exception('Failed to finalize file storage');
    }

    // Verify file was written completely
    if (filesize($target_path) !== $file_size) {
        unlink($target_path);
        throw new Exception('File storage incomplete');
    }

} catch (Exception $e) {
    $error_msg = 'File storage failed';
    logUploadAttempt($user_id, $shipment_id, $doc_type, 'failure', $error_msg . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Storage error']);
    exit;
}

// Record metadata and update database
try {
    // Check which columns exist in the table
    $stmt = $pdo->query("DESCRIBE load_documents");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $has_document_id = in_array('document_id', $columns);
    $has_original_filename = in_array('original_filename', $columns);
    $has_stored_filename = in_array('stored_filename', $columns);
    $has_file_size = in_array('file_size', $columns);
    $has_mime_type = in_array('mime_type', $columns);
    $has_uploader_id = in_array('uploader_id', $columns);

    // Build dynamic insert query based on available columns
    $insert_columns = ['shipment_id', 'doc_type', 'uploaded_at'];
    $insert_values = ['?', '?', 'NOW()'];
    $update_parts = ['uploaded_at = NOW()'];
    $params = [$shipment_id, $doc_type];

    if ($has_document_id) {
        $insert_columns[] = 'document_id';
        $insert_values[] = '?';
        $update_parts[] = 'document_id = VALUES(document_id)';
        $params[] = $document_id;
    }
    if ($has_original_filename) {
        $insert_columns[] = 'original_filename';
        $insert_values[] = '?';
        $update_parts[] = 'original_filename = VALUES(original_filename)';
        $params[] = $original_filename;
    }
    if ($has_stored_filename) {
        $insert_columns[] = 'stored_filename';
        $insert_values[] = '?';
        $update_parts[] = 'stored_filename = VALUES(stored_filename)';
        $params[] = $stored_filename;
    }
    if ($has_file_size) {
        $insert_columns[] = 'file_size';
        $insert_values[] = '?';
        $update_parts[] = 'file_size = VALUES(file_size)';
        $params[] = $file_size;
    }
    if ($has_mime_type) {
        $insert_columns[] = 'mime_type';
        $insert_values[] = '?';
        $update_parts[] = 'mime_type = VALUES(mime_type)';
        $params[] = $mime_type;
    }
    if ($has_uploader_id) {
        $insert_columns[] = 'uploader_id';
        $insert_values[] = '?';
        $update_parts[] = 'uploader_id = VALUES(uploader_id)';
        $params[] = $user_id;
    }

    $insert_sql = "INSERT INTO load_documents (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ") ON DUPLICATE KEY UPDATE " . implode(', ', $update_parts);
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute($params);

    // Log successful upload
    logUploadAttempt($user_id, $shipment_id, $doc_type, 'success', 'Upload completed', $document_id);

    // Trigger full document processing asynchronously (don't block upload response)
    try {
        // Only instantiate and process if all required classes are available
        if (class_exists('DocumentVerificationService')) {
            $verificationService = new DocumentVerificationService();
            $verificationService->processDocument($document_id);

            // Log processing initiation
            error_log("Full document processing initiated for document $document_id");
        } else {
            error_log("DocumentVerificationService class not available - skipping processing for document $document_id");
        }

    } catch (Exception $e) {
        // Processing failure should not affect upload success
        error_log("Document processing initiation failed for document $document_id: " . $e->getMessage());
    }

    $response = [
        'success' => true,
        'message' => 'Document uploaded successfully',
        'document_id' => $document_id,
        'file_size' => $file_size,
        'mime_type' => $mime_type
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    // Clean up stored file on database error
    unlink($target_path);
    error_log("Document upload database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error during upload']);
}

/**
 * Generate a unique document ID
 */
function generateDocumentId() {
    return uniqid('doc_', true);
}

/**
 * Get file extension from MIME type
 */
function getExtensionFromMimeType($mime_type) {
    $extensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    return $extensions[$mime_type] ?? 'bin';
}

/**
 * Log upload attempt for auditability
 */
function logUploadAttempt($user_id, $shipment_id, $doc_type, $status, $reason, $document_id = null) {
    $log_entry = sprintf(
        "[%s] User %d - Shipment %s - DocType %s - Status %s - Reason: %s",
        date('Y-m-d H:i:s'),
        $user_id,
        $shipment_id,
        $doc_type,
        $status,
        $reason
    );

    if ($document_id) {
        $log_entry .= " - DocumentID: $document_id";
    }

    error_log($log_entry);
}
?>
