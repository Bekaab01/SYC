<?php
// Configure session timeout for logged-in users (10 days)
include_once __DIR__ . '/../private/session_config.php';

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect environment
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = ($host === 'localhost' || preg_match('/\.local$/i', $host)) || php_sapi_name() === 'cli';

// Connect to database
$db_path = __DIR__ . '/../private/db.php';
if (file_exists($db_path)) {
    include_once $db_path;
} else {
    die("Database configuration file not found.");
}

// Debug: Check if columns exist
try {
    $check_columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if ($check_columns->rowCount() === 0) {
        error_log("ERROR: reset_token column does not exist in users table");
        $error = 'System configuration error. Please contact administrator.';
    }
} catch (Exception $e) {
    error_log("ERROR checking columns: " . $e->getMessage());
}

// Include Brevo email functionality
$brevo_path = __DIR__ . '/../private/brevo_simple.php';
if (!file_exists($brevo_path)) {
    die("Email service configuration not found.");
}
include_once $brevo_path;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare('SELECT id, syc_id, full_name, email FROM users WHERE email = ? AND email_verified = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

            // Store token in database
            $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?');
            $stmt->execute([$reset_token, $token_expiry, $user['id']]);

            // Send reset email
            if ($isLocalhost) {
                $base_url = 'http://localhost/spotyourcargo.com';
            } else {
                $base_url = 'https://' . $host;
            }
            $reset_url = $base_url . '/reset-password.php?token=' . $reset_token;

            $brevo = new BrevoSimpleEmail();
            $email_sent = $brevo->sendPasswordResetEmail($user['email'], $user['full_name'], $reset_url);

            if ($email_sent['success']) {
                $message = 'Password reset instructions have been sent to your email. The link will expire in 1 hour.';
            } else {
                $error = 'Failed to send reset email. Please try again.';
            }
        } else {
            $error = 'No account found with this email address or email not verified.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Spot Your Cargo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #003366 0%, #1E4D8F 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .reset-icon {
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
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #003366;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f5f5f5;
        }

        .form-group input:focus {
            border-color: #003366;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 51, 102, 0.1);
            background: white;
        }

        .btn {
            background: linear-gradient(135deg, #003366 0%, #1E4D8F 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 51, 102, 0.2);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #003366;
            color: #003366;
            margin-top: 15px;
        }

        .btn-outline:hover {
            background: #003366;
            color: white;
        }

        .error-message {
            background: #ffe6e6;
            color: #e74c3c;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #e74c3c;
        }

        .success-message {
            background: #e8f5e9;
            color: #2ecc71;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #2ecc71;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #003366;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-icon">🔒</div>
        <h1>Reset Your Password</h1>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <a href="access.php" class="btn btn-outline">Back to Login</a>
        <?php else: ?>
            <p>Enter your email address and we'll send you a link to reset your password.</p>

            <form method="POST" action="request-password-reset.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <button type="submit" class="btn">Send Reset Link</button>
            </form>

            <a href="access.php" class="back-link">← Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
