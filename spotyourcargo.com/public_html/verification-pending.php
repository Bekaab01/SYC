<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$email = $_GET['email'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - SYC</title>
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
        
        .pending-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .email-icon {
            color: #FFD700;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #003366;
            margin-bottom: 20px;
        }
        
        p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .email-address {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 600;
            color: #003366;
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
            margin: 10px;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #003366;
            color: #003366;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,51,102,0.3);
        }
        
        .error-message {
            background: #ffe6e6;
            color: #e74c3c;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #e74c3c;
        }
    </style>
</head>
<body>
    <div class="pending-container">
        <div class="email-icon">✉️</div>
        <h1>Verify Your Email Address</h1>
        
        <?php if ($error === 'email_failed'): ?>
            <div class="error-message">
                We encountered an issue sending the verification email. You can request a new verification email from your dashboard.
            </div>
        <?php endif; ?>
        
        <p>We've sent a verification link to:</p>
        <div class="email-address"><?php echo htmlspecialchars($email); ?></div>
        <p>Please check your email and click the verification link to activate your account. The link will expire in 24 hours.</p>
        
        <p><strong>Didn't receive the email?</strong></p>
        <ul style="text-align: left; color: #666; margin-bottom: 20px;">
            <li>Check your spam or junk folder</li>
            <li>Make sure you entered the correct email address</li>
            <li>Wait a few minutes and try again</li>
        </ul>
        
        <div>
            <a href="access.php" class="btn">Back to Login</a>
            <a href="index.php" class="btn btn-outline">Return to Home</a>
        </div>
    </div>
</body>
</html>