<!-- Sidebar -->
<aside class="sidebar">
    <!-- Top Fixed Section: Logo -->
    <div class="sidebar-top">
        <a href="index.php" class="sidebar-logo">
            <img src="assets/img/SYC-Transparent.png" alt="Spot Your Cargo" style="filter: brightness(0) invert(1);">
            <span class="sidebar-logo-text">Spot Your Cargo<span class="sidebar-logo-dot">.</span></span>
        </a>
    </div>

    <!-- Middle Scrollable Section: Navigation Menu -->
    <div class="sidebar-middle">
        <nav class="sidebar-nav">
            <ul>
                <li><a href="#" class="active" data-tab="dashboard"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="#" data-tab="my-trucks"><i class="fas fa-truck"></i> My Trucks</a></li>
                <li><a href="#" data-tab="available-loads"><i class="fas fa-truck-loading"></i> Available Loads</a></li>
                <li><a href="#" data-tab="my-loads"><i class="fas fa-shipping-fast"></i> My Loads</a></li>
                <li><a href="#" data-tab="invoices"><i class="fas fa-file-invoice"></i> Invoices</a></li>
                <li><a href="#" data-tab="performance"><i class="fas fa-chart-line"></i> Performance</a></li>
                <li><a href="#" data-tab="settings"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </nav>
    </div>

    <!-- Bottom Fixed Section: User Info + Logout -->
    <div class="sidebar-bottom">
        <a href="profile.php" class="user-profile-link">
            <div class="user-profile">
                <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                <div class="user-info">
                    <p><?php echo htmlspecialchars($user_name); ?></p>
                </div>
            </div>
        </a>
        <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>
