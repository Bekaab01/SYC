<!-- Sidebar -->
<aside class="sidebar">
    <!-- Top Fixed Section: Logo -->
    <div class="sidebar-top">
        <a href="index.php" class="sidebar-logo">
            <img src="assets/img/SYC-Transparent.png" alt="Spot Your Cargo">
            <span class="sidebar-logo-text">Spot Your Cargo<span class="sidebar-logo-dot">.</span></span>
        </a>
    </div>

    <!-- Middle Scrollable Section: Navigation Menu -->
    <div class="sidebar-middle">
        <!-- Primary CTA Button -->
        <div class="sidebar-cta">
            <button data-section="post_load" class="btn btn-primary btn-new-shipment">
                <i class="fas fa-plus"></i> New Shipment
            </button>
        </div>

        <nav class="sidebar-nav">
            <!-- Group 1: Mission Control -->
            <div class="sidebar-group">
                <h3 class="sidebar-group-header">Mission Control</h3>
                <ul>
                    <li><button data-section="dashboard" class="active" title="Overview of your shipping activities"><i class="fas fa-home"></i> Dashboard</button></li>
                    <li class="sidebar-parent" data-section="freight_desk">
                        <a href="#" class="sidebar-parent-toggle" aria-expanded="false"><i class="fas fa-shipping-fast"></i> Freight Desk <i class="fas fa-chevron-right sidebar-chevron"></i></a>
                        <ul class="sidebar-submenu">
                            <li><button data-section="post_load"><i class="fas fa-plus-circle"></i> Post New Load</button></li>
                            <li><button data-section="active_shipments"><i class="fas fa-ship"></i> Active Shipments</button></li>
                            <li><button data-section="matching"><i class="fas fa-heart"></i> Smart Matching <span class="badge"><!-- dynamic badge --></span></button></li>
                            <li><button data-section="drafts"><i class="fas fa-edit"></i> Drafts</button></li>
                        </ul>
                    </li>
                    <li><button data-section="available_trucks" title="Browse available trucks"><i class="fas fa-truck"></i> Available Trucks</button></li>
                    <li><button data-section="live_fleet_map" title="Track available trucks in real-time"><i class="fas fa-map-marked-alt"></i> Live Fleet Map</button></li>
                </ul>
            </div>

            <!-- Group 2: Logistics Ecosystem -->
            <div class="sidebar-group">
                <h3 class="sidebar-group-header">Logistics Ecosystem</h3>
                <ul>
                    <li><button data-section="partner_network"><i class="fas fa-handshake"></i> Partner Network</button></li>
                    <li><button data-section="associations_unions"><i class="fas fa-users"></i> Associations & Unions</button></li>
                    <li><button data-section="preferred_transporters"><i class="fas fa-star"></i> Preferred Transporters</button></li>
                    <li><button data-section="smart_locations" title="Find optimal pickup and delivery locations"><i class="fas fa-map-marker-alt"></i> Smart Locations</button></li>
                </ul>
            </div>

            <!-- Group 3: Compliance & Finance -->
            <div class="sidebar-group">
                <h3 class="sidebar-group-header">Compliance & Finance</h3>
                <ul>
                    <li><button data-section="vault" title="Secure document storage"><i class="fas fa-lock"></i> Digital Vault</button></li>
                    <li><button data-section="invoices"><i class="fas fa-file-invoice"></i> Financial Ledger</button></li>
                    <li><button data-section="wallet_payments"><i class="fas fa-wallet"></i> Wallet & Payments</button></li>
                    <li><button data-section="tax_invoices"><i class="fas fa-calculator"></i> Tax Invoices</button></li>
                </ul>
            </div>

            <!-- Group 4: Business Intelligence -->
            <div class="sidebar-group">
                <h3 class="sidebar-group-header">Business Intelligence</h3>
                <ul>
                    <li><button data-section="analytics" title="Track performance metrics and insights"><i class="fas fa-chart-line"></i> Performance Insights</button></li>
                    <li><button data-section="support_center" title="Get help and support"><i class="fas fa-headset"></i> Support Center</button></li>
                </ul>
            </div>
        </nav>
    </div>

    <!-- Bottom Fixed Section: User Info + Logout -->
    <div class="sidebar-bottom">
        <a href="profile.php" class="user-profile-link">
            <div class="user-profile">
                <div class="user-avatar"><?php echo $user_initials; ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user_name); ?></h4>
                </div>
            </div>
        </a>
        <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- Mobile Backdrop -->
