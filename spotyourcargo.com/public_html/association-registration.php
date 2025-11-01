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

// Check if association already completed registration
$user_id = $_SESSION['user_id'];
$association_check_stmt = $pdo->prepare("SELECT id, registration_status FROM associations WHERE user_id = ?");
$association_check_stmt->execute([$user_id]);
$existing_association = $association_check_stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_association) {
    // Check association status and redirect accordingly
    if ($existing_association['registration_status'] === 'approved') {
        // Association is approved, redirect to dashboard
        header('Location: association-dashboard.php');
        exit();
    } elseif ($existing_association['registration_status'] === 'pending' || $existing_association['registration_status'] === 'manual_review') {
        // Association is pending approval, redirect to pending approval page
        header('Location: association-pending-approval.php');
        exit();
    } elseif ($existing_association['registration_status'] === 'rejected') {
        // Association was rejected, redirect to pending approval page to show status
        header('Location: association-pending-approval.php');
        exit();
    }
}

// Get user data for pre-filling form
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables
$registration_error = '';
$registration_success = '';

// Ethiopian regions for dropdown
$ethiopian_regions = [
    'Addis Ababa', 'Afar', 'Amhara', 'Benishangul-Gumuz', 'Dire Dawa',
    'Gambella', 'Harari', 'Oromia', 'Somali', 'Southern Nations, Nationalities, and Peoples\' Region (SNNPR)', 'Tigray'
];

// Handle file uploads securely
function handleFileUpload($file_input, $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'], $max_size = 5242880) { // 5MB
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$file_input];
    $file_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate file type
    if (!in_array($file_ext, $allowed_types)) {
        return ['error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        return ['error' => 'File too large. Maximum size: ' . ($max_size / 1024 / 1024) . 'MB'];
    }

    // Generate secure filename
    $secure_name = uniqid('association_' . $file_input . '_', true) . '.' . $file_ext;
    $upload_dir = __DIR__ . '/../private/uploads/associations/';
    $upload_path = $upload_dir . $secure_name;

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'path' => 'private/uploads/associations/' . $secure_name,
            'original_name' => $file_name
        ];
    } else {
        return ['error' => 'Failed to upload file'];
    }
}

