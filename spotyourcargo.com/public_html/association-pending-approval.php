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

// Check if user is an association
if ($_SESSION['user_type'] !== 'association') {
    header('Location: access.php');
    exit();
}

// Get association data
$user_id = $_SESSION['user_id'];
$association_stmt = $pdo->prepare("
    SELECT a.*, u.email as user_email
    FROM associations a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.user_id = ?
");
$association_stmt->execute([$user_id]);
$association = $association_stmt->fetch(PDO::FETCH_ASSOC);

if (!$association) {
    // No association record found, redirect to registration
    header('Location: association-registration.php');
    exit();
}

// Get association documents
$documents_stmt = $pdo->prepare("SELECT * FROM association_documents WHERE association_id = ?");
$documents_stmt->execute([$association['id']]);
$documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine status message and actions
$status_info = [];
switch ($association['registration_status']) {
    case 'pending':
        $status_info = [
            'title' => 'Registration Pending Approval',
            'message' => 'Your association registration has been submitted and is currently under review. You will receive an email notification once the review is complete.',
            'icon' => 'fas fa-clock',
            'color' => 'warning',
            'progress' => 50,
            'can_edit' => false
        ];
        break;
    case 'manual_review':
        $status_info = [
            'title' => 'Manual Review Required',
            'message' => 'Your registration requires additional review. Our team will contact you if we need more information.',
            'icon' => 'fas fa-user-check',
            'color' => 'info',
            'progress' => 75,
            'can_edit' => false
        ];
        break;
    case 'approved':
        // This shouldn't happen as approved associations are redirected to dashboard
        header('Location: association-dashboard.php');
        exit();
    case 'rejected':
        $status_info = [
            'title' => 'Registration Rejected',
            'message' => 'Your association registration was not approved. Please contact support for more information.',
            'icon' => 'fas fa-times-circle',
            'color' => 'danger',
            'progress' => 100,
            'can_edit' => true
        ];
        break;
    default:
        $status_info = [
            'title' => 'Unknown Status',
            'message' => 'There was an issue with your registration status. Please contact support.',
            'icon' => 'fas fa-question-circle',
            'color' => 'secondary',
            'progress' => 0,
            'can_edit' => false
        ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Association Registration Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --accent-blue: #2563EB;
            --light-gray: #F8FAFC;
            --medium-gray: #E2E8F0;
            --dark-gray: #334155;
            --white: #FFFFFF;
            --success-green: #10B981;
            --warning-orange: #F59E0B;
            --danger-red: #EF4444;
            --info-blue: #3B82F6;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--light-gray) 0%, #E0E7FF 100%);
            color: var(--dark-gray);
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--medium-gray);
            border-top: 4px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 60px 0 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .header-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -0.025em;
        }

        .header p {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            opacity: 0.9;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: -40px auto 60px;
            padding: 0 20px;
        }

        /* Back Navigation */
        .back-nav {
            margin-bottom: 32px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            padding: 12px 20px;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--medium-gray);
        }

        .back-link:hover {
            transform: translateX(-4px);
            box-shadow: var(--shadow-md);
            color: var(--secondary-blue);
        }

        .back-link i {
            font-size: 18px;
        }

        /* Status Card */
        .status-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            margin-bottom: 32px;
            border: 1px solid var(--medium-gray);
        }

        /* Status Header */
        .status-header {
            position: relative;
            overflow: hidden;
        }

        .status-header.warning {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            color: #92400E;
        }

        .status-header.info {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            color: #1E40AF;
        }

        .status-header.danger {
            background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
            color: #991B1B;
        }

        .status-header.secondary {
            background: linear-gradient(135deg, var(--medium-gray) 0%, #CBD5E1 100%);
            color: var(--dark-gray);
        }

        .status-content {
            padding: 48px 40px;
            text-align: center;
            position: relative;
        }

        .status-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .status-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: -0.025em;
        }

        .status-message {
            font-size: 18px;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto 32px;
            opacity: 0.9;
        }

        /* Progress Bar */
        .progress-container {
            max-width: 400px;
            margin: 0 auto;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-fill {
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }

        .progress-text {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.8;
        }

        /* Content Sections */
        .content-section {
            padding: 48px 40px;
            border-top: 1px solid var(--medium-gray);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .section-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-blue);
            margin: 0;
        }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }

        .detail-card {
            background: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 24px;
            border: 1px solid var(--medium-gray);
            transition: var(--transition);
        }

        .detail-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .detail-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-gray);
            line-height: 1.4;
        }

        /* Documents Section */
        .documents-grid {
            display: grid;
            gap: 16px;
        }

        .document-card {
            background: var(--white);
            border: 2px solid var(--medium-gray);
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
            cursor: pointer;
        }

        .document-card:hover {
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .document-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #DC2626 0%, #EF4444 100%);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
        }

        .document-details h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-gray);
            margin: 0 0 4px 0;
        }

        .document-details p {
            font-size: 14px;
            color: #64748B;
            margin: 0;
        }

        .document-action {
            background: var(--primary-blue);
            color: var(--white);
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .document-action:hover {
            background: var(--secondary-blue);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            padding: 48px 40px;
            border-top: 1px solid var(--medium-gray);
            background: var(--light-gray);
        }

        .btn {
            padding: 16px 32px;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            min-width: 160px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 40px 0 30px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .container {
                margin: -20px auto 40px;
                padding: 0 16px;
            }

            .status-content {
                padding: 32px 24px;
            }

            .status-icon {
                width: 64px;
                height: 64px;
                font-size: 24px;
            }

            .status-title {
                font-size: 24px;
            }

            .status-message {
                font-size: 16px;
            }

            .content-section {
                padding: 32px 24px;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .action-buttons {
                flex-direction: column;
                padding: 32px 24px;
            }

            .btn {
                width: 100%;
            }

            .document-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .document-action {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .status-content {
                padding: 24px 16px;
            }

            .content-section {
                padding: 24px 16px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .section-title {
                font-size: 20px;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .fade-in-up:nth-child(1) { animation-delay: 0.1s; }
        .fade-in-up:nth-child(2) { animation-delay: 0.2s; }
        .fade-in-up:nth-child(3) { animation-delay: 0.3s; }
        .fade-in-up:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <h1>Registration Status</h1>
            <p>Track your association registration progress</p>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Back Navigation -->
        <nav class="back-nav">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
        </nav>

        <!-- Status Card -->
        <div class="status-card fade-in-up">
            <!-- Status Header -->
            <div class="status-header <?php echo $status_info['color']; ?>">
                <div class="status-content">
                    <div class="status-icon">
                        <i class="<?php echo $status_info['icon']; ?>"></i>
                    </div>
                    <h2 class="status-title"><?php echo htmlspecialchars($status_info['title']); ?></h2>
                    <p class="status-message"><?php echo htmlspecialchars($status_info['message']); ?></p>

                    <?php if (isset($status_info['progress'])): ?>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $status_info['progress']; ?>%"></div>
                        </div>
                        <div class="progress-text"><?php echo $status_info['progress']; ?>% Complete</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Registration Details -->
            <div class="content-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h3 class="section-title">Registration Details</h3>
                </div>

                <div class="details-grid">
                    <div class="detail-card">
                        <span class="detail-label">Association Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['name']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">License Number</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['license_number']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">Region</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['region']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">Number of Trucks</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['number_of_trucks']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">Contact Person</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['contact_person']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">Contact Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['phone']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">Association Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($association['email']); ?></span>
                    </div>

                    <div class="detail-card">
                        <span class="detail-label">Registration Date</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($association['created_at'])); ?></span>
                    </div>
                </div>

                <?php if (!empty($documents)): ?>
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="section-title">Submitted Documents</h3>
                </div>

                <div class="documents-grid">
                    <?php foreach ($documents as $doc): ?>
                    <div class="document-card">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-details">
                                <h4><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))); ?></h4>
                                <p><?php echo htmlspecialchars($doc['original_filename']); ?></p>
                            </div>
                        </div>
                        <a href="serve-file.php?id=<?php echo $doc['id']; ?>&type=association" class="document-action" target="_blank">
                            <i class="fas fa-eye"></i>
                            View Document
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($status_info['can_edit']): ?>
                <a href="association-registration.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Update Registration
                </a>
                <?php endif; ?>

                <a href="mailto:support@spotyourcargo.com?subject=Association Registration Inquiry" class="btn btn-outline">
                    <i class="fas fa-envelope"></i>
                    Contact Support
                </a>
            </div>
        </div>
    </div>

    <script>
        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 500);
        });

        // Auto-refresh status every 30 seconds for pending registrations
        <?php if ($association['registration_status'] === 'pending' || $association['registration_status'] === 'manual_review'): ?>
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        <?php endif; ?>

        // Add fade-in animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.detail-card, .document-card');
            cards.forEach((card, index) => {
                card.classList.add('fade-in-up');
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