<div class="sidebar-backdrop"></div>

<script>
// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Submenu toggle - on parent toggle click (text and chevron)
    const parentToggles = document.querySelectorAll('.sidebar-parent-toggle');
    parentToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const parent = this.closest('.sidebar-parent');
            parent.classList.toggle('active');
            this.classList.toggle('expanded');
            updateAriaExpanded(this);
            saveMenuState();
        });
    });

    // Mobile backdrop click to close sidebar
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeMobileSidebar();
        });
    }

    // Close sidebar on navigation (mobile) - but not for submenu buttons
    const navButtons = document.querySelectorAll('.sidebar-nav button[data-section]');
    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Only close sidebar if button is not inside a submenu
            if (window.innerWidth <= 768 && !this.closest('.sidebar-submenu')) {
                closeMobileSidebar();
            }
        });
    });

    // ESC key to close sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 768) {
            closeMobileSidebar();
        }
    });

    // Initialize active states on page load
    initializeActiveStates();

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Close mobile sidebar on desktop resize
            closeMobileSidebar();
        }
    });
});

// Function to close mobile sidebar
function closeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const body = document.body;

    if (sidebar) sidebar.classList.remove('active');
    if (backdrop) backdrop.classList.remove('active');
    body.classList.remove('sidebar-open');
}

// Function to open mobile sidebar
function openMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const body = document.body;

    if (sidebar) sidebar.classList.add('active');
    if (backdrop) backdrop.classList.add('active');
    body.classList.add('sidebar-open');
}

// Initialize active states and keep parent menus open for active children
function initializeActiveStates() {
    // Find all parent menus with submenus
    const parents = document.querySelectorAll('.sidebar-parent');

    parents.forEach(parent => {
        const submenu = parent.querySelector('.sidebar-submenu');
        if (submenu) {
            const subButtons = submenu.querySelectorAll('button[data-section]');
            const activeSubButtons = submenu.querySelectorAll('button.active');
            // Only mark parent as active if exactly 3 sub-buttons are active
            if (subButtons.length > 0 && activeSubButtons.length === 3) {
                parent.classList.add('active');
                parent.classList.add('has-active-child');
                const toggle = parent.querySelector('.sidebar-parent-toggle');
                if (toggle) {
                    toggle.classList.add('expanded');
                    updateAriaExpanded(toggle);
                }
            }
        }
    });
}

// Update ARIA expanded attribute
function updateAriaExpanded(element) {
    const isExpanded = element.classList.contains('active');
    element.setAttribute('aria-expanded', isExpanded);
}

// Save menu state to localStorage (optional enhancement)
function saveMenuState() {
    const expandedParents = document.querySelectorAll('.sidebar-parent.active');
    const expandedIds = Array.from(expandedParents).map(parent => parent.dataset.section);
    localStorage.setItem('sidebar-expanded-menus', JSON.stringify(expandedIds));
}

// Load menu state from localStorage (optional enhancement)
function loadMenuState() {
    const expandedIds = JSON.parse(localStorage.getItem('sidebar-expanded-menus') || '[]');
    expandedIds.forEach(sectionId => {
        const parent = document.querySelector(`.sidebar-parent[data-section="${sectionId}"]`);
        if (parent) {
            parent.classList.add('active');
                if (window.location.pathname.includes('shipper-tabs/')) {
                    parent.classList.add('has-active-child');
                } else {
                    parent.classList.remove('has-active-child');
                }
        }
    });
}

// Make functions globally available for external use
window.closeMobileSidebar = closeMobileSidebar;
window.openMobileSidebar = openMobileSidebar;
</script>
