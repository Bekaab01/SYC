
<?php
include_once __DIR__ . '/../private/session_config.php';
session_start();
error_log("Shipper Dashboard accessed - User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", User Type: " . ($_SESSION['user_type'] ?? 'Not set'));

include_once __DIR__ . '/../private/db.php';
include_once __DIR__ . '/includes/shipper-common.php';

// Session validation - check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: access.php');
    exit();
}

// Access control - verify user is a shipper
if ($_SESSION['user_type'] !== 'shipper') {
    header('Location: access.php');
    exit();
}

// Handle AJAX actions for drafts tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($_POST['action'] === 'verify') {
        $shipment_id = $_POST['shipment_id'];
        $stmt = $pdo->prepare("UPDATE shipments SET is_verified = 1 WHERE id = ? AND shipper_id = ?");
        $stmt->execute([$shipment_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $response = ['success' => true, 'message' => 'Load marked as verified'];
        } else {
            $response = ['success' => false, 'message' => 'Load not found or access denied'];
        }
    } elseif ($_POST['action'] === 'boost') {
        $shipment_id = $_POST['shipment_id'];
        $stmt = $pdo->prepare("UPDATE shipments SET is_boosted = 1 WHERE id = ? AND shipper_id = ?");
        $stmt->execute([$shipment_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            // Trigger boost notification (placeholder - implement notification system)
            $response = ['success' => true, 'message' => 'Load boosted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Load not found or access denied'];
        }
    } elseif ($_POST['action'] === 'withdraw') {
        $shipment_id = $_POST['shipment_id'];
        $stmt = $pdo->prepare("UPDATE shipments SET status = 'cancelled' WHERE id = ? AND shipper_id = ? AND status IN ('Draft','Pending Verification')");
        $stmt->execute([$shipment_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $response = ['success' => true, 'message' => 'Load withdrawn successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Load not found or cannot be withdrawn'];
        }
    } elseif ($_POST['action'] === 'delete') {
        $shipment_id = $_POST['shipment_id'];
        // First delete associated documents
        $stmt = $pdo->prepare("DELETE FROM load_documents WHERE shipment_id = ?");
        $stmt->execute([$shipment_id]);
        // Then delete the shipment
        $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = ? AND shipper_id = ? AND status = 'Draft'");
        $stmt->execute([$shipment_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $response = ['success' => true, 'message' => 'Draft and associated documents deleted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Draft not found or cannot be deleted'];
        }
    } elseif ($_POST['action'] === 'remove_document') {
        $shipment_id = $_POST['shipment_id'];
        $doc_type = $_POST['doc_type'];
        // Verify ownership first
        $stmt = $pdo->prepare("SELECT id FROM shipments WHERE id = ? AND shipper_id = ?");
        $stmt->execute([$shipment_id, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM load_documents WHERE shipment_id = ? AND doc_type = ?");
            $stmt->execute([$shipment_id, $doc_type]);
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Document removed successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Document not found'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Access denied'];
        }
    }

    echo json_encode($response);
    exit;
}

// Handle AJAX fetch for documents
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_documents') {
    $shipment_id = $_GET['shipment_id'];
    $shipper_id = $_SESSION['user_id'];
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM shipments WHERE id = ? AND shipper_id = ?");
    $stmt->execute([$shipment_id, $shipper_id]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            SELECT
                doc_type,
                uploaded_at,
                COALESCE(ocr_status, 'pending') as ocr_status,
                COALESCE(ocr_confidence, 0) as ocr_confidence,
                COALESCE(verification_status, 'pending') as verification_status,
                COALESCE(trust_score, 0) as trust_score,
                COALESCE(ocr_result, '{}') as ocr_result,
                COALESCE(field_extraction_result, '{}') as field_extraction_result,
                COALESCE(validation_result, '{}') as validation_result,
                COALESCE(llm_verification_result, '{}') as llm_verification_result
            FROM load_documents
            WHERE shipment_id = ?
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$shipment_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($documents);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Get shipper ID from session
$shipper_id = $_SESSION['user_id'];

// Initialize global variables
$user_name = "User";
$user_initials = "U";
$company_name = "Shipping Company";
$welcome_name = "User";
$shipper_data = [];
$user_syc_id = null;
$syc_shipper_id = null;

// Fetch user's syc_id
try {
    $user_stmt = $pdo->prepare("SELECT syc_id FROM users WHERE id = ?");
    $user_stmt->execute([$shipper_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_syc_id = $user_data['syc_id'] ?? null;

    // Generate and set syc_id if missing
    if (!$user_syc_id) {
        $user_syc_id = 'SHP' . str_pad($shipper_id, 6, '0', STR_PAD_LEFT);
        $update_stmt = $pdo->prepare("UPDATE users SET syc_id = ? WHERE id = ?");
        $update_stmt->execute([$user_syc_id, $shipper_id]);
    }
} catch (PDOException $e) {
    error_log("User syc_id fetch error: " . $e->getMessage());
}

// Fetch shipper profile data
try {
    $shipper_stmt = $pdo->prepare("
        SELECT
            u.*,
            s.id as shipper_id,
            s.syc_id as syc_shipper_id,
            s.company_name,
            s.company_contact_name,
            s.email as shipper_email,
            s.created_at as shipper_created_at,
            u.first_name,
            u.last_name,
            u.full_name
        FROM users u
        LEFT JOIN shippers s ON u.syc_id = s.syc_id
        WHERE u.id = ?
    ");
    $shipper_stmt->execute([$shipper_id]);
    $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);

    if ($shipper_data) {
        // Prioritize shipper first_name and last_name if available
        $first_name = $shipper_data['first_name'] ?? null;
        $last_name = $shipper_data['last_name'] ?? null;
        $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($shipper_data['name'] ?? $shipper_data['username'] ?? 'User');
        $user_name = $display_name; // For sidebar and other uses

        // Extract first name for welcome message
        if ($first_name) {
            $welcome_name = ucfirst(strtolower(trim($first_name)));
        } else {
            $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
            // If welcome_name contains @ (email), extract username part
            if (strpos($welcome_name, '@') !== false) {
                $welcome_name = explode('@', $welcome_name)[0];
            }
            // Capitalize first letter
            $welcome_name = ucfirst(strtolower($welcome_name));
        }

        $user_initials = strtoupper(substr($display_name, 0, 2));
        $company_name = $shipper_data['company_name'] ?? 'Shipping Company';
        $shipper_id_db = $shipper_data['shipper_id'] ?? null;
        $syc_shipper_id = $shipper_data['syc_shipper_id'] ?? $user_syc_id;

        // If shipper profile doesn't exist but user is shipper type, create one
        if (!$shipper_id_db && $_SESSION['user_type'] === 'shipper') {
            // Extract first_name from username or email
            $username = $shipper_data['username'] ?? '';
            $email = $shipper_data['email'] ?? '';
            $extracted_first = '';
            if (strpos($username, '@') !== false) {
                $extracted_first = explode('@', $username)[0];
            } elseif (!empty($username)) {
                $extracted_first = $username;
            } elseif (!empty($email)) {
                $extracted_first = explode('@', $email)[0];
            }
            $first_name_to_set = ucfirst(strtolower(trim($extracted_first)));

            $create_shipper = $pdo->prepare("
                INSERT INTO shippers (syc_id, company_name, email, company_contact_name, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $create_shipper->execute([
                $user_syc_id,
                $company_name,
                $shipper_data['email'],
                $display_name,
                $first_name_to_set
            ]);
            $shipper_id_db = $pdo->lastInsertId();

            // Refresh shipper data
            $shipper_stmt->execute([$shipper_id]);
            $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);
            $syc_shipper_id = $shipper_data['syc_shipper_id'] ?? $user_syc_id;

            // Recompute names after refresh
            $first_name = $shipper_data['first_name'] ?? null;
            $last_name = $shipper_data['last_name'] ?? null;
            $display_name = trim(($first_name ? $first_name . ' ' : '') . $last_name) ?: ($shipper_data['name'] ?? $shipper_data['username'] ?? 'User');
            $user_name = $display_name;
            if ($first_name) {
                $welcome_name = ucfirst(strtolower(trim($first_name)));
            } else {
                $welcome_name = trim(explode(' ', $display_name)[0]) ?: $display_name;
                if (strpos($welcome_name, '@') !== false) {
                    $welcome_name = explode('@', $welcome_name)[0];
                }
                $welcome_name = ucfirst(strtolower($welcome_name));
            }
            $user_initials = strtoupper(substr($display_name, 0, 2));
        }
    } else {
        $syc_shipper_id = $user_syc_id;
        $display_name = 'User';
        $user_name = $display_name;
        $welcome_name = $display_name;
        $user_initials = 'U';
    }
} catch (PDOException $e) {
    error_log("Shipper data fetch error: " . $e->getMessage());
}

// Handle profile update (global action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = $_POST['phone'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    
    try {
        $update_stmt = $pdo->prepare("
            UPDATE users
            SET phone = ?, company_name = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([$phone, $company_name, $shipper_id]);
        
        if ($update_stmt->rowCount() > 0) {
            // Refresh shipper data
            $shipper_stmt->execute([$shipper_id]);
            $shipper_data = $shipper_stmt->fetch(PDO::FETCH_ASSOC);
            $GLOBALS['success_message'] = "Profile updated successfully!";
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $GLOBALS['error_message'] = "Error updating profile. Please try again.";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: access.php');
    exit();
}

// Determine current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Define available tabs
$available_tabs = [
    'dashboard',
    'post_load',
    'shipments',
    'drafts',
    'available_trucks',
    'matching',
    'active_shipments',
    'drafts',
    'settings',
    'invoices',
    'analytics',
    'vault',
    'wallet_payments',
    'tax_invoices',
    'support_center',
    'bids',
    'live_fleet_map',
    'partner_network',
    'associations_unions',
    'preferred_transporters',
    'smart_locations'
];

// Validate tab
if (!in_array($current_tab, $available_tabs)) {
    $current_tab = 'dashboard';
}

// Check if this is an AJAX request for tab content
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo "<!-- AJAX Debug: Request received for tab: $current_tab, shipper_id: $shipper_id -->";
    $tab_file = __DIR__ . '/shipper-tabs/' . $current_tab . '.php';
    echo "<!-- AJAX Debug: Looking for file: $tab_file -->";
    if (file_exists($tab_file)) {
        echo "<!-- AJAX Debug: File exists, including it -->";
        // Make variables available to tab file
        extract([
            'shipper_id' => $shipper_id,
            'pdo' => $pdo,
            'shipper_data' => $shipper_data,
            'user_syc_id' => $user_syc_id,
            'syc_shipper_id' => $syc_shipper_id,
            'current_tab' => $current_tab
        ], EXTR_SKIP);

        include $tab_file;
    } else {
        echo "<!-- AJAX Debug: File not found -->";
        echo '<div class="empty-state"><h3>Tab not found</h3></div>';
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot Your Cargo - Shipper Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <link rel="stylesheet" href="assets/css/shipper-dashboard.css">
    <script src="assets/js/post-load-wizard.js"></script>
    <script src="assets/js/modal-system.js"></script>
    <script src="assets/js/script.js"></script>
    <link rel="stylesheet" href="assets/css/dashboard-enhancements.css?v=1.1">
    <link rel="stylesheet" href="assets/css/wizard-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include_once __DIR__ . '/assets/php/sidebar-shipper.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search shipments, cargo, or invoices...">
            </div>
            
            <div class="header-actions">
                <div class="notification-btn" id="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" style="display: none;">0</span>
                </div>

                <a href="contact.php?from=shipper-dashboard" id="messages-btn" class="support-icon" title="Need Help? Contact Support">
                    <i class="fa-solid fa-headset"></i>
                </a>

                <!-- Notifications Dropdown -->
                <div class="notification-dropdown" id="notification-dropdown">
                    <div class="dropdown-header">
                        <h4>Notifications</h4>
                        <div style="display: flex; gap: 10px;">
                            <a href="#" id="mark-all-read-btn" style="color: var(--secondary-blue); text-decoration: none; font-size: 12px;">Mark All Read</a>
                            <a href="notifications.php">View All</a>
                        </div>
                    </div>
                    <div class="dropdown-content">
                        <div class="dropdown-item">
                            <div class="dropdown-text">
                                <p>Loading notifications...</p>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-footer">
                        <a href="notifications.php">See all notifications</a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Dashboard Content Container -->
        <main class="dashboard-content" id="tab-content-container">
            <!-- Content will be loaded dynamically via AJAX -->
            <div class="tab-loading" style="padding: 40px; text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--primary-blue);"></i>
                <p>Loading dashboard...</p>
            </div>
        </main>
    </div>

    <!-- Master Modal Container -->
    <div id="master-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="master-modal-title">Modal Title</h3>
                <button class="modal-close" onclick="closeMasterModal()">&times;</button>
            </div>
            <div class="modal-body" id="master-modal-content">
                <!-- Modal content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        const shipperId = <?php echo json_encode($shipper_id); ?>;
        const userSycId = <?php echo json_encode($user_syc_id); ?>;
        const currentTab = <?php echo json_encode($current_tab); ?>;
        const welcomeName = <?php echo json_encode($welcome_name); ?>;
        const sycShipperId = <?php echo json_encode($syc_shipper_id); ?>;

        // Load tab content
        function loadTabContent(tabName) {
            console.log('Loading tab:', tabName);
            const container = document.getElementById('tab-content-container');
            container.innerHTML = `
                <div class="tab-loading" style="padding: 40px; text-align: center;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--primary-blue);"></i>
                    <p>Loading ${tabName.replace('_', ' ')}...</p>
                </div>
            `;

            // Update URL without reloading
            const newUrl = tabName !== 'dashboard' ?
                window.location.pathname + '?tab=' + tabName :
                window.location.pathname;
            history.pushState({tab: tabName}, '', newUrl);

            const ajaxUrl = `?ajax=1&tab=${tabName}`;
            console.log('Making AJAX request to:', ajaxUrl);

            // Load content via AJAX
            fetch(ajaxUrl)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) throw new Error(`Network response was not ok: ${response.status}`);
                    return response.text();
                })
                .then(html => {
                    console.log('Received HTML length:', html.length);
                    console.log('HTML preview:', html.substring(0, 200));
                    container.innerHTML = html;

                    // Execute any scripts in the loaded content
                    const scripts = container.querySelectorAll('script');
                    scripts.forEach(script => {
                        if (script.src) {
                            const newScript = document.createElement('script');
                            newScript.src = script.src;
                            document.head.appendChild(newScript);
                        } else if (script.type === '' || script.type === 'text/javascript' || script.type === 'application/javascript') {
                            eval(script.innerHTML);
                        }
                        // Ignore other script types like application/json
                    });

                    initializeTabScripts(tabName);

                    // Update active states in sidebar
                    updateSidebarActiveState(tabName);

                    // Update page title
                    document.title = `Spot Your Cargo - ${tabName.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}`;
                })
                .catch(error => {
                    console.error('Error loading tab:', error);
                    container.innerHTML = `
                        <div class="alert alert-error">
                            <p>Error loading content: ${error.message}. Please try again.</p>
                            <button onclick="loadTabContent('${tabName}')" class="btn btn-primary">Retry</button>
                        </div>
                    `;
                });
        }

        // Initialize tab-specific scripts
        function initializeTabScripts(tabName) {
            // Re-initialize common scripts
            initializeCommonScripts();
            
            // Tab-specific initialization
            switch(tabName) {
                case 'available_trucks':
                    initAvailableTrucks();
                    break;
                case 'matching':
                    initSmartMatching();
                    break;
                case 'post_load':
                    initPostLoadForm();
                    break;
            }
        }

        // Initialize common scripts
        function initializeCommonScripts() {
            // Re-attach event listeners for modals, forms, etc.
            attachModalListeners();
            attachFormListeners();
            attachActionButtons();
        }

        // Update sidebar active state
        function updateSidebarActiveState(tabName) {
            // Define freight desk sub-tabs
            const freight_desk_tabs = ['post_load', 'active_shipments', 'matching', 'drafts'];

            // Remove active class from all sidebar items
            document.querySelectorAll('.sidebar-nav button, .sidebar-nav a').forEach(item => {
                item.classList.remove('active');
            });

            // Add active class to current tab
            const sidebarItems = document.querySelectorAll(`[data-section="${tabName}"]`);
            sidebarItems.forEach(item => {
                item.classList.add('active');

                // If this is a submenu item, also activate its parent
                const parentLi = item.closest('.sidebar-parent');
                if (parentLi) {
                    parentLi.classList.add('active');
                    parentLi.classList.add('has-active-child');
                    const toggle = parentLi.querySelector('.sidebar-parent-toggle');
                    if (toggle) toggle.classList.add('active');
                }
            });

            // Deactivate freight desk if not on its sub-tabs
            if (!freight_desk_tabs.includes(tabName)) {
                const freightDeskParent = document.querySelector('.sidebar-parent[data-section="freight_desk"]');
                if (freightDeskParent) {
                    freightDeskParent.classList.remove('active');
                    freightDeskParent.classList.remove('has-active-child');
                    const toggle = freightDeskParent.querySelector('.sidebar-parent-toggle');
                    if (toggle) toggle.classList.remove('active');
                }
            }
        }

        // Handle sidebar navigation
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-section]');
            if (target) {
                e.preventDefault();
                const sectionId = target.getAttribute('data-section');
                loadTabContent(sectionId);
                
                // Close mobile sidebar on mobile
                if (window.innerWidth <= 768) {
                    closeMobileSidebar();
                }
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.tab) {
                loadTabContent(e.state.tab);
            } else {
                // Check URL for tab parameter
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab') || 'dashboard';
                loadTabContent(tab);
            }
        });

        // Mobile menu toggle
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            const backdrop = document.querySelector('.sidebar-backdrop');
            sidebar.classList.toggle('active');
            if (backdrop) backdrop.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            const backdrop = document.querySelector('.sidebar-backdrop');
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (backdrop) backdrop.classList.remove('active');
            }
        });

        // Notifications dropdown
        document.getElementById('notification-btn')?.addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('notification-dropdown').classList.toggle('show');
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.getElementById('notification-dropdown').classList.remove('show');
        });

        // Load initial tab
        document.addEventListener('DOMContentLoaded', function() {
            loadTabContent(currentTab);
            
            // Set initial sidebar state
            updateSidebarActiveState(currentTab);
        });

        // Tab-specific initialization functions
        function initAvailableTrucks() {
            // Available trucks specific scripts
            console.log('Initializing available trucks');
        }

        function initSmartMatching() {
            // Smart matching specific scripts
            console.log('Initializing smart matching');
            
            // Contact for Match modal
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.contact-match-btn');
                if (btn) {
                    const cargoId = btn.dataset.cargoId;
                    const cargoDescription = btn.dataset.cargoDescription;
                    const truckId = btn.dataset.truckId;
                    const truckModel = btn.dataset.truckModel;
                    const carrierId = btn.dataset.carrierId;
                    const carrierCompany = btn.dataset.carrierCompany;
                    
                    openContactMatchModal(cargoId, cargoDescription, truckId, truckModel, carrierId, carrierCompany);
                }
            });
        }

        function initPostLoadForm() {
            // Post load form multi-step functionality
            console.log('Initializing post load form');
            // Initialize the wizard functionality
            if (typeof initPostLoadWizard === 'function') {
                initPostLoadWizard();
            } else {
                console.error('initPostLoadWizard function not found');
            }
        }

        // Common modal functions
        function openContactMatchModal(cargoId, cargoDescription, truckId, truckModel, carrierId, carrierCompany) {
            // ... (keep existing modal code)
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.remove();
        }

        function closeMasterModal() {
            const modal = document.getElementById('master-modal');
            modal.classList.remove('active');
            const modalContent = document.getElementById('master-modal-content');
            modalContent.innerHTML = '';
        }

        // Attach common event listeners
        function attachModalListeners() {
            document.getElementById('master-modal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeMasterModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeMasterModal();
                }
            });
        }

        function attachFormListeners() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
        }

        function attachActionButtons() {
            // Attach listeners to action buttons
            document.addEventListener('click', function(e) {
                // View truck details
                if (e.target.closest('.view-truck-details')) {
                    const truck = JSON.parse(e.target.closest('.view-truck-details').dataset.truck || '{}');
                    openTruckDetails(truck);
                }
                
                // Contact carrier
                if (e.target.closest('.contact-carrier-btn')) {
                    const truckId = e.target.closest('.contact-carrier-btn').dataset.truckId;
                    const carrierName = e.target.closest('.contact-carrier-btn').dataset.carrierName;
                    contactCarrier(truckId, carrierName);
                }
            });
        }

        // Make functions globally available
        window.loadTabContent = loadTabContent;
        window.closeMobileSidebar = closeMobileSidebar;
        window.openMobileSidebar = openMobileSidebar;
        window.closeModal = closeModal;
        window.closeMasterModal = closeMasterModal;
    </script>
</body>
</html>
