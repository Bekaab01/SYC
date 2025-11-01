<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - SYC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --light-gray: #F5F5F5;
            --dark-gray: #333333;
            --white: #FFFFFF;
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
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-blue);
        }

        .header h1 {
            color: var(--primary-blue);
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            color: var(--dark-gray);
            font-size: 16px;
        }

        .content {
            background: var(--white);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .content h2 {
            color: var(--primary-blue);
            font-size: 24px;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
        }

        .content h3 {
            color: var(--primary-blue);
            font-size: 20px;
            margin: 25px 0 10px 0;
        }

        .content p {
            margin-bottom: 15px;
            line-height: 1.7;
        }

        .content ul {
            margin: 15px 0 20px 30px;
        }

        .content li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 25px;
            background: var(--primary-blue);
            color: var(--white);
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s;
        }

        .back-link:hover {
            background: var(--secondary-blue);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-contract"></i> Terms of Service</h1>
            <p>Last updated: <?php echo date('F j, Y'); ?></p>
        </div>

        <div class="content">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using the SYC (Spot Your Cargo) platform, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>

            <h2>2. User Responsibilities</h2>
            <p>Users are responsible for maintaining the confidentiality of their account and password and for restricting access to their computer. You agree to accept responsibility for all activities that occur under your account or password.</p>

            <h2>3. Service Modifications</h2>
            <p>SYC reserves the right to modify or discontinue, temporarily or permanently, the service with or without notice. You agree that SYC shall not be liable to you or to any third party for any modification, suspension or discontinuance of the service.</p>

            <h2>4. User Conduct</h2>
            <p>You agree not to use the service to:</p>
            <ul>
                <li>Post false or misleading information</li>
                <li>Violate any applicable laws or regulations</li>
                <li>Infringe upon the rights of others</li>
                <li>Transmit harmful or malicious code</li>
                <li>Attempt to gain unauthorized access to our systems</li>
            </ul>

            <h2>5. Limitation of Liability</h2>
            <p>SYC shall not be liable for any indirect, incidental, special, consequential or punitive damages resulting from your use of the service. In no event shall SYC's total liability to you for all damages exceed the amount paid by you for the service.</p>

            <h2>6. Governing Law</h2>
            <p>These terms shall be interpreted and governed by the laws of Ethiopia, without regard to conflict of law provisions. Any disputes arising from these terms shall be subject to the exclusive jurisdiction of the courts of Ethiopia.</p>

            <h2>7. Contact Information</h2>
            <p>If you have any questions about these Terms of Service, please contact us at support@syc.com.</p>
        </div>

        <a href="carrier-registration.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Registration
        </a>
    </div>
</body>
</html>
