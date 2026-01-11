// Dashboard Maps - Mapbox Integration for Fleet Tracking
// Handles live fleet map rendering and GPS tracking visualization

class DashboardMaps {
    constructor() {
        this.map = null;
        this.markers = new Map();
        this.shipmentData = [];
        this.mapboxToken = 'pk.eyJ1IjoibG9nZXNoc3ZjIiwiYSI6ImNtM3F5dWF5ZjBkeW0yanF3Z3p5ZG5zZ3gifQ.8Q8Q8Q8Q8Q8Q8Q8Q8Q8Q8Q8Q'; // Replace with actual token
        this.defaultCenter = [38.7578, 8.9806]; // Addis Ababa coordinates
        this.defaultZoom = 6;
        this.updateInterval = 30000; // 30 seconds
        this.updateTimer = null;
    }

    init() {
        this.loadMapbox();
        this.setupEventListeners();
    }

    loadMapbox() {
        // Check if Mapbox GL JS is already loaded
        if (typeof mapboxgl !== 'undefined') {
            this.initializeMap();
        } else {
            // Load Mapbox GL JS dynamically
            const script = document.createElement('script');
            script.src = 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js';
            script.onload = () => this.initializeMap();
            script.onerror = () => this.showMapError('Failed to load map library');
            document.head.appendChild(script);

            // Load CSS
            const link = document.createElement('link');
            link.href = 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css';
            link.rel = 'stylesheet';
            document.head.appendChild(link);
        }
    }

    initializeMap() {
        try {
            mapboxgl.accessToken = this.mapboxToken;

            const mapContainer = document.getElementById('fleet-map');
            if (!mapContainer) {
                console.error('Fleet map container not found');
                return;
            }

            this.map = new mapboxgl.Map({
                container: 'fleet-map',
                style: 'mapbox://styles/mapbox/streets-v12',
                center: this.defaultCenter,
                zoom: this.defaultZoom,
                attributionControl: false
            });

            // Add navigation controls
            this.map.addControl(new mapboxgl.NavigationControl(), 'top-right');
            this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

            // Wait for map to load before adding data
            this.map.on('load', () => {
                this.loadShipmentData();
                this.startRealTimeUpdates();
            });

            // Handle map errors
            this.map.on('error', (e) => {
                console.error('Map error:', e);
                this.showMapError('Map loading failed');
            });

        } catch (error) {
            console.error('Map initialization error:', error);
            this.showMapError('Map initialization failed');
        }
    }

    loadShipmentData() {
        // Get shipment data from the hidden JSON script
        const dashboardData = document.getElementById('dashboard-data');
        if (dashboardData) {
            try {
                const data = JSON.parse(dashboardData.textContent);
                this.shipmentData = data.active_shipments || [];
                this.updateMapMarkers();
            } catch (error) {
                console.error('Error parsing shipment data:', error);
            }
        }
    }

    updateMapMarkers() {
        if (!this.map || !this.shipmentData.length) return;

        // Clear existing markers
        this.clearMarkers();

        // Fit bounds to show all shipments
        const bounds = new mapboxgl.LngLatBounds();

        this.shipmentData.forEach(shipment => {
            if (shipment.current_lat && shipment.current_lng) {
                const marker = this.createShipmentMarker(shipment);
                bounds.extend([shipment.current_lng, shipment.current_lat]);
            }
        });

        // Fit map to show all markers if we have any
        if (!bounds.isEmpty()) {
            this.map.fitBounds(bounds, {
                padding: 50,
                maxZoom: 12
            });
        }
    }

    createShipmentMarker(shipment) {
        const el = document.createElement('div');
        el.className = 'shipment-marker';

        // Determine marker color based on status and timeliness
        let markerColor = '#4CAF50'; // Green - on time
        let pulseClass = '';

        if (shipment.minutes_since_update > 60) {
            markerColor = '#F44336'; // Red - stale data
            pulseClass = 'stale';
        } else if (shipment.minutes_since_update > 30) {
            markerColor = '#FF9800'; // Orange - delayed update
            pulseClass = 'delayed';
        }

        // Create marker element
        el.innerHTML = `
            <div class="marker-pin ${pulseClass}" style="background-color: ${markerColor};">
                <i class="fas fa-truck"></i>
            </div>
            <div class="marker-info">
                <div class="marker-title">${shipment.cargo_id}</div>
                <div class="marker-status">${this.formatStatus(shipment.status)}</div>
                <div class="marker-time">${shipment.minutes_since_update} min ago</div>
            </div>
        `;

        // Create popup
        const popup = new mapboxgl.Popup({
            offset: 25,
            closeButton: false,
            className: 'shipment-popup'
        }).setHTML(this.createPopupContent(shipment));

        // Create marker
        const marker = new mapboxgl.Marker(el)
            .setLngLat([shipment.current_lng, shipment.current_lat])
            .setPopup(popup)
            .addTo(this.map);

        // Store marker reference
        this.markers.set(shipment.id, marker);

        return marker;
    }

