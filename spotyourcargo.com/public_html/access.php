<?php
// TEMPORARY ERROR REPORTING FOR DEBUGGING HTTP 500 ERROR
// Remove this block once the issue is resolved
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Environment detection
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = $host === 'localhost' || preg_match('/\.local$/i', $host);

// Bootstrap sessions and DB with unified include paths working on localhost and live
require_once __DIR__ . '/../private/session_config.php';

// Start session (after applying config but before any output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent "headers already sent" by buffering
ob_start();

// Centralized PDO connection (uses private/.env or falls back to private/db.php)
require_once __DIR__ . '/../private/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("ERROR: PDO connection failed - pdo is not set or not PDO instance");
    die('Database connection failed. Please contact administrator.');
}

// Define dynamic app URL for emails and links
$appUrl = getenv('APP_URL');
if (!$appUrl) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $appUrl = $protocol . '://' . $host . $scriptDir;
}



// Check for URL parameters to auto-open signup form and select role
$auto_action = isset($_GET['action']) ? $_GET['action'] : '';
$auto_role = isset($_GET['role']) ? $_GET['role'] : '';

// Initialize error and success variables
$login_error = '';
$login_success = '';
$signup_error = '';

// Function to generate unique SYC ID
function generateSYCID($user_type, $pdo) {
    if ($user_type === 'association') {
        $prefix = 'SYC-A-';
    } elseif ($user_type === 'transitor') {
        $prefix = 'SYC-T-';
    } else {
        $prefix = 'SYC-S-'; // shipper (default)
    }

    // Get the highest existing ID for this user type
    $stmt = $pdo->prepare("SELECT syc_id FROM users WHERE syc_id LIKE ? ORDER BY syc_id DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->execute([$like_pattern]);
    $last_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last_user) {
        // Extract the number part and increment
        $last_number = intval(substr($last_user['syc_id'], strlen($prefix)));
        $new_number = $last_number + 1;
    } else {
        // First user of this type
        $new_number = 1;
    }

    // Format with leading zeros (4 digits)
    return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

if (!class_exists('BrevoSimpleEmail')) {
    include_once __DIR__ . '/../private/brevo_simple.php';
}

// function to handle login emails
function sendLoginEmail($user) {
    try {


        if (!class_exists('BrevoSimpleEmail')) {
            error_log("ERROR: BrevoSimpleEmail class not found after include");
            return;
        }

        $brevo = new BrevoSimpleEmail();
        $loginTime = date('F j, Y \a\t g:i A T');

        // Send login notification
        $result = $brevo->sendLoginNotification(
            $user['email'],
            $user['full_name'],
            $loginTime,
            $user['user_type'],
            $user['syc_id']
        );
        
        // Log email sending result
        if ($result['success']) {
            error_log("SUCCESS: Login notification sent to {$user['email']} - Message ID: " . $result['response']);
        } else {
            error_log("ERROR: Failed to send login notification to {$user['email']}: " . $result['error']);
        }
    } catch (Exception $e) {
        // Don't break login flow if email fails
        error_log("EXCEPTION: Login email error: " . $e->getMessage());
    }
}

// Function to send welcome email for new signups
function sendWelcomeEmail($user) {
    try {
        if (!class_exists('BrevoSimpleEmail')) {
            error_log("ERROR: BrevoSimpleEmail class not found for welcome email");
            return;
        }

        $brevo = new BrevoSimpleEmail();

        // Send welcome notification
        $result = $brevo->sendWelcomeEmail(
            $user['email'],
            $user['full_name'],
            $user['usertype'],
            $user['syc_id']
        );

        // Log email sending result
        if ($result['success']) {
            error_log("Welcome email sent to {$user['email']}");
        } else {
            error_log("Failed to send welcome email to {$user['email']}: " . $result['error']);
        }
    } catch (Exception $e) {
        // Don't break signup flow if email fails
        error_log("Welcome email error: " . $e->getMessage());
    }
}

// Function to generate verification token
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

// Function to send verification email
function sendVerificationEmail($user, $verificationUrl, $appUrl) {
    try {
        if (!class_exists('BrevoSimpleEmail')) {
            error_log("ERROR: BrevoSimpleEmail class not found for verification email");
            return false;
        }

        $brevo = new BrevoSimpleEmail();

        // Send verification email
        $result = $brevo->sendVerificationEmail(
            $user['email'],
            $user['full_name'],
            $verificationUrl,
            $appUrl
        );

        // Log email sending result
        if ($result['success']) {
            error_log("Verification email sent to {$user['email']}");
            return true;
        } else {
            error_log("Failed to send verification email to {$user['email']}: " . $result['error']);
            return false;
        }
    } catch (Exception $e) {
        error_log("Verification email error: " . $e->getMessage());
        return false;
    }
}

// Function to verify user email
function verifyUserEmail($pdo, $token) {
    try {
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE verification_token = ? AND email_verified = 0 LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Update user as verified
            $update_stmt = $pdo->prepare('UPDATE users SET email_verified = 1, verification_token = NULL, verified_at = ? WHERE id = ?');
            $update_stmt->execute([date('Y-m-d H:i:s'), $user['id']]);
            
            return [
                'success' => true,
                'email' => $user['email']
            ];
        }
        
        return ['success' => false, 'error' => 'Invalid or expired verification token'];
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $login_error = 'Password is required.';
    } else {
        // Check if user exists - ADD email_verified TO THE QUERY
        $stmt = $pdo->prepare('SELECT id, syc_id, full_name, email, password_hash, user_type, email_verified FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // ✅ UPDATED VERIFICATION CHECK WITH RESEND OPTION
            if (!$user['email_verified']) {
                $login_error = 'Please verify your email address before logging in. 
                               <div class="resend-verification-form">
                                   <form method="POST" action="access.php">
                                       <input type="hidden" name="resend_verification" value="1">
                                       <input type="hidden" name="email" value="' . htmlspecialchars($email) . '">
                                       <button type="submit" class="resend-verification-btn">
                                           Click here to resend verification email
                                       </button>
                                   </form>
                               </div>';
            } else {
                // Login successful - set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_syc_id'] = $user['syc_id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['email_verified'] = true;

                // Send login notification
                sendLoginEmail($user);

                // Redirect based on user type
                if ($user['user_type'] === 'association') {
                    // Check if association has completed registration
                    $association_check_stmt = $pdo->prepare("SELECT id, registration_status FROM associations WHERE user_id = ?");
                    $association_check_stmt->execute([$user['id']]);
                    $existing_association = $association_check_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_association) {
                        // Check association approval status
                        if ($existing_association['registration_status'] === 'pending') {
                            // Association registration pending approval
                            header('Location: association-pending-approval.php');
                        } elseif ($existing_association['registration_status'] === 'approved') {
                            // Association approved, go to dashboard
                            header('Location: association-dashboard.php');
                        } elseif ($existing_association['registration_status'] === 'rejected') {
                            // Association rejected, redirect to pending approval page to show status
                            header('Location: association-pending-approval.php');
                        } else {
                            // Unknown status, redirect to registration page
                            header('Location: association-registration.php');
                        }
                    } else {
                        // Association needs to complete registration
                        header('Location: association-registration.php');
                    }
                } else {
                    // Handle transitor login redirect
                    if ($user['user_type'] === 'transitor') {
                        header('Location: transitor-dashboard.php');
                    } else {
                        header('Location: shipper-dashboard.php');
                    }
                }
                exit();
            }
        } else {
            $login_error = 'Invalid email or password.';
        }
    }
}

        // Handle resend verification from login form
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
            $email = trim($_POST['email']);
            
            // Check if user exists and is not verified
            $stmt = $pdo->prepare('SELECT id, full_name, user_type, syc_id, email_verified FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !$user['email_verified']) {
                // Generate new verification token
                $verificationToken = generateVerificationToken();
                $verificationUrl = $appUrl . "/verify.php?token=" . $verificationToken;

                // Update token in database
                $token_stmt = $pdo->prepare('UPDATE users SET verification_token = ? WHERE email = ?');
                $token_stmt->execute([$verificationToken, $email]);

                // Send verification email
                $verificationSent = sendVerificationEmail([
                    'email' => $email,
                    'full_name' => $user['full_name'],
                    'user_type' => $user['user_type'],
                    'syc_id' => $user['syc_id']
                ], $verificationUrl, $appUrl);
                
                if ($verificationSent) {
                    $login_success = 'Verification email sent! Please check your inbox and spam folder.';
                } else {
                    $login_error = 'Failed to send verification email. Please try again.';
                }
            } else {
                $login_error = 'Email not found or already verified.';
            }
            
            // Make sure login form stays active
            $login_active = 'active';
            $signup_active = '';
        }


// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $full_name = trim($first_name . ' ' . $last_name);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $company_contact_name = trim($_POST['company_contact_name'] ?? '');
    $company_contact_email = trim($_POST['company_contact_email'] ?? '');

    // Validation (keep your existing validation)
    if (empty($first_name)) {
        $signup_error = 'First name is required.';
    } elseif (empty($last_name)) {
        $signup_error = 'Last name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $signup_error = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $signup_error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $signup_error = 'Passwords do not match.';
    } elseif (empty($user_type) || !in_array($user_type, ['shipper', 'transitor', 'association'])) {
        $signup_error = 'Please select an account type.';
    } elseif (!empty($company_contact_email) && !filter_var($company_contact_email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = 'Please enter a valid company contact email address.';
    } else {
        // Check if email already exists
        $check_stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check_stmt->execute([$email]);
        
        if ($check_stmt->fetch()) {
            $signup_error = 'Email address is already registered.';
        } else {
            // Generate unique SYC ID and hash password
            $syc_id = generateSYCID($user_type, $pdo);


            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');

            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Use default company name if not provided
                $final_company_name = !empty($company_name) ? $company_name : $full_name;
                $final_contact_name = !empty($company_contact_name) ? $company_contact_name : $full_name;
                $final_contact_email = !empty($company_contact_email) ? $company_contact_email : $email;
                
                // Insert new user into users table WITH COMPANY DATA
                $userSql = "INSERT INTO users (syc_id, first_name, last_name, full_name, email, username, password_hash, user_type, company_name, company_contact_name, company_contact_email, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $userStmt = $pdo->prepare($userSql);
                $userStmt->execute([
                    $syc_id,
                    $first_name,
                    $last_name,
                    $full_name,
                    $email,
                    $email,
                    $password_hash,
                    $user_type,
                    $final_company_name,
                    $final_contact_name,
                    $final_contact_email,
                    $created_at
                ]);

                // Get the inserted user's id
                $user_id = $pdo->lastInsertId();

                // Insert into the appropriate table based on user type
                if ($user_type === 'shipper') {
                    // Insert into shippers table
                    $shipperSql = "INSERT INTO shippers (user_id, syc_id, company_name, email, company_contact_name, company_contact_email, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $shipperStmt = $pdo->prepare($shipperSql);
                    $shipperStmt->execute([
                        $user_id,
                        $syc_id,
                        $final_company_name,
                        $email,
                        $final_contact_name,
                        $final_contact_email,
                        $created_at
                    ]);
                } elseif ($user_type === 'transitor') {
                    // Insert into transitors table
                    $transitorSql = "INSERT INTO transitors (user_id, syc_id, company_name, email, company_contact_name, company_contact_email, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $transitorStmt = $pdo->prepare($transitorSql);
                    $transitorStmt->execute([
                        $user_id,
                        $syc_id,
                        $final_company_name,
                        $email,
                        $final_contact_name,
                        $final_contact_email,
                        $created_at
                    ]);
                } elseif ($user_type === 'association') {
                    // Association registration will be completed later via association-registration.php
                    // No insertion into associations table here
                }
                
                // Commit transaction
                $pdo->commit();

                // Generate verification token and send verification email
                $verificationToken = generateVerificationToken();
                $verificationUrl = $appUrl . "/verify.php?token=" . $verificationToken;

                // Store verification token in database
                $token_stmt = $pdo->prepare('UPDATE users SET verification_token = ? WHERE id = ?');
                $token_stmt->execute([$verificationToken, $user_id]);

                // Send verification email
                $verificationSent = sendVerificationEmail([
                    'email' => $email,
                    'full_name' => $full_name
                ], $verificationUrl, $appUrl);

                // Set session variables (but mark as unverified)
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_syc_id'] = $syc_id;
                $_SESSION['user_type'] = $user_type;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['email_verified'] = false;

                // Send welcome email
                sendWelcomeEmail([
                    'email' => $email,
                    'full_name' => $full_name,
                    'user_type' => $user_type,
                    'syc_id' => $syc_id
                ]);

                // Redirect to verification pending page
                if ($verificationSent) {
                    header('Location: verification-pending.php?email=' . urlencode($email));
                } else {
                    header('Location: verification-pending.php?email=' . urlencode($email) . '&error=email_failed');
                }
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                
                // Log the actual error for debugging
                error_log("Registration error: " . $e->getMessage());
                
                // Show user-friendly error message
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $signup_error = 'Email address is already registered.';
                } else {
                    $signup_error = 'Registration failed. Please try again. Error: ' . $e->getMessage();
                }
            }
        }
    }
}



