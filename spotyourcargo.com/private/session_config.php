<?php
// private/session_config.php - Session configuration
// Must be included before any session_start() calls

// Detect environment
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = $host === 'localhost' || preg_match('/\.local$/i', $host);
$cookieDomain = '';

// If using a custom local domain like spotyourcargo.local, set cookie domain accordingly
if (!$isLocalhost && $host) {
    // Extract main domain for subdomains (e.g., admin.spotyourcargo.com -> .spotyourcargo.com)
    $hostWithoutWww = preg_replace('/^www\./i', '', $host);
    $parts = explode('.', $hostWithoutWww);
    if (count($parts) > 2) {
        // Has subdomain, use the last two parts
        $cookieDomain = '.' . $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    } else {
        // No subdomain
        $cookieDomain = '.' . $hostWithoutWww;
    }
}

// Configure session lifetime (2 hours)
ini_set('session.gc_maxlifetime', '7200');
ini_set('session.cookie_lifetime', '7200');

// Secure cookie flags
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // Lax works better for normal navigation

// HTTPS only when HTTPS is enabled; allow HTTP otherwise
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('session.cookie_secure', '0');
}

// Apply cookie domain if available
if ($cookieDomain) {
    // Use session_set_cookie_params to set domain reliably
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => !$isLocalhost,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
?>