    createPopupContent(shipment) {
        return `
            <div class="shipment-popup-content">
                <h4>${shipment.cargo_id}</h4>
                <div class="popup-details">
                    <div class="detail-row">
                        <span class="label">Description:</span>
                        <span class="value">${shipment.description}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status:</span>
                        <span class="value">${this.formatStatus(shipment.status)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Driver:</span>
                        <span class="value">${shipment.driver_name || 'Not assigned'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Last Update:</span>
                        <span class="value">${shipment.minutes_since_update} minutes ago</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Route:</span>
                        <span class="value">${shipment.pickup_location} → ${shipment.dropoff_location}</span>
                    </div>
                </div>
                <div class="popup-actions">
                    <button class="btn btn-primary" onclick="dashboardMaps.trackShipment(${shipment.id})">
                        <i class="fas fa-route"></i> Track
                    </button>
                    <button class="btn btn-secondary" onclick="dashboardMaps.callDriver('${shipment.driver_phone}')">
                        <i class="fas fa-phone"></i> Call
                    </button>
                </div>
            </div>
        `;
    }

    formatStatus(status) {
        if (!status) return 'Unknown';

        const statusMap = {
            'in_transit': 'In Transit',
            'moving': 'Moving',
            'en_route': 'En Route',
            'active': 'Active',
            'picked_up': 'Picked Up',
            'delivered': 'Delivered',
            'stalled': 'Stalled'
        };

        return statusMap[status.toLowerCase()] || status;
    }

    clearMarkers() {
        this.markers.forEach(marker => marker.remove());
        this.markers.clear();
    }

    startRealTimeUpdates() {
        // Update shipment data every 30 seconds
        this.updateTimer = setInterval(() => {
            this.refreshShipmentData();
        }, this.updateInterval);
    }

    stopRealTimeUpdates() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
    }

    async refreshShipmentData() {
        try {
            const response = await fetch('api/get_active_shipments.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.shipments) {
                    this.shipmentData = data.shipments;
                    this.updateMapMarkers();
                }
            }
        } catch (error) {
            console.error('Error refreshing shipment data:', error);
        }
    }

    trackShipment(shipmentId) {
        // Open tracking modal or redirect to tracking page
        console.log('Tracking shipment:', shipmentId);
        // Implementation depends on tracking system
    }

    callDriver(phoneNumber) {
        if (phoneNumber) {
            window.location.href = `tel:${phoneNumber}`;
        }
    }

    showMapError(message) {
        const mapContainer = document.getElementById('fleet-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="map-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                    <button onclick="dashboardMaps.retryMapLoad()" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </div>
            `;
        }
    }

    retryMapLoad() {
        const mapContainer = document.getElementById('fleet-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>Loading live fleet map...</p>
                </div>
            `;
        }
        this.initializeMap();
    }

    setupEventListeners() {
        // Handle window resize
        window.addEventListener('resize', () => {
            if (this.map) {
                this.map.resize();
            }
        });

        // Handle visibility change (tab switching)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopRealTimeUpdates();
            } else {
                this.startRealTimeUpdates();
            }
        });
    }

    destroy() {
        this.stopRealTimeUpdates();
        this.clearMarkers();
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }
}

// Global instance
const dashboardMaps = new DashboardMaps();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the dashboard page
    if (document.getElementById('fleet-map')) {
        dashboardMaps.init();
    }
});

// CSS for map markers and popups
const mapStyles = `
<style>
.shipment-marker {
    position: relative;
    cursor: pointer;
}

.marker-pin {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    transition: transform 0.2s ease;
    position: relative;
}

.marker-pin:hover {
    transform: scale(1.1);
}

.marker-pin.stale {
    animation: pulse-red 2s infinite;
}

.marker-pin.delayed {
    animation: pulse-orange 2s infinite;
}

.marker-info {
    position: absolute;
    bottom: 35px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 8px 12px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    white-space: nowrap;
    display: none;
    font-size: 12px;
}

.shipment-marker:hover .marker-info {
    display: block;
}

.marker-title {
    font-weight: 600;
    color: var(--primary-blue);
    margin-bottom: 2px;
}

.marker-status {
    color: var(--dark-gray);
    margin-bottom: 2px;
}

.marker-time {
    color: var(--text-light);
    font-size: 11px;
}

.shipment-popup .mapboxgl-popup-content {
    padding: 0;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.shipment-popup-content {
    padding: 20px;
    max-width: 300px;
}

.shipment-popup-content h4 {
    margin: 0 0 15px 0;
    color: var(--primary-blue);
    font-size: 16px;
    font-weight: 600;
}

.popup-details {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    margin-bottom: 8px;
    font-size: 13px;
}

.detail-row .label {
    font-weight: 600;
    color: var(--dark-gray);
    min-width: 70px;
    margin-right: 8px;
}

.detail-row .value {
    color: var(--text-light);
    flex: 1;
}

.popup-actions {
    display: flex;
    gap: 8px;
}

.popup-actions .btn {
    flex: 1;
    font-size: 12px;
    padding: 8px 12px;
}

.map-error, .map-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-light);
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.map-error i, .map-placeholder i {
    font-size: 48px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.map-error p, .map-placeholder p {
    margin: 0;
    font-size: 14px;
    text-align: center;
}

@keyframes pulse-red {
    0% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(244, 67, 54, 0); }
    100% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
}

@keyframes pulse-orange {
    0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .marker-info {
        display: none; /* Hide on mobile to avoid clutter */
    }

    .shipment-popup-content {
        max-width: 250px;
        padding: 15px;
    }

    .popup-actions {
        flex-direction: column;
    }
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', mapStyles);
