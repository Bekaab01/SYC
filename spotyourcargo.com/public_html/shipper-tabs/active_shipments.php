<?php
// Active Shipments Tab - Real-time operational monitoring interface
?>

<div class="section-header">
    <h2><i class="fas fa-truck-moving"></i> Active Shipments</h2>
    <div class="header-actions">
        <button class="btn btn-outline-primary" onclick="refreshActiveShipments()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- KPI Summary Strip -->
<div class="kpi-strip" id="kpi-strip">
    <div class="kpi-card bg-success" data-filter="on_time">
        <div class="kpi-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-value" id="on-time-count">0</div>
            <div class="kpi-label">On-Time</div>
        </div>
    </div>

    <div class="kpi-card bg-warning" data-filter="delayed">
        <div class="kpi-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-value" id="delayed-count">0</div>
            <div class="kpi-label">Delayed</div>
        </div>
    </div>

    <div class="kpi-card bg-danger" data-filter="critical">
        <div class="kpi-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-value" id="critical-count">0</div>
            <div class="kpi-label">Critical</div>
        </div>
    </div>
</div>

<!-- Active Shipments Table -->
<div class="table-container">
    <table class="data-table" id="active-shipments-table">
        <thead>
            <tr>
                <th>Shipment ID</th>
                <th>Driver & Truck</th>
                <th>Route Progress</th>
                <th>Live Status</th>
                <th>ETA</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="shipments-tbody">
            <!-- Shipments will be loaded here -->
        </tbody>
    </table>
</div>

<!-- Empty State -->
<div class="empty-state" id="empty-state" style="display: none;">
    <i class="fas fa-truck"></i>
    <h3>No Active Shipments</h3>
    <p>All your shipments are currently completed or pending assignment.</p>
</div>

<!-- Loading State -->
<div class="loading-state" id="loading-state">
    <i class="fas fa-spinner fa-spin"></i>
    <p>Loading active shipments...</p>
</div>

<!-- Document Quick View Panel -->
<div class="document-panel" id="document-panel" style="display: none;">
    <div class="panel-header">
        <h4>Documents</h4>
        <button class="panel-close" onclick="closeDocumentPanel()">&times;</button>
    </div>
    <div class="panel-content" id="document-content">
        <!-- Documents will be loaded here -->
    </div>
</div>

<script>
// Global variables
let activeShipmentsData = [];
let currentFilter = 'all';
let refreshInterval;

// Initialize when tab loads
document.addEventListener('DOMContentLoaded', function() {
    loadActiveShipments();
    startAutoRefresh();
});

// Load active shipments data
function loadActiveShipments() {
    showLoadingState();

    fetch('api/get_active_shipments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                activeShipmentsData = data.shipments;
                updateKPIs(data.kpi_counts);
                renderShipmentsTable();
                hideLoadingState();
            } else {
                showError('Failed to load shipments data');
            }
        })
        .catch(error => {
            console.error('Error loading shipments:', error);
            showError('Network error while loading shipments');
        });
}

// Update KPI counters
function updateKPIs(counts) {
    document.getElementById('on-time-count').textContent = counts.on_time;
    document.getElementById('delayed-count').textContent = counts.delayed;
    document.getElementById('critical-count').textContent = counts.critical;
}

