<?php
// Configure session timeout for logged-in users (10 days)
include_once __DIR__ . '/../private/session_config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to database
$db_path = __DIR__ . '/../private/db.php';
if (file_exists($db_path)) {
    include_once $db_path;
} else {
    die("Database configuration file not found.");
}

// Check if token is provided
$token = $_GET['token'] ?? '';
$verification_result = null;

if (!empty($token)) {
    // Include the verification function
    include_once __DIR__ . '/access.php';
    $verification_result = verifyUserEmail($pdo, $token);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - SYC</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana;
            background: linear-gradient(135deg, #003366 0%, #1E4D8F 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .verification-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .success-icon {
            color: #2ecc71;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .error-icon {
            color: #e74c3c;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #003366;
            margin-bottom: 20px;
        }
        
        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn {
            background: linear-gradient(135deg, #003366 0%, #1E4D8F 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,51,102,0.3);
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($verification_result && $verification_result['success']): ?>
            <div class="success-icon">✓</div>
            <h1>Email Verified Successfully!</h1>
            <p>Your email address <strong><?php echo htmlspecialchars($verification_result['email']); ?></strong> has been successfully verified. You can now access all features of your SYC account.</p>
            <a href="access.php" class="btn">Continue to Login</a>
        <?php elseif (!empty($token)): ?>
            <div class="error-icon">✗</div>
            <h1>Verification Failed</h1>
            <p>The verification link is invalid or has expired. Please try signing in to request a new verification email.</p>
            <a href="access.php" class="btn">Go to Login</a>
        <?php else: ?>
            <div class="error-icon">!</div>
            <h1>Invalid Verification Link</h1>
            <p>No verification token provided. Please check your email for the correct verification link.</p>
            <a href="access.php" class="btn">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>