// Handle association registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_registration'])) {
    // Common validation
    $association_name = trim($_POST['association_name'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $association_email = trim($_POST['association_email'] ?? '');
    $number_of_trucks = (int)($_POST['number_of_trucks'] ?? 0);

    if (empty($association_name) || empty($license_number) || empty($region) || empty($contact_person) || empty($contact_phone) || empty($association_email) || $number_of_trucks <= 0) {
        $registration_error = 'Please fill in all required fields.';
    } elseif (!filter_var($association_email, FILTER_VALIDATE_EMAIL)) {
        $registration_error = 'Please enter a valid email address.';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Generate SYC association ID
            $syc_id = 'SYC-A-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

            // Initialize association data
            $association_data = [
                'user_id' => $user_id,
                'syc_id' => $syc_id,
                'name' => $association_name,
                'license_number' => $license_number,
                'region' => $region,
                'contact_person' => $contact_person,
                'phone' => $contact_phone,
                'email' => $association_email,
                'number_of_trucks' => $number_of_trucks,
                'registration_status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Handle file uploads
            $business_license = handleFileUpload('business_license');
            $tin_certificate = handleFileUpload('tin_certificate');
            $association_membership = handleFileUpload('association_membership');

            if (isset($business_license['error'])) {
                $registration_error = 'Business License: ' . $business_license['error'];
            } elseif (isset($tin_certificate['error'])) {
                $registration_error = 'TIN Certificate: ' . $tin_certificate['error'];
            } elseif (isset($association_membership['error'])) {
                $registration_error = 'Association Membership: ' . $association_membership['error'];
            } else {
                // Insert association data
                $columns = implode(', ', array_keys($association_data));
                $placeholders = str_repeat('?, ', count($association_data) - 1) . '?';

                $association_sql = "INSERT INTO associations ($columns) VALUES ($placeholders)
                                   ON DUPLICATE KEY UPDATE " .
                                   implode(', ', array_map(function($col) { return "$col = VALUES($col)"; }, array_keys($association_data)));

                $association_stmt = $pdo->prepare($association_sql);
                $association_stmt->execute(array_values($association_data));

                $association_id = $pdo->lastInsertId();

                // Insert association documents
                if ($business_license) {
                    $pdo->prepare("INSERT INTO association_documents (association_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$association_id, 'business_license', $business_license['path'], $business_license['original_name']]);
                }
                if ($tin_certificate) {
                    $pdo->prepare("INSERT INTO association_documents (association_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$association_id, 'tin_certificate', $tin_certificate['path'], $tin_certificate['original_name']]);
                }
                if ($association_membership) {
                    $pdo->prepare("INSERT INTO association_documents (association_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)")->execute([$association_id, 'association_membership', $association_membership['path'], $association_membership['original_name']]);
                }

                // Commit transaction
                $pdo->commit();

                // Update session to indicate registration is complete
                $_SESSION['association_registered'] = true;

                // Redirect to pending approval page
                header('Location: association-pending-approval.php');
                exit();
            }

        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();

            error_log("Association registration error: " . $e->getMessage());
            $registration_error = 'Registration failed. Please try again. Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC - Complete Association Registration</title>
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
            --error-red: #e74c3c;
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
        .registration-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 30px 0;
            text-align: center;
        }

        .registration-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .registration-header p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Progress Indicator */
        .progress-container {
            max-width: 800px;
            margin: -20px auto 0;
            padding: 0 20px;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.2);
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            background: var(--primary-yellow);
            height: 100%;
            width: 50%;
            transition: width 0.3s ease;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .progress-step {
            text-align: center;
            flex: 1;
        }

        .progress-step.completed .step-number {
            background: var(--primary-yellow);
            color: var(--primary-blue);
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .step-label {
            font-size: 12px;
            opacity: 0.8;
        }

        /* Main Content */
        .registration-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .card-header {
            background: var(--light-gray);
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
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
            padding: 30px;
        }

        /* Form Styles */
        .registration-form {
            display: grid;
            gap: 25px;
        }

        .form-section {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-yellow);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-blue);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #ffebee;
            color: var(--error-red);
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e9;
            color: var(--success-green);
            border: 1px solid #c8e6c9;
        }

        .alert i {
            font-size: 18px;
        }

        /* Required Field Indicator */
        .required {
            color: var(--error-red);
        }

        /* File hints */
        .file-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .registration-header {
                padding: 20px 0;
            }

            .registration-header h1 {
                font-size: 24px;
            }

            .registration-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .card-body {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .progress-steps {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="registration-header">
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-steps">
                <div class="progress-step completed">
                    <div class="step-number">1</div>
                    <div class="step-label">Account Created</div>
                </div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div class="step-label">Association Details</div>
                </div>
            </div>
        </div>
        <h1>Complete Your Association Registration</h1>
        <p>Please provide your truck association information to start managing your fleet</p>
    </header>

    <!-- Main Content -->
    <div class="registration-container">
        <div class="registration-card">
            <div class="card-header">
                <h2><i class="fas fa-building"></i> Association Information</h2>
                <p>Fill out the details below to complete your association profile</p>
            </div>

            <div class="card-body">
                <?php if (!empty($registration_error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($registration_error); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($registration_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($registration_success); ?></span>
                </div>
                <?php endif; ?>

                <form class="registration-form" method="POST" action="association-registration.php" enctype="multipart/form-data">
                    <input type="hidden" name="complete_registration" value="1">

                    <!-- Association Details Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Association Details
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="association_name">Association Name <span class="required">*</span></label>
                                <input type="text" id="association_name" name="association_name" placeholder="Enter association name" required>
                            </div>

                            <div class="form-group">
                                <label for="license_number">License Number <span class="required">*</span></label>
                                <input type="text" id="license_number" name="license_number" placeholder="Association license number" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="region">Region <span class="required">*</span></label>
                                <select id="region" name="region" required>
                                    <option value="">Select region</option>
                                    <?php foreach ($ethiopian_regions as $region): ?>
                                    <option value="<?php echo htmlspecialchars($region); ?>"><?php echo htmlspecialchars($region); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="number_of_trucks">Number of Trucks in Fleet <span class="required">*</span></label>
                                <input type="number" id="number_of_trucks" name="number_of_trucks" min="1" placeholder="Total trucks" required>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-address-book"></i>
                            Contact Information
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_person">Contact Person Name <span class="required">*</span></label>
                                <input type="text" id="contact_person" name="contact_person" placeholder="Full name of contact person" required>
                            </div>

                            <div class="form-group">
                                <label for="contact_phone">Contact Phone Number <span class="required">*</span></label>
                                <input type="tel" id="contact_phone" name="contact_phone" placeholder="Phone number" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="association_email">Association Email <span class="required">*</span></label>
                                <input type="email" id="association_email" name="association_email" placeholder="association@example.com" required>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-upload"></i>
                            Required Documents
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="business_license">Business License</label>
                                <input type="file" id="business_license" name="business_license" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                            </div>

                            <div class="form-group">
                                <label for="tin_certificate">TIN Certificate</label>
                                <input type="file" id="tin_certificate" name="tin_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="association_membership">Association Membership Certificate</label>
                                <input type="file" id="association_membership" name="association_membership" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="file-hint">PDF, JPG, PNG up to 5MB</small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Phone formatting
        document.getElementById('contact_phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})/, '($1) $2-');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})/, '($1) ');
            }
            e.target.value = value;
        });

        // Form validation
        document.querySelector('.registration-form').addEventListener('submit', function(e) {
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    isValid = false;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Real-time validation
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = '#e74c3c';
                } else {
                    this.style.borderColor = '#ddd';
                }
            });

            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#ddd';
                }
            });
        });
    </script>

</body>
</html>
