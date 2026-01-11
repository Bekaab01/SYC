<?php
// Admin Login Page
// Include session configuration
include_once __DIR__ . '/../../spotyourcargo.com/private/session_config.php';

// Start session
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Hardcoded credentials
    $admin_email = 'admin@spotyourcargo.com';
    $admin_password = '@SYCADMIN0464';

    if ($email === $admin_email && $password === $admin_password) {
        // Login successful
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['last_activity'] = time();
        header('Location: index.php');
        exit;
    } else {
        // Login failed
        $error = 'Invalid email or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --light-gray: #F5F5F5;
            --dark-gray: #333333;
            --white: #FFFFFF;
            --text-dark: #1d1d1f;
            --text-light: #86868b;
            --card-bg: rgba(255, 255, 255, 0.8);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo-section {
            margin-bottom: 30px;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .logo img {
            height: 40px;
            width: auto;
        }

        .logo-text {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 28px;
            letter-spacing: -0.5px;
        }

        .logo-dot {
            color: var(--primary-yellow);
        }

        .login-title {
            color: var(--primary-blue);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: var(--text-light);
            font-size: 16px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-blue);
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }

        .btn-login:hover {
            background: var(--secondary-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 51, 102, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            accent-color: var(--primary-blue);
        }

        .footer-text {
            margin-top: 20px;
            color: var(--text-light);
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 20px;
            }

            .logo img {
                height: 32px;
            }

            .logo-text {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">
                <img src="assets/img/SYC-Transparent.png" alt="SYC">
                <span class="logo-text">SYC<span class="logo-dot">.</span></span>
            </div>
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">Access the administrative dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required
                       placeholder="admin@spotyourcargo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                       placeholder="Enter your password">
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember Me</label>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login to Dashboard
            </button>
        </form>

        <div class="footer-text">
            <i class="fas fa-shield-alt"></i> Secure Admin Access Only
        </div>
    </div>
</body>
</html>
