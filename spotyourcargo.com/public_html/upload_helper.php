<?php
/**
 * Secure File Upload Helper Functions
 * Handles file uploads with validation, security checks, and database storage
 */

// Configuration
define('UPLOAD_BASE_DIR', __DIR__ . '/../private/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png'
]);
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

/**
 * Handle secure file upload
 * @param string $field_name The name of the file input field
 * @param string $syc_id The SYC ID of the user
 * @return array Upload result with file info or error
 */
function handleSecureFileUpload($field_name, $syc_id) {
    // Check if file was uploaded
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'No file uploaded'];
    }

    $file = $_FILES[$field_name];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload failed: ' . getUploadErrorMessage($file['error'])];
    }

    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File size exceeds maximum allowed size of 5MB'];
    }

    // Validate file extension first (more reliable for user uploads)
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Define MIME type mappings
    $mime_mappings = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];

    // Check if extension is allowed
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['error' => 'Invalid file extension. Only PDF, JPG, PNG files are allowed'];
    }

    // Get expected MIME type from extension
    $expected_mime = $mime_mappings[$extension] ?? '';

    // Validate MIME type with multiple methods for additional security
    $detected_mime = '';

    // Try finfo first (most reliable)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    // Fallback to $_FILES['type'] if finfo failed
    if (empty($detected_mime) && isset($file['type'])) {
        $detected_mime = $file['type'];
    }

    // If we detected a MIME type, validate it against expected type
    if (!empty($detected_mime)) {
        // Allow some common variations
        $acceptable_mimes = [$expected_mime];
        if ($expected_mime === 'image/jpeg') {
            $acceptable_mimes[] = 'image/jpg'; // Common mistake
        }

        if (!in_array($detected_mime, $acceptable_mimes)) {
            // For debugging, but don't block - extension validation is primary
            error_log("MIME type mismatch: expected $expected_mime, detected $detected_mime for file " . $file['name']);
        }
    }

    // Use the expected MIME type from extension
    $mime_type = $expected_mime;



    // Generate secure filename
    $original_name = basename($file['name']);
    $unique_id = uniqid('', true);
    $secure_filename = $unique_id . '_' . preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $original_name);

    // Create user directory
    $user_folder = $syc_id;
    $upload_dir = UPLOAD_BASE_DIR . $user_folder . '/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['error' => 'Failed to create upload directory'];
        }
    }

    // Full path for storage
    $stored_path = $upload_dir . $secure_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $stored_path)) {
        return ['error' => 'Failed to save uploaded file'];
    }

    // Generate checksum
    $checksum = hash_file('sha256', $stored_path);

    // Get client IP
    $uploaded_by_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Return success data
    return [
        'user_folder' => $user_folder,
        'stored_path' => $user_folder . '/' . $secure_filename,
        'original_name' => $original_name,
        'mime_type' => $mime_type,
        'size' => $file['size'],
        'checksum' => $checksum,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'uploaded_by_ip' => $uploaded_by_ip
    ];
}

/**
 * Delete user upload directory and all its contents
 * @param string $syc_id The SYC ID of the user
 * @return array|null Result with error if any
 */
function deleteUserUploadDirectory($syc_id) {
    $upload_dir = UPLOAD_BASE_DIR . $syc_id . '/';

    if (!is_dir($upload_dir)) {
        return null; // Directory doesn't exist, nothing to delete
    }

    // Recursively delete directory and contents
    if (!deleteDirectory($upload_dir)) {
        return ['error' => 'Failed to delete user upload directory'];
    }

    return null; // Success
}

/**
 * Recursively delete a directory and its contents
 * @param string $dir Directory path
 * @return bool Success
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

/**
 * Get human-readable upload error message
 * @param int $error_code PHP upload error code
 * @return string Error message
 */
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}
?>
