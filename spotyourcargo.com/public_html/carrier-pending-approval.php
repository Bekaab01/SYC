<?php
// Configure session timeout for logged-in users (10 days)
ini_set('session.gc_maxlifetime', 864000); // 10 days in seconds
ini_set('session.cookie_lifetime', 864000); // Make cookies persistent for 10 days
session_start();

// Include database connection
include_once __DIR__ . '/../private/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: access.php');
    exit();
}

// Handle admin actions
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    try {
        if (isset($_POST['approve_carrier'])) {
            $carrier_id = $_POST['carrier_id'];
            $stmt = $pdo->prepare("UPDATE carriers SET status = 'verified', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$carrier_id]);
            $message = "Carrier approved successfully!";
        } elseif (isset($_POST['reject_carrier'])) {
            $carrier_id = $_POST['carrier_id'];
            $stmt = $pdo->prepare("UPDATE carriers SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$carrier_id]);
            $message = "Carrier rejected successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Determine which carrier to show
$carrier_id = null;
$viewing_carrier = null;
$is_admin_view = false;

if ($_SESSION['user_type'] === 'admin' && isset($_GET['carrier_id'])) {
    // Admin viewing a specific carrier
    $carrier_id = $_GET['carrier_id'];
    $is_admin_view = true;

    // Fetch carrier details
    $stmt = $pdo->prepare("
        SELECT c.*, u.email, u.first_name, u.last_name, u.phone
        FROM carriers c
        LEFT JOIN users u ON c.syc_id = u.syc_id
        WHERE c.id = ?
    ");
    $stmt->execute([$carrier_id]);
    $viewing_carrier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$viewing_carrier) {
        header('Location: index.php');
        exit();
    }
} else {
    // Regular carrier viewing their own status
    if ($_SESSION['user_type'] !== 'carrier') {
        header('Location: access.php');
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $carrier_check_stmt = $pdo->prepare("SELECT * FROM carriers WHERE user_id = ?");
    $carrier_check_stmt->execute([$user_id]);
    $viewing_carrier = $carrier_check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$viewing_carrier) {
        // Carrier hasn't completed registration yet
        header('Location: carrier-registration.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - <?php echo $is_admin_view ? 'Carrier Review' : 'Registration Submitted'; ?></title>
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
            --success-green: #2ecc71;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
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

        /* Header */
        .pending-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 40px 0;
            text-align: center;
        }

        .pending-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .pending-header p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Main Content */
        .pending-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .pending-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            text-align: center;
        }

        .card-header {
            background: var(--light-gray);
            padding: 30px;
            border-bottom: 1px solid #eee;
        }

        .status-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--secondary-blue) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }

        .card-header h2 {
            color: var(--primary-blue);
            font-size: 24px;
            margin-bottom: 10px;
        }

        .card-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .card-body {
            padding: 40px;
        }

        .pending-message {
            margin-bottom: 30px;
        }

        .pending-message h3 {
            color: var(--primary-blue);
            font-size: 20px;
            margin-bottom: 15px;
        }

        .pending-message p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .highlight-box {
            background: #e8f4fd;
            border: 1px solid #b3d9ff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .highlight-box h4 {
            color: var(--primary-blue);
            font-size: 18px;
            margin-bottom: 10px;
        }

        .highlight-box p {
            color: var(--dark-gray);
            margin: 0;
        }

        .timeline {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--light-gray);
            z-index: 1;
        }

        .timeline-step {
            background: white;
            border: 2px solid var(--light-gray);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
            font-weight: 600;
            color: var(--text-light);
        }

        .timeline-step.completed {
            background: var(--success-green);
            border-color: var(--success-green);
            color: white;
        }

        .timeline-step.current {
            background: var(--primary-yellow);
            border-color: var(--primary-yellow);
            color: var(--primary-blue);
        }

        .timeline-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-light);
        }

        .timeline-label {
            width: 33.33%;
            text-align: center;
        }

        .actions {
            margin-top: 40px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.2);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }

        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .pending-header {
                padding: 30px 0;
            }

            .pending-header h1 {
                font-size: 24px;
            }

            .pending-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .card-body {
                padding: 30px 20px;
            }

            .timeline {
                flex-direction: column;
                gap: 20px;
            }

            .timeline::before {
                width: 2px;
                height: 100%;
                left: 50%;
                top: 0;
                transform: translateX(-50%);
            }

            .timeline-step {
                margin: 0 auto;
            }

            .timeline-labels {
                flex-direction: column;
                gap: 40px;
                margin-top: 20px;
            }

            .actions {
                text-align: center;
            }

            .btn {
                display: block;
                margin: 10px auto;
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="pending-header">
        <h1><?php echo $is_admin_view ? 'Carrier Application Review' : 'Registration Submitted Successfully'; ?></h1>
        <p><?php echo $is_admin_view ? 'Review and approve or reject the carrier application below.' : 'Your carrier information has been received and is under review'; ?></p>
    </header>

    <!-- Main Content -->
    <div class="pending-container">
        <div class="pending-card">
            <div class="card-header">
                <div class="status-icon">
                    <?php if ($viewing_carrier['status'] === 'pending'): ?>
                        <i class="fas fa-clock"></i>
                    <?php elseif ($viewing_carrier['status'] === 'verified'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif ($viewing_carrier['status'] === 'rejected'): ?>
                        <i class="fas fa-times-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-info-circle"></i>
                    <?php endif; ?>
                </div>
                <h2>
                    <?php
                    switch ($viewing_carrier['status']) {
                        case 'pending':
                            echo 'Under Review';
                            break;
                        case 'verified':
                            echo 'Approved';
                            break;
                        case 'rejected':
                            echo 'Rejected';
                            break;
                        default:
                            echo 'Status Unknown';
                    }
                    ?>
                </h2>
                <p>
                    <?php
                    switch ($viewing_carrier['status']) {
                        case 'pending':
                            echo 'Your application is being processed by our team';
                            break;
                        case 'verified':
                            echo 'Your application has been approved. You can now access load opportunities.';
                            break;
                        case 'rejected':
                            echo 'Your application was rejected. Please contact support for more information.';
                            break;
                        default:
                            echo 'Please contact support for more information.';
                    }
                    ?>
                </p>
            </div>

            <div class="card-body">
                <?php if ($is_admin_view): ?>
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <h3>Carrier Details</h3>
                    <div style="text-align: left; max-width: 600px; margin: 0 auto;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div>
                                <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Basic Information</h4>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($viewing_carrier['company_name']); ?></p>
                                <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($viewing_carrier['contact_person'] ?? 'N/A'); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars(trim(($viewing_carrier['first_name'] ?? '') . ' ' . ($viewing_carrier['last_name'] ?? ''))); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($viewing_carrier['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($viewing_carrier['phone']); ?></p>
                                <p><strong>Status:</strong> <span style="color: <?php echo $viewing_carrier['status'] === 'verified' ? 'green' : ($viewing_carrier['status'] === 'rejected' ? 'red' : 'orange'); ?>"><?php echo ucfirst($viewing_carrier['status']); ?></span></p>
                            </div>
                            <div>
                                <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Business Details</h4>
                                <p><strong>DOT Number:</strong> <?php echo htmlspecialchars($viewing_carrier['dot_number'] ?? 'N/A'); ?></p>
                                <p><strong>MC Number:</strong> <?php echo htmlspecialchars($viewing_carrier['mc_number'] ?? 'N/A'); ?></p>
                                <p><strong>Fleet Size:</strong> <?php echo htmlspecialchars($viewing_carrier['fleet_size'] ?? 'N/A'); ?> trucks</p>
                                <p><strong>Years Experience:</strong> <?php echo htmlspecialchars($viewing_carrier['years_experience'] ?? 'N/A'); ?> years</p>
                                <p><strong>Tax ID:</strong> <?php echo htmlspecialchars($viewing_carrier['tax_id'] ?? 'N/A'); ?></p>
                                <p><strong>Business License:</strong> <?php echo htmlspecialchars($viewing_carrier['business_license'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Operations</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <p><strong>Equipment Types:</strong></p>
                                    <p style="margin-left: 10px;"><?php echo htmlspecialchars($viewing_carrier['equipment_types'] ?? 'N/A'); ?></p>
                                </div>
                                <div>
                                    <p><strong>Operating Areas:</strong></p>
                                    <p style="margin-left: 10px;"><?php echo htmlspecialchars($viewing_carrier['operating_areas'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Insurance Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <p><strong>Insurance Provider:</strong> <?php echo htmlspecialchars($viewing_carrier['insurance_provider'] ?? 'N/A'); ?></p>
                                    <p><strong>Policy Number:</strong> <?php echo htmlspecialchars($viewing_carrier['insurance_policy_number'] ?? 'N/A'); ?></p>
                                </div>
                                <div>
                                    <p><strong>Expiry Date:</strong> <?php echo htmlspecialchars($viewing_carrier['insurance_expiry'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Emergency Contact</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($viewing_carrier['emergency_contact_name'] ?? 'N/A'); ?></p>
                                </div>
                                <div>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($viewing_carrier['emergency_contact_phone'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Additional Contacts</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <p><strong>Company Contact:</strong> <?php echo htmlspecialchars($viewing_carrier['company_contact_name'] ?? 'N/A'); ?></p>
                                </div>
                                <div>
                                    <p><strong>Contact Email:</strong> <?php echo htmlspecialchars($viewing_carrier['company_contact_email'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--primary-blue); margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Registration Info</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <p><strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($viewing_carrier['created_at'])); ?></p>
                                </div>
                                <div>
                                    <p><strong>Last Updated:</strong> <?php echo date('M j, Y H:i', strtotime($viewing_carrier['updated_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="carrier_id" value="<?php echo htmlspecialchars($viewing_carrier['id']); ?>">
                        <?php if ($viewing_carrier['status'] === 'pending'): ?>
                            <button type="submit" name="approve_carrier" class="btn btn-primary" style="margin-right: 10px;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="reject_carrier" class="btn btn-danger">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        <?php else: ?>
                            <p>No actions available.</p>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="pending-message">
                        <h3>Thank you for submitting your carrier information!</h3>
                        <p>Our administrative team will review your application to ensure compliance with our safety and quality standards. This process helps maintain the highest level of service for all our users.</p>

                        <div class="highlight-box">
                            <h4><i class="fas fa-info-circle"></i> What happens next?</h4>
                            <p>You will receive an email notification within 24 hours regarding the status of your application. If approved, you'll gain immediate access to load opportunities and can start receiving shipment requests.</p>
                        </div>
                    </div>

                    <!-- Progress Timeline -->
                    <div class="timeline">
                        <div class="timeline-step <?php echo $viewing_carrier['status'] === 'pending' ? 'completed' : ($viewing_carrier['status'] === 'verified' ? 'completed' : ''); ?>">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-step <?php echo $viewing_carrier['status'] === 'pending' ? 'current' : ($viewing_carrier['status'] === 'verified' ? 'completed' : ''); ?>">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="timeline-step <?php echo $viewing_carrier['status'] === 'verified' ? 'current' : ''; ?>">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                    </div>

                    <div class="timeline-labels">
                        <div class="timeline-label">Registration Submitted</div>
                        <div class="timeline-label">Admin Review</div>
                        <div class="timeline-label">Approval & Access</div>
                    </div>

                    <div class="actions">
                        <a href="access.php?logout=1" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>

                    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: left;">
                        <h4 style="color: var(--primary-blue); margin-bottom: 10px;"><i class="fas fa-question-circle"></i> Need Help?</h4>
                        <p style="margin: 0; font-size: 14px; color: var(--text-light);">
                            If you have any questions about your application or need to update your information,
                            please contact our support team at <a href="mailto:help@spotyourcargo.com" style="color: var(--primary-blue);">help@spotyourcargo.com</a>
                            or call us at +251 997 459 695.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 5 minutes to check for approval status
        setTimeout(function() {
            // Check if user is still on this page and refresh
            if (!document.hidden) {
                location.reload();
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>
