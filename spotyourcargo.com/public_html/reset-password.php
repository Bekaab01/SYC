<?php
// Start session first
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

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$valid_token = false;
$user_email = '';

// Debug: Log the token received
error_log("Password reset token received: " . $token);

// Validate token
if (!empty($token)) {
    // First, let's check if the token exists at all
    $check_stmt = $pdo->prepare('SELECT id, email, reset_token, reset_token_expiry FROM users WHERE reset_token = ? LIMIT 1');
    $check_stmt->execute([$token]);
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        error_log("Token found for user: " . $user['email']);
        error_log("Token expiry: " . $user['reset_token_expiry']);
        error_log("Current time: " . date('Y-m-d H:i:s'));
        
        // Check if token is expired
        $current_time = date('Y-m-d H:i:s');
        if (strtotime($user['reset_token_expiry']) > strtotime($current_time)) {
            $valid_token = true;
            $user_email = $user['email'];
            $user_id = $user['id'];
            error_log("Token is valid");
        } else {
            $error = 'Reset token has expired. Please request a new password reset.';
            error_log("Token expired");
        }
    } else {
        $error = 'Invalid reset token. Please request a new password reset.';
        error_log("Token not found in database");
    }
} else {
    $error = 'No reset token provided.';
    error_log("No token provided");
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Update password and clear reset token
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?');
        
        if ($stmt->execute([$password_hash, $user_id])) {
            $success = 'Your password has been reset successfully. You can now login with your new password.';
            $valid_token = false; // Token is now used and invalid
            error_log("Password reset successful for user: " . $user_email);
        } else {
            $error = 'Failed to reset password. Please try again.';
            error_log("Password reset failed for user: " . $user_email);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Spot Your Cargo</title>
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
        
        .password-input-container {
            position: relative;
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
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-meter {
            height: 5px;
            background-color: #f5f5f5;
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
            color: #666;
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
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
        
        .debug-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
            color: #666;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if (!$valid_token && empty($success)): ?>
            <div class="reset-icon">❌</div>
            <h1>Invalid Reset Link</h1>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
            
            <!-- Debug information -->
            <?php if (!empty($token)): ?>
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Token: <?php echo substr($token, 0, 20) . '...'; ?><br>
                Time: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            <?php endif; ?>
            
            <a href="request-password-reset.php" class="btn">Request New Reset Link</a>
            
        <?php elseif (!empty($success)): ?>
            <div class="reset-icon">✅</div>
            <h1>Password Reset Successful</h1>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <a href="access.php" class="btn">Login Now</a>
            
        <?php else: ?>
            <div class="reset-icon">🔑</div>
            <h1>Set New Password</h1>
            <p>Enter your new password for <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="reset-password.php?token=<?php echo urlencode($token); ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="8">
                        <button type="button" class="toggle-password" data-target="password">
                            👁️
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
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            👁️
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">Reset Password</button>
            </form>
            
            <a href="access.php" class="back-link">← Back to Login</a>
        <?php endif; ?>
    </div>

    <script>
        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                
                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = '🙈';
                } else {
                    input.type = 'password';
                    this.textContent = '👁️';
                }
            });
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 8) strength += 25;
                if (/[a-z]/.test(password)) strength += 25;
                if (/[A-Z]/.test(password)) strength += 25;
                if (/[0-9]/.test(password)) strength += 15;
                if (/[^A-Za-z0-9]/.test(password)) strength += 10;
                
                strengthFill.style.width = strength + '%';
                
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
    </script>
</body>
</html>