// Render shipments table
function renderShipmentsTable() {
    const tbody = document.getElementById('shipments-tbody');
    const emptyState = document.getElementById('empty-state');

    if (activeShipmentsData.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';

    let filteredData = activeShipmentsData;
    if (currentFilter !== 'all') {
        filteredData = activeShipmentsData.filter(shipment => {
            if (currentFilter === 'on_time') return shipment.status_class === 'success';
            if (currentFilter === 'delayed') return shipment.status_class === 'warning';
            if (currentFilter === 'critical') return shipment.status_class === 'danger';
            return true;
        });
    }

    tbody.innerHTML = filteredData.map(shipment => `
        <tr>
            <td>
                <button class="shipment-id-btn" onclick="viewShipmentTimeline(${shipment.id})">
                    ${shipment.load_ref}
                </button>
            </td>
            <td>
                <div class="driver-info">
                    <div class="driver-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="driver-details">
                        <div class="driver-name">${shipment.driver_name}</div>
                        <div class="truck-plate">${shipment.truck_plate}</div>
                        ${shipment.driver_phone ? `<a href="tel:${shipment.driver_phone}" class="phone-link"><i class="fas fa-phone"></i></a>` : ''}
                    </div>
                </div>
            </td>
            <td>
                <div class="route-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${shipment.progress}%"></div>
                    </div>
                    <div class="progress-text">
                        ${shipment.origin} → ${shipment.destination} | ${shipment.progress}%
                    </div>
                </div>
            </td>
            <td>
                <span class="status-badge ${shipment.status_class}">
                    ${shipment.status_badge}
                </span>
            </td>
            <td>${shipment.eta}</td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn" onclick="openShipmentMap(${shipment.id})" title="View Map">
                        <i class="fas fa-map-marked-alt"></i>
                    </button>
                    <button class="action-btn" onclick="showDocuments(${shipment.id})" title="Quick Docs">
                        <i class="fas fa-file-alt"></i>
                    </button>
                    <button class="action-btn" onclick="pingDriver(${shipment.id})" title="Ping Driver">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// KPI filter click handlers
document.addEventListener('click', function(e) {
    const kpiCard = e.target.closest('.kpi-card');
    if (kpiCard) {
        const filter = kpiCard.dataset.filter;

        // Remove active class from all cards
        document.querySelectorAll('.kpi-card').forEach(card => card.classList.remove('active'));

        // Add active class to clicked card
        kpiCard.classList.add('active');

        // Apply filter
        currentFilter = filter;
        renderShipmentsTable();
    }
});

// Manual refresh
function refreshActiveShipments() {
    loadActiveShipments();
}

// Auto refresh every 60 seconds
function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        loadActiveShipments();
    }, 60000);
}

// Stop auto refresh when tab is not active
function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
}

// Interactive functions
function viewShipmentTimeline(shipmentId) {
    // Placeholder - implement timeline view
    console.log('View shipment timeline:', shipmentId);
}

function openShipmentMap(shipmentId) {
    // Placeholder - implement map view
    console.log('Open shipment map:', shipmentId);
    alert('Map view for shipment ' + shipmentId + ' will be implemented');
}

function showDocuments(shipmentId) {
    const panel = document.getElementById('document-panel');
    const content = document.getElementById('document-content');

    content.innerHTML = `
        <div class="document-item">
            <i class="fas fa-file-pdf"></i>
            <span>E-Waybill</span>
            <a href="#" class="btn btn-sm btn-primary">View</a>
        </div>
        <div class="document-item">
            <i class="fas fa-file-image"></i>
            <span>Proof of Delivery</span>
            <span class="text-muted">Not available</span>
        </div>
    `;

    panel.style.display = 'block';
}

function closeDocumentPanel() {
    document.getElementById('document-panel').style.display = 'none';
}

function pingDriver(shipmentId) {
    // Show feedback
    const btn = event.target.closest('.action-btn');
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.add('success');

    // Reset after 2 seconds
    setTimeout(() => {
        btn.innerHTML = originalIcon;
        btn.classList.remove('success');
    }, 2000);

    // Placeholder - implement actual ping
    console.log('Ping driver for shipment:', shipmentId);
}

// Utility functions
function showLoadingState() {
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('active-shipments-table').style.display = 'none';
}

function hideLoadingState() {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('active-shipments-table').style.display = 'table';
}

function showError(message) {
    hideLoadingState();
    const tbody = document.getElementById('shipments-tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="6" class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                ${message}
            </td>
        </tr>
    `;
}

// Clean up when tab is unloaded
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});
</script>

<style>
/* KPI Strip */
.kpi-strip {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.kpi-card {
    flex: 1;
    min-width: 120px;
    background: var(--card-bg, #fff);
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.kpi-card.active {
    border-color: var(--primary-blue);
}

.kpi-card.bg-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.kpi-card.bg-warning { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
.kpi-card.bg-danger { background: linear-gradient(135deg, #dc3545, #e83e8c); color: white; }

.kpi-icon {
    font-size: 24px;
    opacity: 0.9;
}

.kpi-content {
    flex: 1;
}

.kpi-value {
    font-size: 28px;
    font-weight: bold;
    line-height: 1;
}

.kpi-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Table Styles */
.table-container {
    background: var(--card-bg, #fff);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: var(--secondary-bg, #f8f9fa);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary, #333);
    border-bottom: 2px solid var(--border-color, #dee2e6);
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    vertical-align: top;
}

/* Driver Info */
.driver-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.driver-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.driver-details {
    flex: 1;
}

.driver-name {
    font-weight: 600;
    color: var(--text-primary, #333);
}

.truck-plate {
    font-size: 12px;
    color: var(--text-secondary, #666);
}

.phone-link {
    color: var(--primary-blue);
    text-decoration: none;
    margin-left: 5px;
}

/* Route Progress */
.route-progress {
    min-width: 200px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--border-color, #dee2e6);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 12px;
    color: var(--text-secondary, #666);
}

/* Status Badges */
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.danger { background: #f8d7da; color: #721c24; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

.action-btn {
    padding: 6px 8px;
    border: none;
    background: var(--secondary-bg, #f8f9fa);
    color: var(--text-secondary, #666);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: var(--primary-blue);
    color: white;
}

.action-btn.success {
    background: #28a745;
    color: white;
}

/* Document Panel */
.document-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: 350px;
    height: 100%;
    background: var(--card-bg, #fff);
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.document-panel.active {
    transform: translateX(0);
}

.panel-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--text-secondary, #666);
}

.panel-content {
    padding: 20px;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color, #dee2e6);
}

/* Loading and Empty States */
.loading-state, .empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary, #666);
}

.loading-state i, .empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: var(--primary-blue);
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--text-primary, #333);
}

/* Responsive */
@media (max-width: 768px) {
    .kpi-strip {
        flex-direction: column;
    }

    .kpi-card {
        min-width: auto;
    }

    .data-table {
        font-size: 14px;
    }

    .data-table th, .data-table td {
        padding: 10px;
    }

    .driver-info {
        flex-direction: column;
        text-align: center;
    }

    .route-progress {
        min-width: auto;
    }
}
</style>