// Determine which form should be active (with URL parameter support)
$login_active = '';
$signup_active = '';

// Check if we're coming from a form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $login_active = 'active';
        $signup_active = '';
    } elseif (isset($_POST['signup'])) {
        $login_active = '';
        $signup_active = 'active';
    }
} else {
    // Check for URL parameters to auto-open signup form
    if ($auto_action === 'signup' || $auto_action === 'register' || !empty($auto_role)) {
        $login_active = '';
        $signup_active = 'active';
    } else {
        // Default to login form on page load
        $login_active = 'active';
        $signup_active = '';
    }
}

// If there are errors or success messages, make sure the correct form is active
if ((!empty($login_error) || !empty($login_success)) && empty($signup_error)) {
    $login_active = 'active';
    $signup_active = '';
} elseif (!empty($signup_error) && empty($login_error) && empty($login_success)) {
    $login_active = '';
    $signup_active = 'active';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Spot Your Cargo | Login & Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --light-gray: #F5F5F5;
            --dark-gray: #333333;
            --white: #FFFFFF;
            --error-red: #e74c3c;
            --success-green: #2ecc71;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --text-dark: #1d1d1f;
            --text-light: #86868b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--white);
            color: var(--dark-gray);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Sticky Header */
        .access-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            transition: var(--transition);
        }
        
        .access-header.scrolled {
            padding: 15px 40px;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .access-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .access-logo-icon {
            height: 36px;
            width: auto;
            transition: height 0.3s ease;
        }
        
        .access-logo-text {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 24px;
            letter-spacing: -0.5px;
        }
        
        .access-logo-dot {
            color: var(--primary-yellow);
        }
        
        .access-home-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            padding: 8px 16px;
            border-radius: 6px;
        }
        
        .access-home-link:hover {
            background-color: var(--light-gray);
        }
        
        @media (max-width: 768px) {
            .access-header {
                padding: 15px 20px;
            }
            
            .access-logo-icon {
                height: 30px;
            }
            
            .access-logo-text {
                font-size: 20px;
            }
        }
        
        /* Add padding to body to account for fixed header */
        .auth-container {
            padding-top: 80px;
            display: flex;
            flex: 1;
            min-height: calc(100vh - 80px);
        }

        /* Left Side - Branding */
        .auth-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23FFD700" fill-opacity="0.1" d="M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,224C672,235,768,181,864,181.3C960,181,1056,235,1152,234.7C1248,235,1344,181,1392,154.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }
        
        .auth-left-content {
            max-width: 500px;
            z-index: 2;
        }
        
        .auth-left h2 {
            font-size: 36px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .auth-left p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .auth-features {
            margin-top: 40px;
        }
        
        .auth-feature {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .auth-feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: var(--white);
        }
        
        .auth-feature-text h4 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--white);
        }
        
        .auth-feature-text p {
            font-size: 14px;
            margin-bottom: 0;
            opacity: 0.8;
            color: var(--white);
        }
        
        /* Right Side - Forms */
        .auth-right {
            flex: 1;
            background-color: var(--white);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .auth-form-container {
            width: 100%;
            max-width: 450px;
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .auth-tab {
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
            opacity: 0.7;
            transition: var(--transition);
            position: relative;
        }
        
        .auth-tab.active {
            opacity: 1;
            color: var(--primary-blue);
        }
        
        .auth-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary-yellow);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-blue);
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background: var(--light-gray);
        }
        
        .form-group input:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 51, 102, 0.1);
            background: var(--white);
        }
        
        .password-input-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            opacity: 0.7;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--primary-blue);
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-meter {
            height: 5px;
            background-color: var(--light-gray);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
            border-radius: 3px;
        }
        
        .strength-text {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .user-type-selection {
            margin: 25px 0;
        }
        
        .user-type-title {
            font-weight: 500;
            color: var(--primary-blue);
            margin-bottom: 12px;
        }
        
        .user-type-options {
            display: flex;
            gap: 15px;
        }
        
        .user-type-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            background: var(--white);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .user-type-btn:hover {
            border-color: var(--primary-blue);
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        .user-type-btn.selected {
            border-color: var(--primary-yellow);
            background-color: rgba(255, 215, 0, 0.1);
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        .user-type-btn i {
            font-size: 24px;
            color: var(--primary-yellow);
            margin-bottom: 8px;
            display: block;
        }
        
        .user-type-btn span {
            font-weight: 500;
            color: var(--primary-blue);
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 51, 102, 0.2);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .btn-outline:hover {
            background: var(--primary-blue);
            color: var(--white);
        }
        
        .btn-loading {
            position: relative;
            color: transparent;
        }
        
        .btn-loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 215, 0, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-yellow);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .auth-links {
            margin-top: 20px;
            text-align: center;
        }
        
        .auth-links a {
            color: var(--primary-blue);
            text-decoration: none;
            transition: color 0.3s;
            font-weight: 500;
        }
        
        .auth-links a:hover {
            color: var(--secondary-blue);
            text-decoration: underline;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: var(--secondary-blue);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: var(--light-gray);
        }
        
        .divider span {
            padding: 0 15px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .legal-links {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: var(--text-light);
        }
        
        .legal-links a {
            color: var(--primary-blue);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .legal-links a:hover {
            color: var(--secondary-blue);
            text-decoration: underline;
        }
        
        .error-message {
            color: var(--error-red);
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        
        .form-group.error input {
            border-color: var(--error-red);
        }
        
        .form-group.error .error-message {
            display: block;
        }
        
        .global-error {
            background-color: #ffebee;
            color: var(--error-red);
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--error-red);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .resend-verification-form {
            margin-top: 10px;
        }

        .resend-verification-btn {
            background: none !important;
            border: none !important;
            color: #0066cc !important;
            text-decoration: underline !important;
            cursor: pointer !important;
            padding: 0 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            display: inline !important;
            width: auto !important;
        }

        .resend-verification-btn:hover {
            color: #004499 !important;
            text-decoration: none !important;
            background: none !important;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .global-success {
            background-color: #e8f5e9;
            color: var(--success-green);
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success-green);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .global-error i,
        .global-success i {
            font-size: 18px;
        }
        

        
        /* Responsive Design */
        @media (max-width: 987px) {
            .auth-container {
                flex-direction: column;
            }
            
            .auth-left {
                padding: 40px 20px;
                order: 2;
            }
            
            .auth-right {
                padding: 40px 20px;
                order: 1;
            }
            
            .user-type-options {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .access-header {
                padding: 15px 20px;
            }
            
            .access-logo-icon {
                height: 30px;
            }
            
            .access-logo-text {
                font-size: 20px;
            }
            
            .auth-left h2 {
                font-size: 28px;
            }
            
            .auth-left p {
                font-size: 16px;
            }
            
            .auth-tabs {
                flex-direction: column;
            }
            
            .auth-tab {
                text-align: center;
            }
            
            .floating-icon {
                display: none;
            }
            
            .modal-content {
                width: 95%;
                max-height: 90vh;
            }
            
            .modal-header {
                padding: 15px 20px;
            }
            
            .modal-body {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .auth-left {
                padding: 30px 15px;
            }
            
            .auth-right {
                padding: 30px 15px;
            }
            
            .auth-feature {
                flex-direction: column;
                text-align: center;
            }
            
            .auth-feature-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .modal-header h3 {
                font-size: 20px;
            }
            
            .modal-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body class="access-page">
    <!-- Sticky Header -->
    <header class="access-header" id="accessHeader">
        <a href="index.php" class="access-logo">
            <img src="assets/img/SYC-Transparent.png" alt="SYC" class="access-logo-icon">
            <span class="access-logo-text">SYC<span class="access-logo-dot">.</span></span>
        </a>
        <a href="index.php" class="access-home-link">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>

    </header>

    <!-- Auth Container -->
    <div class="auth-container">
        <!-- Left Side - Branding -->
        <div class="auth-left">
            <?php include 'assets/php/floating-animation.php'; ?>
            <div class="auth-left-content">
                <h2>Welcome to SYC</h2>
                <p>Your instant freight matching solution. Connect with reliable carriers or find cargo to transport.</p>
                
                <div class="auth-features">
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="auth-feature-text">
                            <h4>Instant Matching</h4>
                            <p>Connect with carriers or cargo owners in minutes</p>
                        </div>
                    </div>
                    
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="auth-feature-text">
                            <h4>Real-time Tracking</h4>
                            <p>Monitor your shipments every step of the way</p>
                        </div>
                    </div>
                    
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="auth-feature-text">
                            <h4>Secure Transactions</h4>
                            <p>All transactions are encrypted and protected</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Forms -->
        <div class="auth-right">
            <div class="auth-form-container">
                <div class="auth-tabs">
                    <div class="auth-tab <?php echo $login_active ? 'active' : ''; ?>" data-tab="login">Login</div>
                    <div class="auth-tab <?php echo $signup_active ? 'active' : ''; ?>" data-tab="signup">Sign Up</div>
                </div>
                
                <!-- Login Form -->
                <form class="auth-form <?php echo $login_active ? 'active' : ''; ?>" id="loginForm" method="POST" action="access.php">
                    <input type="hidden" name="login" value="1">
                    
                    <?php if (!empty($login_error)): ?>
                    <div class="global-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $login_error; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($login_success)): ?>
                    <div class="global-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $login_success; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="login-email">Email Address</label>
                        <input type="email" id="login-email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) && isset($_POST['login']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <div class="password-input-container">
                            <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" data-target="login-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="request-password-reset.php">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn">Login</button>
                    
                    <div class="auth-links">
                        <span>Don't have an account? <a href="#" class="switch-to-signup">Sign Up</a></span>
                    </div>
                </form>
                
                <!-- Signup Form -->
                <form class="auth-form <?php echo $signup_active ? 'active' : ''; ?>" id="signupForm" method="POST" action="access.php">
                    <input type="hidden" name="signup" value="1">
                    
                    <?php if (!empty($signup_error)): ?>
                    <div class="global-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($signup_error); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="signup-first-name">First Name</label>
                        <input type="text" id="signup-first-name" name="first_name" placeholder="Enter your first name" required value="<?php echo isset($_POST['first_name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="signup-last-name">Last Name</label>
                        <input type="text" id="signup-last-name" name="last_name" placeholder="Enter your last name" required value="<?php echo isset($_POST['last_name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="signup-email">Email Address</label>
                        <input type="email" id="signup-email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) && isset($_POST['signup']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="signup-password">Password</label>
                        <div class="password-input-container">
                            <input type="password" id="signup-password" name="password" placeholder="Create a password" required>
                            <button type="button" class="toggle-password" data-target="signup-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Password strength</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="signup-confirm-password">Confirm Password</label>
                        <div class="password-input-container">
                            <input type="password" id="signup-confirm-password" name="confirm_password" placeholder="Confirm your password" required>
                            <button type="button" class="toggle-password" data-target="signup-confirm-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="user-type-selection">
                        <div class="user-type-title">I am a:</div>
                        <div class="user-type-options">
                            <div class="user-type-btn <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'shipper') || $auto_role === 'shipper' ? 'selected' : ''; ?>" data-type="shipper">
                                <i class="fas fa-dolly"></i>
                                <span>Shipper</span>
                            </div>
                            <div class="user-type-btn <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'transitor') || $auto_role === 'transitor' ? 'selected' : ''; ?>" data-type="transitor">
                                <i class="fas fa-clipboard-check"></i>
                                <span>Transitor</span>
                            </div>
                            <div class="user-type-btn <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'association') || $auto_role === 'association' ? 'selected' : ''; ?>" data-type="association">
                                <i class="fas fa-users"></i>
                                <span>Association</span>
                            </div>
                        </div>
                        <input type="hidden" id="user-type-input" name="user_type" value="<?php echo isset($_POST['user_type']) ? htmlspecialchars($_POST['user_type']) : $auto_role; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="signup-company-name">Company Name (Optional)</label>
                        <input type="text" id="signup-company-name" name="company_name" placeholder="Enter your company name" value="<?php echo isset($_POST['company_name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="signup-company-contact-name">Company Contact Name (Optional)</label>
                        <input type="text" id="signup-company-contact-name" name="company_contact_name" placeholder="Enter company contact name" value="<?php echo isset($_POST['company_contact_name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['company_contact_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="signup-company-contact-email">Company Contact Email (Optional)</label>
                        <input type="email" id="signup-company-contact-email" name="company_contact_email" placeholder="Enter company contact email" value="<?php echo isset($_POST['company_contact_email']) && isset($_POST['signup']) ? htmlspecialchars($_POST['company_contact_email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="signup-phone">Phone Number (Optional)</label>
                        <input type="tel" id="signup-phone" name="phone" placeholder="Enter your phone number" value="<?php echo isset($_POST['phone']) && isset($_POST['signup']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>

                    <button type="submit" class="btn" id="createAccountBtn">Create Account</button>
                    
                    <div class="auth-links">
                        <span>Already have an account? <a href="#" class="switch-to-login">Login</a></span>
                    </div>
                    
                    <div class="legal-links">
                        By creating an account, you agree to our 
                        <a href="#" id="termsLink">Terms of Service</a> and 
                        <a href="#" id="privacyLink">Privacy Policy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'assets/php/modals.php'; ?>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('accessHeader');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Tab switching functionality
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding form
                document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
                document.getElementById(tabId + 'Form').classList.add('active');
            });
        });

        // Switch to signup form
        document.querySelectorAll('.switch-to-signup').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                document.querySelector('[data-tab="signup"]').classList.add('active');
                
                document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
                document.getElementById('signupForm').classList.add('active');
            });
        });

        // Switch to login form
        document.querySelectorAll('.switch-to-login').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                document.querySelector('[data-tab="login"]').classList.add('active');
                
                document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
                document.getElementById('loginForm').classList.add('active');
            });
        });

        // User type selection
        document.querySelectorAll('.user-type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.user-type-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('user-type-input').value = this.getAttribute('data-type');
            });
        });

        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password strength indicator
        const passwordInput = document.getElementById('signup-password');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Check password length
                if (password.length >= 8) strength += 25;
                
                // Check for lowercase letters
                if (/[a-z]/.test(password)) strength += 25;
                
                // Check for uppercase letters
                if (/[A-Z]/.test(password)) strength += 25;
                
                // Check for numbers and special characters
                if (/[0-9]/.test(password)) strength += 15;
                if (/[^A-Za-z0-9]/.test(password)) strength += 10;
                
                // Update strength meter
                strengthFill.style.width = strength + '%';
                
                // Update strength text and color
                if (strength < 50) {
                    strengthFill.style.backgroundColor = '#e74c3c';
                    strengthText.textContent = 'Weak password';
                } else if (strength < 75) {
                    strengthFill.style.backgroundColor = '#f39c12';
                    strengthText.textContent = 'Medium password';
                } else {
                    strengthFill.style.backgroundColor = '#2ecc71';
                    strengthText.textContent = 'Strong password';
                }
                
                if (password.length === 0) {
                    strengthText.textContent = 'Password strength';
                    strengthFill.style.width = '0%';
                }
            });
        }

        // Modal functionality
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Terms and Privacy modals
        document.getElementById('termsLink').addEventListener('click', function(e) {
            e.preventDefault();
            openModal('termsModal');
        });

        document.getElementById('privacyLink').addEventListener('click', function(e) {
            e.preventDefault();
            openModal('privacyModal');
        });

        // Close modals
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal.id);
            });
        });

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });



        // Form validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('signup-password').value;
            const confirmPassword = document.getElementById('signup-confirm-password').value;
            const userType = document.getElementById('user-type-input').value;
            const createAccountBtn = document.getElementById('createAccountBtn');
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                return;
            }
            
            if (!userType) {
                e.preventDefault();
                alert('Please select whether you are a carrier or shipper.');
                return;
            }

            // Show loading spinner on create account button
            createAccountBtn.classList.add('btn-loading');
            createAccountBtn.disabled = true;
        });

        // Auto-select user type if coming from homepage
        <?php if (!empty($auto_role)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const userTypeBtn = document.querySelector(`.user-type-btn[data-type="<?php echo $auto_role; ?>"]`);
                if (userTypeBtn) {
                    userTypeBtn.click();
                }
            });
        <?php endif; ?>

        // Pre-fill and lock transitor role if ?role=transitor is in URL
        <?php if ($auto_role === 'transitor'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Disable other user type buttons
                document.querySelectorAll('.user-type-btn').forEach(btn => {
                    if (btn.getAttribute('data-type') !== 'transitor') {
                        btn.style.opacity = '0.5';
                        btn.style.pointerEvents = 'none';
                    }
                });
                // Add visual indicator
                const transitorBtn = document.querySelector('.user-type-btn[data-type="transitor"]');
                if (transitorBtn) {
                    transitorBtn.style.borderColor = '#FFD700';
                    transitorBtn.style.backgroundColor = 'rgba(255, 215, 0, 0.1)';
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Flush output buffer
ob_end_flush();
?>