<?php
// Database configuration for both local (XAMPP) and live (DirectAdmin)
// Detect environment (same pattern as session_config.php)
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = ($host === 'localhost' || preg_match('/\.local$/i', $host)) || php_sapi_name() === 'cli';

// DEBUG: Log which config is being used
error_log("DEBUG db.php: HTTP_HOST = '$host', isLocalhost = " . ($isLocalhost ? 'true' : 'false'));

if ($isLocalhost) {
    // XAMPP local configuration
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'syc_db');  // Your current XAMPP database name
    define('DB_USER', 'root');    // XAMPP default username
    define('DB_PASS', '');        // XAMPP default password (usually empty)
    define('DB_CHARSET', 'utf8mb4');
    error_log("DEBUG db.php: Using LOCAL config - DB_NAME: " . DB_NAME);
} else {
    // Live server configuration (DirectAdmin)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'spotyojn_syc_database');
    define('DB_USER', 'spotyojn_syc_database');
    define('DB_PASS', '12348765');
    define('DB_CHARSET', 'utf8mb4');
    error_log("DEBUG db.php: Using LIVE config - DB_NAME: " . DB_NAME);
}

try {
    error_log("DEBUG db.php: Attempting connection to " . DB_HOST . " with user " . DB_USER);
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    error_log("DEBUG db.php: Database connection successful");
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());

    // Show detailed error only on localhost for security
    if ($isLocalhost) {
        die("Database connection error: " . $e->getMessage());
    } else {
        // TEMPORARY: Show detailed error on live for debugging
        die("Database connection error: " . $e->getMessage());
    }
}
?>