// ===== REGIONS SHOW PAGE JAVASCRIPT =====
// Advanced interactive features for region detail page

class RegionShow {
    constructor() {
        this.region = window.regionData?.region || null;
        this.sectors = window.regionData?.sectors || [];
        this.stats = window.regionData?.stats || {};
        this.photos = window.regionData?.photos || [];
        this.currentPhotoIndex = 0;

        this.map = null;
        this.mapLayers = {
            sectors: null,
            hiking: null,
            parking: null
        };
        this.routingControl = null;
        this.activeMapLayer = 'sectors';
        this.isRoutingActive = false;

        this.weatherCache = new Map();
        this.gallerySwiper = null;

        // API Keys
        this.weatherApiKey = window.regionData?.weatherApiKey;
        this.swisstopoApiKey = window.regionData?.swisstopoApiKey;
        this.routingApiKey = window.regionData?.routingApiKey;

        this.init();
    }

    async init() {
        if (!this.region) {
            console.error('No region data found');
            return;
        }

        this.setupEventListeners();
        this.initializeMap();
        this.initializeWeatherWidget();
        this.initializePhotoGallery();
        this.loadUpcomingEvents();

        console.log('RegionShow initialized for:', this.region.name);
    }

    setupEventListeners() {
        // Map layer buttons
        const mapBtns = ['btn-sectors', 'btn-hiking', 'btn-parking', 'btn-route'];
        mapBtns.forEach(btnId => {
            const btn = document.getElementById(btnId);
            if (btn) {
                btn.addEventListener('click', () => {
                    if (btnId === 'btn-route') {
                        this.toggleRouting();
                    } else {
                        const layer = btnId.replace('btn-', '');
                        this.showMapLayer(layer);
                    }
                });
            }
        });

        // Photo gallery navigation
        document.addEventListener('keydown', (e) => {
            if (document.getElementById('photo-modal').classList.contains('active')) {
                if (e.key === 'ArrowLeft') this.previousPhoto();
                if (e.key === 'ArrowRight') this.nextPhoto();
                if (e.key === 'Escape') this.closePhotoModal();
            }
        });

        // Close modals on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Window resize handler
        window.addEventListener('resize', () => {
            if (this.map) {
                setTimeout(() => this.map.invalidateSize(), 250);
            }
            if (this.gallerySwiper) {
                this.gallerySwiper.update();
            }
        });
    }

    async initializeMap() {
        try {
            // Setup Swiss projections
            this.setupSwissProjections();

            // Initialize map
            const center = this.region.coordinates_lat && this.region.coordinates_lng
                ? [this.region.coordinates_lat, this.region.coordinates_lng]
                : [46.8182, 8.2275];

            this.map = L.map('region-map', {
                center: center,
                zoom: 12,
                maxZoom: 18,
                minZoom: 8
            });

            // Add base layers
            await this.addSwissBaseLayers();

            // Add default layer (sectors)
            this.showMapLayer('sectors');

            // Setup map controls
            this.setupMapControls();

            console.log('Map initialized successfully');

        } catch (error) {
            console.error('Error initializing map:', error);
            this.showMapError();
        }
    }

    setupSwissProjections() {
        if (window.proj4) {
            proj4.defs("EPSG:2056", "+proj=somerc +lat_0=46.95240555555556 +lon_0=7.439583333333333 +k_0=1 +x_0=2600000 +y_0=1200000 +ellps=bessel +towgs84=674.374,15.056,405.346,0,0,0,0 +units=m +no_defs");
            proj4.defs("EPSG:21781", "+proj=somerc +lat_0=46.95240555555556 +lon_0=7.439583333333333 +k_0=1 +x_0=600000 +y_0=200000 +ellps=bessel +towgs84=674.4,15.1,405.3,0,0,0,0 +units=m +no_defs");
        }
    }

    async addSwissBaseLayers() {
        const baseLayers = {};

        if (this.swisstopoApiKey) {
            // Swisstopo layers
            baseLayers['Carte nationale'] = L.tileLayer(
                'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg',
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );

            baseLayers['Carte satellite'] = L.tileLayer(
                'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg',
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );

            baseLayers['Carte de randonnée'] = L.tileLayer(
                'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe-pk25.noscale/default/current/3857/{z}/{x}/{y}.jpeg',
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );
        } else {
            // Fallback layers
            baseLayers['OpenStreetMap'] = L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                }
            );

