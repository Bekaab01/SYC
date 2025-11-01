<!-- Sidebar -->
<aside class="sidebar">
    <!-- Top Fixed Section: Logo -->
    <div class="sidebar-top">
        <a href="index.php" class="sidebar-logo">
            <img src="assets/img/SYC-Transparent.png" alt="SYC" style="filter: brightness(0) invert(1);">
            <span class="sidebar-logo-text">SYC<span class="sidebar-logo-dot">.</span></span>
        </a>
    </div>

    <!-- Middle Scrollable Section: Navigation Menu -->
    <div class="sidebar-middle">
        <nav class="sidebar-nav">
            <ul>
                <li><a href="?tab=dashboard" class="<?php echo $current_tab == 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="?tab=shipments" class="<?php echo $current_tab == 'shipments' ? 'active' : ''; ?>"><i class="fas fa-shipping-fast"></i> Shipments</a></li>
                <li><a href="?tab=invoices" class="<?php echo $current_tab == 'invoices' ? 'active' : ''; ?>"><i class="fas fa-file-invoice"></i> Invoices</a></li>
                <li><a href="?tab=my-cargo" class="<?php echo $current_tab == 'my-cargo' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i> My Cargo</a></li>
                <li><a href="?tab=available-trucks" class="<?php echo $current_tab == 'available-trucks' ? 'active' : ''; ?>"><i class="fas fa-truck"></i> Available Trucks</a></li>
                <li><a href="?tab=matching" class="<?php echo $current_tab == 'matching' ? 'active' : ''; ?>"><i class="fas fa-heart"></i> Smart Matching</a></li>
                <li><a href="?tab=active-shipments" class="<?php echo $current_tab == 'active-shipments' ? 'active' : ''; ?>"><i class="fas fa-ship"></i> Active Shipments</a></li>
                <li><a href="?tab=analytics" class="<?php echo $current_tab == 'analytics' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Analytics</a></li>
                <li><a href="?tab=settings" class="<?php echo $current_tab == 'settings' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
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
