class TrackingSystem {
    constructor() {
        this.map = null;
        this.marker = null;
        this.routeLine = null;
        this.shipmentData = null;
        this.watchId = null;

        this.initMap();
        this.loadRecentShipments();
    }

    initMap() {
        // Initialize Leaflet map
        this.map = L.map('tracking-map').setView([0, 0], 2);

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);

        // Add satellite layer option
        this.satelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google'
        });
    }

    async trackShipment(trackingNumber) {
        try {
            const response = await fetch('../api/tracking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tracking_number: trackingNumber })
            });

            const data = await response.json();

            if (data.success) {
                this.shipmentData = data.shipment;
                this.displayTrackingInfo();
                this.updateMap();
                this.startLiveTracking();

                // Save to recent searches
                this.saveToRecent(trackingNumber, data.shipment);

                return data;
            } else {
                throw new Error(data.error || 'Tracking number not found');
            }
        } catch (error) {
            this.showError(error.message);
            return null;
        }
    }

    displayTrackingInfo() {
        const shipment = this.shipmentData;

        // Update basic info
        document.getElementById('detail-tracking').textContent = shipment.tracking_number;
        document.getElementById('detail-status').textContent = shipment.status;
        document.getElementById('detail-status').className = `status-badge ${shipment.status}`;
        document.getElementById('detail-eta').textContent = shipment.estimated_delivery;
        document.getElementById('detail-service').textContent = shipment.service_type;
        document.getElementById('detail-weight').textContent = `${shipment.weight} kg`;
        document.getElementById('detail-dimensions').textContent = `${shipment.length}×${shipment.width}×${shipment.height} cm`;

        // Update driver info
        if (shipment.driver) {
            document.getElementById('driver-name').textContent = shipment.driver.name;
            document.getElementById('driver-phone').textContent = shipment.driver.phone;
            document.getElementById('driver-vehicle').textContent = shipment.driver.vehicle;
            document.getElementById('driver-id').textContent = shipment.driver.id;
        }

        // Update timeline
        this.updateTimeline(shipment.history);
    }

    updateMap() {
        const shipment = this.shipmentData;

        // Clear previous markers
        if (this.marker) {
            this.map.removeLayer(this.marker);
        }
        if (this.routeLine) {
            this.map.removeLayer(this.routeLine);
        }

        // Add current location marker
        const latLng = [shipment.current_lat, shipment.current_lng];
        this.marker = L.marker(latLng).addTo(this.map);
        this.marker.bindPopup(`
            <b>Current Location</b><br>
            ${shipment.current_location}<br>
            Updated: ${shipment.last_updated}
        `).openPopup();

        // Center map on marker
        this.map.setView(latLng, 12);

        // Draw route if available
        if (shipment.route && shipment.route.length > 0) {
            const routePoints = shipment.route.map(point => [point.lat, point.lng]);
            this.routeLine = L.polyline(routePoints, { color: '#2563eb' }).addTo(this.map);

            // Fit map to show entire route
            this.map.fitBounds(this.routeLine.getBounds());
        }

        // Add pickup and delivery markers
        L.marker([shipment.pickup_lat, shipment.pickup_lng])
            .addTo(this.map)
            .bindPopup(`<b>Pickup:</b><br>${shipment.pickup_address}`)
            .openPopup();

        L.marker([shipment.delivery_lat, shipment.delivery_lng])
            .addTo(this.map)
            .bindPopup(`<b>Delivery:</b><br>${shipment.delivery_address}`)
            .openPopup();
    }

    startLiveTracking() {
        // Stop any existing tracking
        if (this.watchId) {
            this.stopLiveTracking();
        }

        // Start WebSocket connection for live updates
        this.connectWebSocket();

        // Or use polling for live updates
        this.watchId = setInterval(() => {
            this.updateLivePosition();
        }, 30000); // Update every 30 seconds
    }

    connectWebSocket() {
        const ws = new WebSocket('wss://freightexpress.com/ws/tracking');

        ws.onopen = () => {
            console.log('WebSocket connected');
            ws.send(JSON.stringify({
                action: 'subscribe',
                tracking_number: this.shipmentData.tracking_number
            }));
        };

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);

            if (data.type === 'location_update') {
                this.updateLivePosition(data.location);
            } else if (data.type === 'status_update') {
                this.updateStatus(data.status);
            }
        };

        ws.onclose = () => {
            console.log('WebSocket disconnected');
            // Attempt to reconnect
            setTimeout(() => this.connectWebSocket(), 5000);
        };
    }

    updateLivePosition(location) {
        if (!location && this.shipmentData) {
            // Simulate movement for demo
            const lat = this.shipmentData.current_lat + (Math.random() - 0.5) * 0.01;
            const lng = this.shipmentData.current_lng + (Math.random() - 0.5) * 0.01;
            location = { lat, lng };
        }

        if (location && this.marker) {
            // Smooth transition
            const oldLatLng = this.marker.getLatLng();
            const newLatLng = L.latLng(location.lat, location.lng);

            this.marker.setLatLng(newLatLng);

            // Update polyline if route tracking
            if (this.routeLine) {
                const currentPath = this.routeLine.getLatLngs();
                currentPath.push(newLatLng);
                this.routeLine.setLatLngs(currentPath);
            }

            // Move map slightly to follow marker
            if (this.map.getZoom() > 10) {
                this.map.panTo(newLatLng);
            }
        }
    }

    updateTimeline(history) {
        const timeline = document.getElementById('shipment-timeline');
        timeline.innerHTML = '';

        const statusOrder = {
            'label_created': 1,
            'picked_up': 2,
            'in_transit': 3,
            'out_for_delivery': 4,
            'delivered': 5
        };

        history.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));

        history.forEach((event, index) => {
            const timelineItem = document.createElement('div');
            timelineItem.className = 'timeline-item';

            const isActive = index === history.length - 1;
            const isCompleted = index < history.length - 1;

            timelineItem.innerHTML = `
                <div class="timeline-marker ${isActive ? 'active' : ''} ${isCompleted ? 'completed' : ''}">
                    <i class="fas ${this.getStatusIcon(event.status)}"></i>
                </div>
                <div class="timeline-content">
                    <h4>${this.getStatusText(event.status)}</h4>
                    <p>${event.location || ''}</p>
                    <span class="timeline-time">${this.formatDateTime(event.timestamp)}</span>
                </div>
            `;

            timeline.appendChild(timelineItem);
        });
    }

    getStatusIcon(status) {
        const icons = {
            'label_created': 'fa-tag',
            'picked_up': 'fa-truck-pickup',
            'in_transit': 'fa-truck-moving',
            'out_for_delivery': 'fa-truck-loading',
            'delivered': 'fa-check-circle',
            'delayed': 'fa-exclamation-triangle'
        };
        return icons[status] || 'fa-circle';
    }

    getStatusText(status) {
        const texts = {
            'label_created': 'Label Created',
            'picked_up': 'Picked Up',
            'in_transit': 'In Transit',
            'out_for_delivery': 'Out for Delivery',
            'delivered': 'Delivered',
            'delayed': 'Delayed'
        };
        return texts[status] || status;
    }

    formatDateTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString();
    }

    saveToRecent(trackingNumber, shipment) {
        let recent = JSON.parse(localStorage.getItem('recentShipments') || '[]');

        // Remove if already exists
        recent = recent.filter(s => s.tracking_number !== trackingNumber);

        // Add to beginning
        recent.unshift({
            tracking_number: trackingNumber,
            status: shipment.status,
            service_type: shipment.service_type,
            last_updated: new Date().toISOString(),
            destination: shipment.delivery_address
        });

        // Keep only last 10
        recent = recent.slice(0, 10);

        localStorage.setItem('recentShipments', JSON.stringify(recent));
    }

    loadRecentShipments() {
        const recent = JSON.parse(localStorage.getItem('recentShipments') || '[]');
        return recent;
    }

    shareLocation() {
        if (navigator.share && this.shipmentData) {
            navigator.share({
                title: 'Track My Shipment',
                text: `Track my FreightExpress shipment: ${this.shipmentData.tracking_number}`,
                url: `https://freightexpress.com/track/${this.shipmentData.tracking_number}`
            });
        } else {
            // Copy to clipboard
            const url = `https://freightexpress.com/track/${this.shipmentData.tracking_number}`;
            navigator.clipboard.writeText(url);
            alert('Tracking link copied to clipboard!');
        }
    }

    stopLiveTracking() {
        if (this.watchId) {
            clearInterval(this.watchId);
            this.watchId = null;
        }
    }

    toggleSatellite() {
        if (this.map.hasLayer(this.satelliteLayer)) {
            this.map.removeLayer(this.satelliteLayer);
        } else {
            this.satelliteLayer.addTo(this.map);
        }
    }
}

// Global tracking instance
const trackingSystem = new TrackingSystem();

// Global functions for HTML onclick handlers
function trackShipment() {
    const input = document.getElementById('tracking-input').value.trim();
    if (input) {
        trackingSystem.trackShipment(input);
    } else {
        alert('Please enter a tracking number');
    }
}

function centerMap() {
    if (trackingSystem.map && trackingSystem.shipmentData) {
        trackingSystem.map.setView(
            [trackingSystem.shipmentData.current_lat, trackingSystem.shipmentData.current_lng],
            12
        );
    }
}

function shareLocation() {
    trackingSystem.shareLocation();
}

function toggleSatellite() {
    trackingSystem.toggleSatellite();
}