            baseLayers['Terrain'] = L.tileLayer(
                'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                {
                    attribution: '© OpenTopoMap contributors',
                    maxZoom: 17
                }
            );
        }

        // Add first layer as default
        const firstLayer = Object.values(baseLayers)[0];
        firstLayer.addTo(this.map);

        // Add layer control
        L.control.layers(baseLayers).addTo(this.map);
    }

    setupMapControls() {
        // Add scale control
        L.control.scale({
            metric: true,
            imperial: false,
            position: 'bottomleft'
        }).addTo(this.map);

        // Add fullscreen control
        this.addFullscreenControl();

        // Add location control
        this.addLocationControl();

        // Add custom styles
        this.addMapStyles();
    }

    addFullscreenControl() {
        const FullscreenControl = L.Control.extend({
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.innerHTML = '<a href="#" title="Plein écran"><i class="fas fa-expand"></i></a>';

                container.onclick = (e) => {
                    e.preventDefault();
                    const mapContainer = this._map.getContainer();
                    if (mapContainer.requestFullscreen) {
                        mapContainer.requestFullscreen();
                    }
                };

                return container;
            }
        });

        new FullscreenControl({ position: 'topright' }).addTo(this.map);
    }

    addLocationControl() {
        const LocationControl = L.Control.extend({
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.innerHTML = '<a href="#" title="Ma position"><i class="fas fa-crosshairs"></i></a>';

                container.onclick = (e) => {
                    e.preventDefault();
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition((position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            this._map.setView([lat, lng], 15);

                            L.marker([lat, lng])
                                .addTo(this._map)
                                .bindPopup('Votre position')
                                .openPopup();
                        });
                    }
                };

                return container;
            }
        });

        new LocationControl({ position: 'topright' }).addTo(this.map);
    }

    showMapLayer(layerType) {
        // Update active button
        document.querySelectorAll('.map-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(`btn-${layerType}`)?.classList.add('active');

        // Clear existing layers
        this.clearMapLayers();

        // Show selected layer
        this.activeMapLayer = layerType;

        switch (layerType) {
            case 'sectors':
                this.showSectorsLayer();
                break;
            case 'hiking':
                this.showHikingLayer();
                break;
            case 'parking':
                this.showParkingLayer();
                break;
        }
    }

    showSectorsLayer() {
        if (!this.sectors || this.sectors.length === 0) return;

        const markers = [];

        this.sectors.forEach(sector => {
            if (sector.coordinates_lat && sector.coordinates_lng) {
                const marker = this.createSectorMarker(sector);
                markers.push(marker);
                marker.addTo(this.map);
            }
        });

        this.mapLayers.sectors = L.layerGroup(markers);

        // Fit map to show all sectors
        if (markers.length > 0) {
            const group = new L.featureGroup(markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    createSectorMarker(sector) {
        const icon = L.divIcon({
            html: this.getSectorIcon(sector),
            className: 'custom-sector-marker',
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });

        const marker = L.marker([sector.coordinates_lat, sector.coordinates_lng], { icon });

        const popupContent = this.createSectorPopup(sector);
        marker.bindPopup(popupContent, {
            maxWidth: 300,
            className: 'sector-popup'
        });

        marker.on('click', () => {
            this.showSectorInfo(sector);
        });

        return marker;
    }

    getSectorIcon(sector) {
        const routeCount = sector.routes_count || 0;
        const difficulty = this.getSectorDifficulty(sector);
        const color = this.getDifficultyColor(difficulty);

        return `
            <div class="sector-marker-icon" style="background: ${color};">
                <i class="fas fa-climbing"></i>
                <span class="sector-count">${routeCount}</span>
            </div>
        `;
    }

    getSectorDifficulty(sector) {
        // Determine difficulty based on sector's route range
        const difficulty = sector.difficulty_range || sector.avg_difficulty || 0;
        if (difficulty <= 5) return 'beginner';
        if (difficulty <= 6.5) return 'intermediate';
        return 'advanced';
    }

    getDifficultyColor(difficulty) {
        const colors = {
            beginner: '#10b981',
            intermediate: '#f59e0b',
            advanced: '#ef4444'
        };
        return colors[difficulty] || '#6b7280';
    }

    createSectorPopup(sector) {
        return `
            <div class="sector-popup-content">
                <div class="popup-header">
                    <h4>${sector.name}</h4>
                    <div class="popup-meta">
                        ${sector.altitude ? `<span class="altitude">${sector.altitude}m</span>` : ''}
                        ${sector.exposure ? `<span class="exposure">${sector.exposure}</span>` : ''}
                    </div>
                </div>
                
                <div class="popup-stats">
                    <div class="popup-stat">
                        <i class="fas fa-route"></i>
                        <span>${sector.routes_count || 0} voies</span>
                    </div>
                    ${sector.access_time ? `
                        <div class="popup-stat">
                            <i class="fas fa-clock"></i>
                            <span>${sector.access_time} min</span>
                        </div>
                    ` : ''}
                    ${sector.difficulty_range ? `
                        <div class="popup-stat">
                            <i class="fas fa-chart-line"></i>
                            <span>${sector.difficulty_range}</span>
                        </div>
                    ` : ''}
                </div>
                
                ${sector.description ? `
                    <div class="popup-description">
                        ${this.truncateText(sector.description, 100)}
                    </div>
                ` : ''}
                
                <div class="popup-actions">
                    <a href="/sectors/${sector.id}" class="popup-btn primary">
                        <i class="fas fa-eye"></i> Voir les voies
                    </a>
                    <button onclick="regionShow.calculateRouteTo(${sector.coordinates_lat}, ${sector.coordinates_lng})" class="popup-btn secondary">
                        <i class="fas fa-route"></i> Itinéraire
                    </button>
                </div>
            </div>
        `;
    }

    showHikingLayer() {
        if (this.swisstopoApiKey) {
            // Add Swiss hiking trails layer
            const hikingLayer = L.tileLayer(
                'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swisstlm3d-wanderwege/default/current/3857/{z}/{x}/{y}.png',
                {
                    attribution: '© swisstopo',
                    opacity: 0.7
                }
            );

            hikingLayer.addTo(this.map);
            this.mapLayers.hiking = hikingLayer;

            this.showNotification('Sentiers de randonnée affichés', 'info');
        } else {
            this.showNotification('Couche sentiers non disponible', 'warning');
        }
    }

    showParkingLayer() {
        // Add parking markers from region data
        const parkingData = this.region.parking_areas || [];
        const markers = [];

        parkingData.forEach(parking => {
            if (parking.coordinates_lat && parking.coordinates_lng) {
                const marker = this.createParkingMarker(parking);
                markers.push(marker);
                marker.addTo(this.map);
            }
        });

        this.mapLayers.parking = L.layerGroup(markers);

        if (markers.length === 0) {
            this.showNotification('Aucun parking référencé', 'info');
        }
    }

    createParkingMarker(parking) {
        const icon = L.divIcon({
            html: '<div class="parking-marker-icon"><i class="fas fa-parking"></i></div>',
            className: 'custom-parking-marker',
            iconSize: [25, 25],
            iconAnchor: [12, 25],
            popupAnchor: [0, -25]
        });

        const marker = L.marker([parking.coordinates_lat, parking.coordinates_lng], { icon });

        const popupContent = `
            <div class="parking-popup-content">
                <h4>${parking.name}</h4>
                ${parking.description ? `<p>${parking.description}</p>` : ''}
                <div class="parking-info">
                    ${parking.capacity ? `<div><i class="fas fa-car"></i> ${parking.capacity} places</div>` : ''}
                    ${parking.free ? '<div><i class="fas fa-check"></i> Gratuit</div>' : '<div><i class="fas fa-euro-sign"></i> Payant</div>'}
                </div>
                <button onclick="regionShow.calculateRouteTo(${parking.coordinates_lat}, ${parking.coordinates_lng})" class="popup-btn primary">
                    <i class="fas fa-route"></i> Itinéraire
                </button>
            </div>
        `;

        marker.bindPopup(popupContent, { className: 'parking-popup' });

        return marker;
    }

    clearMapLayers() {
        Object.values(this.mapLayers).forEach(layer => {
            if (layer) {
                this.map.removeLayer(layer);
            }
        });

        // Clear all markers
        this.map.eachLayer(layer => {
            if (layer instanceof L.Marker && !layer.options.permanent) {
                this.map.removeLayer(layer);
            }
        });
    }

    toggleRouting() {
        const panel = document.getElementById('routing-panel');
        const btn = document.getElementById('btn-route');

        if (this.isRoutingActive) {
            this.closeRouting();
        } else {
            panel.classList.add('active');
            btn.classList.add('active');
            this.isRoutingActive = true;
            this.initializeRoutingInputs();
        }
    }

    closeRouting() {
        const panel = document.getElementById('routing-panel');
        const btn = document.getElementById('btn-route');

        panel.classList.remove('active');
        btn.classList.remove('active');
        this.isRoutingActive = false;

        if (this.routingControl) {
            this.map.removeControl(this.routingControl);
            this.routingControl = null;
        }
    }

    initializeRoutingInputs() {
        const destinationSelect = document.getElementById('route-destination');

        // Populate destination dropdown with sectors
        if (destinationSelect && this.sectors.length > 0) {
            destinationSelect.innerHTML = '<option value="">Sélectionner un secteur</option>';
            this.sectors.forEach(sector => {
                if (sector.coordinates_lat && sector.coordinates_lng) {
                    const option = document.createElement('option');
                    option.value = `${sector.coordinates_lat},${sector.coordinates_lng}`;
                    option.textContent = sector.name;
                    destinationSelect.appendChild(option);
                }
            });
        }
    }

    useCurrentLocation() {
        if (!navigator.geolocation) {
            this.showNotification('Géolocalisation non supportée', 'error');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const input = document.getElementById('route-start');
                input.value = `${position.coords.latitude}, ${position.coords.longitude}`;
            },
            (error) => {
                this.showNotification('Impossible d\'obtenir votre position', 'error');
            }
        );
    }

    async calculateRoute() {
        const startInput = document.getElementById('route-start');
        const destinationSelect = document.getElementById('route-destination');
        const routeType = document.querySelector('input[name="route-type"]:checked')?.value || 'driving';

        if (!startInput.value || !destinationSelect.value) {
            this.showNotification('Veuillez remplir le point de départ et la destination', 'warning');
            return;
        }

        try {
            this.showRouteLoading();

            const [startLat, startLng] = this.parseCoordinates(startInput.value);
            const [destLat, destLng] = destinationSelect.value.split(',').map(Number);

            const routeData = await this.fetchRouteData(startLat, startLng, destLat, destLng, routeType);
            this.displayRoute(routeData, routeType);

        } catch (error) {
            console.error('Routing error:', error);
            this.showNotification('Erreur lors du calcul de l\'itinéraire', 'error');
        } finally {
            this.hideRouteLoading();
        }
    }

    parseCoordinates(input) {
        // Try to parse various coordinate formats
        const cleaned = input.trim().replace(/[°'"″′]/g, '');

        // Check if it's already lat,lng format
        if (cleaned.includes(',')) {
            const [lat, lng] = cleaned.split(',').map(Number);
            if (!isNaN(lat) && !isNaN(lng)) {
                return [lat, lng];
            }
        }

        // Try to geocode the address
        throw new Error('Invalid coordinate format');
    }

    async fetchRouteData(startLat, startLng, destLat, destLng, profile) {
        if (!this.routingApiKey) {
            // Fallback to simple routing without API
            return this.createSimpleRoute(startLat, startLng, destLat, destLng);
        }

        const profileMap = {
            driving: 'driving-car',
            walking: 'foot-walking',
            cycling: 'cycling-regular'
        };

        const response = await fetch(
            `https://api.openrouteservice.org/v2/directions/${profileMap[profile]}?` +
            `api_key=${this.routingApiKey}&` +
            `start=${startLng},${startLat}&` +
            `end=${destLng},${destLat}&` +
            `format=geojson&` +
            `instructions=true&` +
            `elevation=true`
        );

        if (!response.ok) {
            throw new Error('Routing API request failed');
        }

        return await response.json();
    }

    createSimpleRoute(startLat, startLng, destLat, destLng) {
        // Create a simple direct route for fallback
        const distance = this.calculateDistance(startLat, startLng, destLat, destLng);

        return {
            features: [{
                geometry: {
                    coordinates: [[startLng, startLat], [destLng, destLat]],
                    type: 'LineString'
                },
                properties: {
                    summary: {
                        distance: distance * 1000, // Convert to meters
                        duration: distance * 60 // Rough estimate: 1km per minute walking
                    }
                }
            }]
        };
    }

    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371; // Earth's radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    displayRoute(routeData, routeType) {
        if (this.routingControl) {
            this.map.removeControl(this.routingControl);
        }

        const route = routeData.features[0];
        const coordinates = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);

        // Draw route on map
        const routeLine = L.polyline(coordinates, {
            color: '#667eea',
            weight: 4,
            opacity: 0.8
        }).addTo(this.map);

        // Fit map to route
        this.map.fitBounds(routeLine.getBounds().pad(0.1));

        // Display route info
        const distance = (route.properties.summary.distance / 1000).toFixed(2);
        const duration = this.formatDuration(route.properties.summary.duration);

        this.showRouteResults(distance, duration, routeType);

        // Store for cleanup
        this.routingControl = { _routeLine: routeLine };
    }

    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);

        if (hours > 0) {
            return `${hours}h ${minutes}min`;
        }
        return `${minutes}min`;
    }

    showRouteResults(distance, duration, routeType) {
        const resultsDiv = document.getElementById('route-results');
        if (!resultsDiv) return;

        const typeLabels = {
            driving: 'En voiture',
            walking: 'À pied',
            cycling: 'À vélo'
        };

        resultsDiv.innerHTML = `
            <div class="route-summary">
                <h5>${typeLabels[routeType]}</h5>
                <div class="route-details">
                    <div class="route-detail">
                        <i class="fas fa-route"></i>
                        <span>Distance: <strong>${distance} km</strong></span>
                    </div>
                    <div class="route-detail">
                        <i class="fas fa-clock"></i>
                        <span>Durée: <strong>${duration}</strong></span>
                    </div>
                </div>
                
                <div class="route-actions">
                    <button onclick="regionShow.exportRoute()" class="route-action-btn">
                        <i class="fas fa-download"></i>
                        Exporter GPX
                    </button>
                    <button onclick="regionShow.shareRoute()" class="route-action-btn">
                        <i class="fas fa-share"></i>
                        Partager
                    </button>
                </div>
            </div>
        `;
    }

    calculateRouteTo(lat, lng) {
        // Auto-fill destination in routing panel
        const destinationSelect = document.getElementById('route-destination');
        if (destinationSelect) {
            destinationSelect.value = `${lat},${lng}`;
        }

        // Open routing panel if not active
        if (!this.isRoutingActive) {
            this.toggleRouting();
        }
    }

    showRouteLoading() {
        const resultsDiv = document.getElementById('route-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = '<div class="loading-route">Calcul de l\'itinéraire...</div>';
        }
    }

    hideRouteLoading() {
        // Loading will be replaced by results
    }

    exportRoute() {
        this.showNotification('Fonctionnalité d\'export en développement', 'info');
    }

    shareRoute() {
        if (navigator.share) {
            navigator.share({
                title: `Itinéraire vers ${this.region.name}`,
                text: `Découvrez cet itinéraire vers la région d'escalade ${this.region.name}`,
                url: window.location.href
            });
        } else {
            // Fallback to clipboard
            navigator.clipboard.writeText(window.location.href);
            this.showNotification('Lien copié dans le presse-papiers', 'success');
        }
    }

    showSectorInfo(sector) {
        const infoPanel = document.getElementById('map-info-overlay');
        const titleEl = document.getElementById('info-title');
        const contentEl = document.getElementById('info-content');

        if (!infoPanel || !titleEl || !contentEl) return;

        titleEl.textContent = sector.name;
        contentEl.innerHTML = `
            <div class="sector-info-detailed">
                ${sector.description ? `
                    <div class="info-section">
                        <h5>Description</h5>
                        <p>${sector.description}</p>
                    </div>
                ` : ''}
                
                <div class="info-section">
                    <h5>Statistiques</h5>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span>Voies:</span>
                            <strong>${sector.routes_count || 0}</strong>
                        </div>
                        ${sector.altitude ? `
                            <div class="stat-item">
                                <span>Altitude:</span>
                                <strong>${sector.altitude}m</strong>
                            </div>
                        ` : ''}
                        ${sector.access_time ? `
                            <div class="stat-item">
                                <span>Accès:</span>
                                <strong>${sector.access_time} min</strong>
                            </div>
                        ` : ''}
                        ${sector.exposure ? `
                            <div class="stat-item">
                                <span>Exposition:</span>
                                <strong>${sector.exposure}</strong>
                            </div>
                        ` : ''}
                    </div>
                </div>
                
                ${sector.access_info ? `
                    <div class="info-section">
                        <h5>Accès</h5>
                        <p>${sector.access_info}</p>
                    </div>
                ` : ''}
                
                <div class="info-actions">
                    <a href="/sectors/${sector.id}" class="info-btn primary">
                        <i class="fas fa-eye"></i>
                        Voir les voies
                    </a>
                    <button onclick="regionShow.showSectorWeather(${sector.id})" class="info-btn secondary">
                        <i class="fas fa-cloud-sun"></i>
                        Météo locale
                    </button>
                </div>
            </div>
        `;

        infoPanel.classList.add('active');
    }

    closeMapInfo() {
        const infoPanel = document.getElementById('map-info-overlay');
        if (infoPanel) {
            infoPanel.classList.remove('active');
        }
    }

    showSectorOnMap(sectorId) {
        const sector = this.sectors.find(s => s.id == sectorId);
        if (!sector || !sector.coordinates_lat || !sector.coordinates_lng) return;

        // Switch to sectors layer if not active
        if (this.activeMapLayer !== 'sectors') {
            this.showMapLayer('sectors');
        }

        // Center map on sector
        this.map.setView([sector.coordinates_lat, sector.coordinates_lng], 15);

        // Show sector info
        setTimeout(() => {
            this.showSectorInfo(sector);
        }, 500);
    }

    async initializeWeatherWidget() {
        if (!this.region.coordinates_lat || !this.region.coordinates_lng) {
            this.hideWeatherWidget();
            return;
        }

        try {
            const weatherData = await this.fetchWeatherData(
                this.region.coordinates_lat,
                this.region.coordinates_lng
            );
            this.renderWeatherWidget(weatherData);
        } catch (error) {
            console.error('Weather fetch error:', error);
            this.showWeatherError();
        }
    }

    async fetchWeatherData(lat, lng) {
        if (!this.weatherApiKey) {
            throw new Error('Weather API key not configured');
        }

        const cacheKey = `${lat},${lng}`;
        const cached = this.weatherCache.get(cacheKey);

        if (cached && Date.now() - cached.timestamp < 600000) { // 10 minutes cache
            return cached.data;
        }

        const response = await fetch(
            `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lng}&appid=${this.weatherApiKey}&units=metric&lang=fr`
        );

        if (!response.ok) {
            throw new Error('Weather API request failed');
        }

        const data = await response.json();

        this.weatherCache.set(cacheKey, {
            data: data,
            timestamp: Date.now()
        });

        return data;
    }

    renderWeatherWidget(data) {
        const widget = document.getElementById('weather-widget');
        if (!widget) return;

        const temp = Math.round(data.main.temp);
        const description = data.weather[0].description;
        const icon = data.weather[0].icon;
        const humidity = data.main.humidity;
        const windSpeed = Math.round(data.wind.speed * 3.6);

        widget.innerHTML = `
            <div class="weather-current">
                <div class="weather-main">
                    <img src="https://openweathermap.org/img/wn/${icon}.png" alt="${description}" class="weather-icon">
                    <div class="weather-temp">${temp}°C</div>
                </div>
                <div class="weather-description">${description}</div>
                <div class="weather-details">
                    <div class="weather-detail">
                        <i class="fas fa-tint"></i>
                        <span>${humidity}%</span>
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-wind"></i>
                        <span>${windSpeed} km/h</span>
                    </div>
                </div>
            </div>
        `;
    }

    hideWeatherWidget() {
        const widget = document.getElementById('weather-widget');
        if (widget) {
            widget.style.display = 'none';
        }
    }

    showWeatherError() {
        const widget = document.getElementById('weather-widget');
        if (widget) {
            widget.innerHTML = `
                <div class="weather-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Météo indisponible</span>
                </div>
            `;
        }
    }

    async showFullWeather() {
        if (!this.region.coordinates_lat || !this.region.coordinates_lng) {
            this.showNotification('Coordonnées non disponibles', 'error');
            return;
        }

        const modal = document.getElementById('weather-modal');
        const content = document.getElementById('weather-modal-content');

        if (!modal || !content) return;

        modal.classList.add('active');
        content.innerHTML = '<div class="loading-weather">Chargement des prévisions...</div>';

        try {
            // Fetch current weather and forecast
            const [currentWeather, forecast] = await Promise.all([
                this.fetchWeatherData(this.region.coordinates_lat, this.region.coordinates_lng),
                this.fetchWeatherForecast(this.region.coordinates_lat, this.region.coordinates_lng)
            ]);

            content.innerHTML = this.renderDetailedWeather(currentWeather, forecast);
        } catch (error) {
            console.error('Detailed weather error:', error);
            content.innerHTML = '<div class="error-weather">Erreur lors du chargement des prévisions</div>';
        }
    }

    async fetchWeatherForecast(lat, lng) {
        if (!this.weatherApiKey) {
            throw new Error('Weather API key not configured');
        }

        const response = await fetch(
            `https://api.openweathermap.org/data/2.5/forecast?lat=${lat}&lon=${lng}&appid=${this.weatherApiKey}&units=metric&lang=fr`
        );

        if (!response.ok) {
            throw new Error('Forecast API request failed');
        }

        return await response.json();
    }

    renderDetailedWeather(current, forecast) {
        const currentTemp = Math.round(current.main.temp);
        const currentDesc = current.weather[0].description;
        const currentIcon = current.weather[0].icon;

        // Process forecast data (next 5 days)
        const dailyForecast = this.processForecastData(forecast.list);

        return `
            <div class="detailed-weather">
                <div class="current-weather-section">
                    <h4>Conditions actuelles</h4>
                    <div class="current-weather-display">
                        <img src="https://openweathermap.org/img/wn/${currentIcon}@2x.png" alt="${currentDesc}">
                        <div class="current-temp">${currentTemp}°C</div>
                        <div class="current-desc">${currentDesc}</div>
                    </div>
                    
                    <div class="current-details">
                        <div class="detail-item">
                            <span>Ressenti:</span>
                            <strong>${Math.round(current.main.feels_like)}°C</strong>
                        </div>
                        <div class="detail-item">
                            <span>Humidité:</span>
                            <strong>${current.main.humidity}%</strong>
                        </div>
                        <div class="detail-item">
                            <span>Vent:</span>
                            <strong>${Math.round(current.wind.speed * 3.6)} km/h</strong>
                        </div>
                        <div class="detail-item">
                            <span>Pression:</span>
                            <strong>${current.main.pressure} hPa</strong>
                        </div>
                    </div>
                </div>
                
                <div class="forecast-section">
                    <h4>Prévisions 5 jours</h4>
                    <div class="forecast-days">
                        ${dailyForecast.map(day => `
                            <div class="forecast-day">
                                <div class="day-name">${day.dayName}</div>
                                <img src="https://openweathermap.org/img/wn/${day.icon}.png" alt="${day.description}">
                                <div class="day-temps">
                                    <span class="temp-max">${day.tempMax}°</span>
                                    <span class="temp-min">${day.tempMin}°</span>
                                </div>
                                <div class="day-desc">${day.description}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="climbing-advice">
                    <h4>Conseils escalade</h4>
                    <div class="advice-items">
                        ${this.generateClimbingAdvice(current, dailyForecast)}
                    </div>
                </div>
            </div>
        `;
    }

    processForecastData(forecastList) {
        const dailyData = {};

        forecastList.forEach(item => {
            const date = new Date(item.dt * 1000);
            const dateKey = date.toDateString();

            if (!dailyData[dateKey]) {
                dailyData[dateKey] = {
                    date: date,
                    temps: [],
                    weather: item.weather[0],
                    dayName: date.toLocaleDateString('fr-FR', { weekday: 'short' })
                };
            }

            dailyData[dateKey].temps.push(item.main.temp);
        });

        return Object.values(dailyData).slice(0, 5).map(day => ({
            dayName: day.dayName,
            tempMax: Math.round(Math.max(...day.temps)),
            tempMin: Math.round(Math.min(...day.temps)),
            icon: day.weather.icon,
            description: day.weather.description
        }));
    }

    generateClimbingAdvice(current, forecast) {
        const advice = [];
        const temp = current.main.temp;
        const humidity = current.main.humidity;
        const isRaining = current.weather[0].main.includes('Rain');

        // Temperature advice
        if (temp >= 15 && temp <= 25) {
            advice.push({ icon: 'fas fa-thermometer-half', text: 'Température parfaite pour grimper', type: 'good' });
        } else if (temp < 5) {
            advice.push({ icon: 'fas fa-snowflake', text: 'Attention au froid, échauffement important', type: 'warning' });
        } else if (temp > 30) {
            advice.push({ icon: 'fas fa-sun', text: 'Chaleur intense, hydratez-vous bien', type: 'warning' });
        }

        // Humidity advice
        if (humidity > 80) {
            advice.push({ icon: 'fas fa-tint', text: 'Forte humidité, séchage des prises difficile', type: 'warning' });
        }

        // Rain advice
        if (isRaining) {
            advice.push({ icon: 'fas fa-cloud-rain', text: 'Conditions humides, évitez les voies exposées', type: 'poor' });
        } else {
            advice.push({ icon: 'fas fa-sun', text: 'Conditions sèches favorables', type: 'good' });
        }

        // General advice based on forecast
        const rainyDays = forecast.filter(day => day.description.includes('pluie')).length;
        if (rainyDays > 2) {
            advice.push({ icon: 'fas fa-umbrella', text: 'Plusieurs jours de pluie prévus', type: 'info' });
        }

        return advice.map(item => `
            <div class="advice-item ${item.type}">
                <i class="${item.icon}"></i>
                <span>${item.text}</span>
            </div>
        `).join('');
    }

    closeWeatherModal() {
        const modal = document.getElementById('weather-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    async showSectorWeather(sectorId) {
        const sector = this.sectors.find(s => s.id == sectorId);
        if (!sector || !sector.coordinates_lat || !sector.coordinates_lng) return;

        // Use the same weather modal but with sector-specific data
        const modal = document.getElementById('weather-modal');
        const content = document.getElementById('weather-modal-content');

        if (!modal || !content) return;

        modal.classList.add('active');
        content.innerHTML = `<div class="loading-weather">Chargement météo pour ${sector.name}...</div>`;

        try {
            const weatherData = await this.fetchWeatherData(sector.coordinates_lat, sector.coordinates_lng);
            content.innerHTML = this.renderSectorWeather(weatherData, sector);
        } catch (error) {
            content.innerHTML = '<div class="error-weather">Erreur lors du chargement de la météo</div>';
        }
    }

    renderSectorWeather(data, sector) {
        const temp = Math.round(data.main.temp);
        const description = data.weather[0].description;
        const icon = data.weather[0].icon;
        const humidity = data.main.humidity;
        const windSpeed = Math.round(data.wind.speed * 3.6);

        return `
            <div class="sector-weather">
                <h4>Météo - ${sector.name}</h4>
                
                <div class="weather-current">
                    <div class="weather-display">
                        <img src="https://openweathermap.org/img/wn/${icon}@2x.png" alt="${description}">
                        <div class="temp-main">${temp}°C</div>
                        <div class="weather-desc">${description}</div>
                    </div>
                    
                    <div class="weather-stats">
                        <div class="stat">
                            <i class="fas fa-thermometer-half"></i>
                            <span>Ressenti: ${Math.round(data.main.feels_like)}°C</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-tint"></i>
                            <span>Humidité: ${humidity}%</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-wind"></i>
                            <span>Vent: ${windSpeed} km/h</span>
                        </div>
                        ${sector.altitude ? `
                            <div class="stat">
                                <i class="fas fa-mountain"></i>
                                <span>Altitude: ${sector.altitude}m</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="sector-specific-advice">
                    <h5>Conseils pour ce secteur</h5>
                    ${this.getSectorSpecificAdvice(data, sector)}
                </div>
            </div>
        `;
    }

    getSectorSpecificAdvice(weather, sector) {
        const advice = [];
        const temp = weather.main.temp;
        const windSpeed = weather.wind.speed * 3.6;
        const isRaining = weather.weather[0].main.includes('Rain');

        // Exposure-specific advice
        if (sector.exposure) {
            if (sector.exposure.includes('S') && temp > 25) {
                advice.push('Secteur exposé sud : privilégiez les heures matinales');
            }
            if (sector.exposure.includes('N') && temp < 15) {
                advice.push('Secteur exposé nord : peu de soleil, habillez-vous chaudement');
            }
            if (windSpeed > 20 && (sector.exposure.includes('W') || sector.exposure.includes('E'))) {
                advice.push('Vent fort sur cette exposition, attention à la sécurité');
            }
        }

        // Altitude-specific advice
        if (sector.altitude > 1500 && temp < 10) {
            advice.push('Altitude élevée : risque de conditions plus froides');
        }

        // General conditions
        if (isRaining) {
            advice.push('Pluie : ce secteur sera humide, reportez votre sortie');
        }

        if (advice.length === 0) {
            advice.push('Conditions favorables pour ce secteur');
        }

        return advice.map(text => `<div class="advice-text"><i class="fas fa-lightbulb"></i> ${text}</div>`).join('');
    }

    initializePhotoGallery() {
        if (!this.photos || this.photos.length === 0) return;

        // Initialize Swiper for photo gallery
        this.gallerySwiper = new Swiper('.gallery-swiper', {
            slidesPerView: 'auto',
            spaceBetween: 16,
            pagination: {
                el: '.swiper-pagination',
                clickable: true
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            },
            breakpoints: {
                480: {
                    slidesPerView: 2
                },
                768: {
                    slidesPerView: 3
                },
                1024: {
                    slidesPerView: 4
                }
            }
        });
    }

    openPhotoModal(index) {
        this.currentPhotoIndex = index;
        const modal = document.getElementById('photo-modal');
        const image = document.getElementById('photo-modal-image');
        const title = document.getElementById('photo-modal-title');

        if (!modal || !image || !this.photos[index]) return;

        const photo = this.photos[index];
        image.src = photo.url || photo.image_url;
        image.alt = photo.title || `Photo ${index + 1}`;
        title.textContent = photo.title || `Photo ${index + 1} de ${this.photos.length}`;

        modal.classList.add('active');

        // Update navigation buttons
        this.updatePhotoNavigation();
    }

    updatePhotoNavigation() {
        const prevBtn = document.getElementById('photo-prev');
        const nextBtn = document.getElementById('photo-next');

        if (prevBtn) {
            prevBtn.style.display = this.currentPhotoIndex > 0 ? 'block' : 'none';
        }

        if (nextBtn) {
            nextBtn.style.display = this.currentPhotoIndex < this.photos.length - 1 ? 'block' : 'none';
        }
    }

    previousPhoto() {
        if (this.currentPhotoIndex > 0) {
            this.openPhotoModal(this.currentPhotoIndex - 1);
        }
    }

    nextPhoto() {
        if (this.currentPhotoIndex < this.photos.length - 1) {
            this.openPhotoModal(this.currentPhotoIndex + 1);
        }
    }

    closePhotoModal() {
        const modal = document.getElementById('photo-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    async loadUpcomingEvents() {
        const container = document.getElementById('upcoming-events');
        if (!container) return;

        try {
            // Simulate API call - replace with actual endpoint
            const events = await this.fetchUpcomingEvents();
            this.renderUpcomingEvents(events, container);
        } catch (error) {
            console.error('Error loading events:', error);
            container.innerHTML = '<div class="error-events">Erreur lors du chargement des événements</div>';
        }
    }

    async fetchUpcomingEvents() {
        // Simulate API call
        return new Promise(resolve => {
            setTimeout(() => {
                resolve([
                    {
                        id: 1,
                        title: 'Sortie escalade groupe',
                        date: '2024-07-15',
                        location: 'Secteur principal'
                    },
                    {
                        id: 2,
                        title: 'Formation secours',
                        date: '2024-07-20',
                        location: 'Base du secteur'
                    }
                ]);
            }, 1000);
        });
    }

    renderUpcomingEvents(events, container) {
        if (events.length === 0) {
            container.innerHTML = '<div class="no-events">Aucun événement à venir</div>';
            return;
        }

        container.innerHTML = events.map(event => `
            <div class="event-item">
                <div class="event-date">${this.formatEventDate(event.date)}</div>
                <div class="event-title">${event.title}</div>
                <div class="event-location">${event.location}</div>
            </div>
        `).join('');
    }

    formatEventDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            day: 'numeric',
            month: 'short'
        });
    }

    // Quick action handlers
    downloadTopoGuide() {
        this.showNotification('Téléchargement du topo en préparation', 'info');
    }

    shareRegion() {
        if (navigator.share) {
            navigator.share({
                title: this.region.name,
                text: `Découvrez la région d'escalade ${this.region.name}`,
                url: window.location.href
            });
        } else {
            navigator.clipboard.writeText(window.location.href);
            this.showNotification('Lien copié dans le presse-papiers', 'success');
        }
    }

    addToFavorites() {
        // This would typically save to user preferences
        this.showNotification('Région ajoutée aux favoris', 'success');
    }

    copyCoordinates() {
        if (this.region.coordinates_lat && this.region.coordinates_lng) {
            const coords = `${this.region.coordinates_lat}, ${this.region.coordinates_lng}`;
            navigator.clipboard.writeText(coords);
            this.showNotification('Coordonnées copiées', 'success');
        }
    }

    openPhotoUpload() {
        this.showNotification('Fonctionnalité d\'upload en développement', 'info');
    }

    // Utility methods
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }

    showNotification(message, type = 'info') {
        // Simple notification system - could be enhanced with a toast library
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    addMapStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .custom-sector-marker,
            .custom-parking-marker {
                background: transparent;
                border: none;
            }
            
            .sector-marker-icon {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 0.875rem;
                font-weight: bold;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                border: 2px solid white;
                position: relative;
            }
            
            .sector-count {
                position: absolute;
                bottom: -5px;
                right: -5px;
                background: white;
                color: #1f2937;
                border-radius: 50%;
                width: 16px;
                height: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.625rem;
                font-weight: bold;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            }
            
            .parking-marker-icon {
                width: 25px;
                height: 25px;
                background: #3b82f6;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 0.875rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                border: 2px solid white;
            }
            
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                color: white;
                font-weight: 600;
                z-index: 3000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            }
            
            .notification.show {
                transform: translateX(0);
            }
            
            .notification-info {
                background: #3b82f6;
            }
            
            .notification-success {
                background: #10b981;
            }
            
            .notification-warning {
                background: #f59e0b;
            }
            
            .notification-error {
                background: #ef4444;
            }
            
            .loading-route,
            .loading-weather,
            .loading-events,
            .error-weather,
            .error-events,
            .no-events {
                text-align: center;
                padding: 1rem;
                color: #6b7280;
                font-style: italic;
            }
            
            .error-weather,
            .error-events {
                color: #ef4444;
            }
            
            .route-summary {
                background: #f9fafb;
                border-radius: 0.5rem;
                padding: 1rem;
            }
            
            .route-summary h5 {
                margin: 0 0 0.75rem 0;
                color: #1f2937;
                font-weight: 700;
            }
            
            .route-details {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .route-detail {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: #6b7280;
            }
            
            .route-detail i {
                color: #667eea;
                width: 1rem;
            }
            
            .route-actions {
                display: flex;
                gap: 0.5rem;
            }
            
            .route-action-btn {
                flex: 1;
                padding: 0.5rem;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                font-size: 0.75rem;
                font-weight: 600;
                color: #6b7280;
                cursor: pointer;
                transition: all 0.15s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.25rem;
            }
            
            .route-action-btn:hover {
                background: #f3f4f6;
                color: #1f2937;
            }
        `;
        document.head.appendChild(style);
    }

    showMapError() {
        const mapContainer = document.getElementById('region-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="map-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Erreur de chargement de la carte</h4>
                    <p>Impossible de charger la carte interactive.</p>
                    <button onclick="location.reload()" class="retry-btn">
                        <i class="fas fa-redo"></i>
                        Réessayer
                    </button>
                </div>
            `;
        }
    }

    closeAllModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => modal.classList.remove('active'));

        this.closeMapInfo();
        this.closeRouting();
    }
}

