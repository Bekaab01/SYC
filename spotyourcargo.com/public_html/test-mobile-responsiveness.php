<?php
// Mobile Responsiveness Test for Dashboard
// Tests dashboard components on various screen sizes

session_start();

// Include configuration
require_once 'includes/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: access.php');
    exit;
}

$user_type = $_SESSION['user_type'] ?? '';
$shipper_id = $_SESSION['shipper_id'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Responsiveness Test - SpotYourCargo</title>

    <!-- Include dashboard styles -->
    <link rel="stylesheet" href="assets/css/shipper-dashboard.css">
    <link rel="stylesheet" href="assets/css/dashboard-enhancements.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Test Styles -->
    <style>
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .test-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }

        .device-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .device-btn {
            padding: 12px 20px;
            border: 2px solid var(--primary-blue);
            background: white;
            color: var(--primary-blue);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .device-btn.active {
            background: var(--primary-blue);
            color: white;
        }

        .device-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 51, 102, 0.2);
        }

        .viewport-container {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .viewport-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .viewport-info {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .viewport-controls {
            display: flex;
            gap: 10px;
        }

        .control-btn {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
        }

        .control-btn:hover {
            background: #f8f9fa;
        }

        .viewport-content {
            height: 600px;
            overflow: auto;
            background: #f8f9fa;
        }

        .iframe-container {
            width: 100%;
            height: 100%;
            background: white;
        }

        .test-dashboard {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }

        .test-results {
            margin-top: 30px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .test-section {
            margin-bottom: 25px;
        }

        .test-section h3 {
            color: var(--primary-blue);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #ddd;
        }

        .test-item.pass {
            border-left-color: #4CAF50;
            background: rgba(76, 175, 80, 0.05);
        }

        .test-item.fail {
            border-left-color: #F44336;
            background: rgba(244, 67, 54, 0.05);
        }

        .test-item.warn {
            border-left-color: #FF9800;
            background: rgba(255, 152, 0, 0.05);
        }

        .test-label {
            font-weight: 500;
        }

        .test-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pass {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .status-fail {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }

        .status-warn {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .status-info {
            background: rgba(0, 51, 102, 0.1);
            color: var(--primary-blue);
        }

        .manual-tests {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .manual-tests h4 {
            color: #856404;
            margin-bottom: 10px;
        }

        .manual-test-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }

        .manual-test-item {
            background: white;
            padding: 10px;
            border-radius: 6px;
            border-left: 3px solid #856404;
        }

        /* Device-specific viewport sizes */
        .device-mobile {
            width: 375px;
            margin: 0 auto;
        }

        .device-tablet {
            width: 768px;
            margin: 0 auto;
        }

        .device-desktop {
            width: 100%;
        }

        .device-large {
            width: 1200px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .device-selector {
                flex-direction: column;
                align-items: center;
            }

            .viewport-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .manual-test-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1><i class="fas fa-mobile-alt"></i> Mobile Responsiveness Test</h1>
            <p>Test dashboard responsiveness across different device sizes</p>
        </div>

        <div class="device-selector">
            <button class="device-btn active" data-device="mobile" data-width="375">
                <i class="fas fa-mobile-alt"></i> Mobile (375px)
            </button>
            <button class="device-btn" data-device="tablet" data-width="768">
                <i class="fas fa-tablet-alt"></i> Tablet (768px)
            </button>
            <button class="device-btn" data-device="desktop" data-width="100%">
                <i class="fas fa-desktop"></i> Desktop (100%)
            </button>
            <button class="device-btn" data-device="large" data-width="1200">
                <i class="fas fa-tv"></i> Large (1200px)
            </button>
        </div>

        <div class="viewport-container">
            <div class="viewport-header">
                <div class="viewport-info">
                    <span id="current-device">Mobile (375px)</span>
                </div>
                <div class="viewport-controls">
                    <button class="control-btn" onclick="reloadDashboard()">
                        <i class="fas fa-sync-alt"></i> Reload
                    </button>
                    <button class="control-btn" onclick="openFullscreen()">
                        <i class="fas fa-expand"></i> Fullscreen
                    </button>
                </div>
            </div>
            <div class="viewport-content">
                <div class="iframe-container">
                    <iframe id="dashboard-iframe" class="test-dashboard" src="shipper-dashboard.php?tab=dashboard&test_mode=1"></iframe>
                </div>
            </div>
        </div>

        <div class="test-results">
            <div class="test-section">
                <h3><i class="fas fa-check-circle"></i> Automated Tests</h3>

                <div class="test-item" id="test-viewport">
                    <span class="test-label">Viewport Meta Tag</span>
                    <span class="test-status status-info">Testing...</span>
                </div>

                <div class="test-item" id="test-responsive-grid">
                    <span class="test-label">Responsive Grid Layout</span>
                    <span class="test-status status-info">Testing...</span>
                </div>

                <div class="test-item" id="test-touch-targets">
                    <span class="test-label">Touch Target Sizes</span>
                    <span class="test-status status-info">Testing...</span>
                </div>

                <div class="test-item" id="test-font-scaling">
                    <span class="test-label">Font Scaling</span>
                    <span class="test-status status-info">Testing...</span>
                </div>

                <div class="test-item" id="test-modal-mobile">
                    <span class="test-label">Modal Mobile Adaptation</span>
                    <span class="test-status status-info">Testing...</span>
                </div>
            </div>

            <div class="manual-tests">
                <h4><i class="fas fa-user-check"></i> Manual Tests Required</h4>
                <div class="manual-test-list">
                    <div class="manual-test-item">
                        <strong>KPI Cards:</strong> Tap each card to ensure modals open properly
                    </div>
                    <div class="manual-test-item">
                        <strong>Navigation:</strong> Test sidebar collapse/expand on mobile
                    </div>
                    <div class="manual-test-item">
                        <strong>Charts:</strong> Verify chart responsiveness and touch interactions
                    </div>
                    <div class="manual-test-item">
                        <strong>Maps:</strong> Test map loading and marker interactions
                    </div>
                    <div class="manual-test-item">
                        <strong>Forms:</strong> Test form inputs and modal forms on mobile
                    </div>
                    <div class="manual-test-item">
                        <strong>Scrolling:</strong> Ensure horizontal scroll is prevented
                    </div>
                    <div class="manual-test-item">
                        <strong>Performance:</strong> Test loading speed on slower connections
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Device switching functionality
        const deviceButtons = document.querySelectorAll('.device-btn');
        const viewportContainer = document.querySelector('.viewport-container');
        const currentDeviceSpan = document.getElementById('current-device');
        const dashboardIframe = document.getElementById('dashboard-iframe');

        deviceButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                deviceButtons.forEach(btn => btn.classList.remove('active'));

                // Add active class to clicked button
                button.classList.add('active');

                const device = button.dataset.device;
                const width = button.dataset.width;

                // Update viewport info
                currentDeviceSpan.textContent = `${device.charAt(0).toUpperCase() + device.slice(1)} (${width}${width === '100%' ? '' : 'px'})`;

                // Update container classes
                viewportContainer.className = 'viewport-container';
                if (width !== '100%') {
                    viewportContainer.classList.add(`device-${device}`);
                }

                // Run responsiveness tests
                runResponsivenessTests(device, width);
            });
        });

        function reloadDashboard() {
            dashboardIframe.src = dashboardIframe.src;
        }

        function openFullscreen() {
            const iframe = document.getElementById('dashboard-iframe');
            if (iframe.requestFullscreen) {
                iframe.requestFullscreen();
            } else if (iframe.webkitRequestFullscreen) {
                iframe.webkitRequestFullscreen();
            } else if (iframe.msRequestFullscreen) {
                iframe.msRequestFullscreen();
            }
        }

        function runResponsivenessTests(device, width) {
            // Reset test statuses
            document.querySelectorAll('.test-status').forEach(status => {
                status.className = 'test-status status-info';
                status.textContent = 'Testing...';
            });

            // Test viewport meta tag
            setTimeout(() => {
                const hasViewport = dashboardIframe.contentDocument.querySelector('meta[name="viewport"]');
                updateTestStatus('test-viewport', hasViewport ? 'pass' : 'fail', hasViewport ? 'Present' : 'Missing');
            }, 2000);

            // Test responsive grid (check if KPI cards stack on mobile)
            setTimeout(() => {
                try {
                    const iframeDoc = dashboardIframe.contentDocument;
                    const kpiGrid = iframeDoc.querySelector('.kpi-grid');

                    if (kpiGrid) {
                        const computedStyle = window.getComputedStyle(kpiGrid);
                        const isResponsive = computedStyle.display === 'grid' ||
                                           computedStyle.display === 'flex' ||
                                           kpiGrid.classList.contains('responsive');
                        updateTestStatus('test-responsive-grid', isResponsive ? 'pass' : 'warn', isResponsive ? 'Responsive' : 'May need adjustment');
                    } else {
                        updateTestStatus('test-responsive-grid', 'fail', 'Not found');
                    }
                } catch (e) {
                    updateTestStatus('test-responsive-grid', 'fail', 'Cannot access iframe');
                }
            }, 2500);

            // Test touch targets
            setTimeout(() => {
                try {
                    const iframeDoc = dashboardIframe.contentDocument;
                    const buttons = iframeDoc.querySelectorAll('button, .kpi-card, .btn');
                    let smallTargets = 0;

                    buttons.forEach(btn => {
                        const rect = btn.getBoundingClientRect();
                        if (rect.width < 44 || rect.height < 44) {
                            smallTargets++;
                        }
                    });

                    const passed = smallTargets === 0;
                    updateTestStatus('test-touch-targets', passed ? 'pass' : 'warn',
                        passed ? 'All targets adequate' : `${smallTargets} small targets found`);
                } catch (e) {
                    updateTestStatus('test-touch-targets', 'fail', 'Cannot access iframe');
                }
            }, 3000);

            // Test font scaling
            setTimeout(() => {
                try {
                    const iframeDoc = dashboardIframe.contentDocument;
                    const body = iframeDoc.body;
                    const fontSize = window.getComputedStyle(body).fontSize;
                    const baseSize = parseFloat(fontSize);

                    // Check if font scales appropriately for device
                    const isMobile = width === '375';
                    const minSize = isMobile ? 14 : 16;
                    const passed = baseSize >= minSize;

                    updateTestStatus('test-font-scaling', passed ? 'pass' : 'warn',
                        `Base font: ${baseSize}px (${passed ? 'Adequate' : 'Small'})`);
                } catch (e) {
                    updateTestStatus('test-font-scaling', 'fail', 'Cannot access iframe');
                }
            }, 3500);

            // Test modal mobile adaptation
            setTimeout(() => {
                try {
                    const iframeDoc = dashboardIframe.contentDocument;
                    const modals = iframeDoc.querySelectorAll('.modal');

                    if (modals.length > 0) {
                        let mobileFriendly = true;
                        modals.forEach(modal => {
                            const content = modal.querySelector('.modal-content');
                            if (content) {
                                const maxWidth = window.getComputedStyle(content).maxWidth;
                                if (maxWidth && parseFloat(maxWidth) > parseFloat(width)) {
                                    mobileFriendly = false;
                                }
                            }
                        });

                        updateTestStatus('test-modal-mobile', mobileFriendly ? 'pass' : 'warn',
                            mobileFriendly ? 'Mobile optimized' : 'May overflow on mobile');
                    } else {
                        updateTestStatus('test-modal-mobile', 'info', 'No modals loaded yet');
                    }
                } catch (e) {
                    updateTestStatus('test-modal-mobile', 'fail', 'Cannot access iframe');
                }
            }, 4000);
        }

        function updateTestStatus(testId, status, message) {
            const testItem = document.getElementById(testId);
            const statusSpan = testItem.querySelector('.test-status');

            statusSpan.className = `test-status status-${status}`;
            statusSpan.textContent = message;

            testItem.className = `test-item ${status}`;
        }

        // Initial test run
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                runResponsivenessTests('mobile', '375');
            }, 3000);
        });

        // Handle iframe load events
        dashboardIframe.addEventListener('load', () => {
            console.log('Dashboard iframe loaded');
        });
    </script>
</body>
</html>