// Global functions for onclick handlers
window.showMapLayer = function (layer) {
    if (window.regionShow) {
        window.regionShow.showMapLayer(layer);
    }
};

window.toggleRouting = function () {
    if (window.regionShow) {
        window.regionShow.toggleRouting();
    }
};

window.closeMapInfo = function () {
    if (window.regionShow) {
        window.regionShow.closeMapInfo();
    }
};

window.closeRouting = function () {
    if (window.regionShow) {
        window.regionShow.closeRouting();
    }
};

window.useCurrentLocation = function () {
    if (window.regionShow) {
        window.regionShow.useCurrentLocation();
    }
};

window.calculateRoute = function () {
    if (window.regionShow) {
        window.regionShow.calculateRoute();
    }
};

window.showSectorOnMap = function (sectorId) {
    if (window.regionShow) {
        window.regionShow.showSectorOnMap(sectorId);
    }
};

window.showSectorWeather = function (sectorId) {
    if (window.regionShow) {
        window.regionShow.showSectorWeather(sectorId);
    }
};

window.openPhotoModal = function (index) {
    if (window.regionShow) {
        window.regionShow.openPhotoModal(index);
    }
};

window.previousPhoto = function () {
    if (window.regionShow) {
        window.regionShow.previousPhoto();
    }
};

window.nextPhoto = function () {
    if (window.regionShow) {
        window.regionShow.nextPhoto();
    }
};

window.closePhotoModal = function () {
    if (window.regionShow) {
        window.regionShow.closePhotoModal();
    }
};

window.closeWeatherModal = function () {
    if (window.regionShow) {
        window.regionShow.closeWeatherModal();
    }
};

window.showFullWeather = function () {
    if (window.regionShow) {
        window.regionShow.showFullWeather();
    }
};

window.downloadTopoGuide = function () {
    if (window.regionShow) {
        window.regionShow.downloadTopoGuide();
    }
};

window.shareRegion = function () {
    if (window.regionShow) {
        window.regionShow.shareRegion();
    }
};

window.addToFavorites = function () {
    if (window.regionShow) {
        window.regionShow.addToFavorites();
    }
};

window.copyCoordinates = function () {
    if (window.regionShow) {
        window.regionShow.copyCoordinates();
    }
};

window.openPhotoUpload = function () {
    if (window.regionShow) {
        window.regionShow.openPhotoUpload();
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    window.regionShow = new RegionShow();
});