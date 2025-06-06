// ===== REGIONS INDEX PAGE JAVASCRIPT =====
// Advanced interactive features for regions listing page

class RegionsIndex {
    constructor() {
        this.regions = window.regionsData?.regions || [];
        this.filteredRegions = [...this.regions];
        this.currentView = 'grid';
        this.map = null;
        this.mapMarkers = [];
        this.searchTimeout = null;
        this.weatherCache = new Map();
        this.swisstopoApiKey = window.regionsData?.swisstopoApiKey;
        this.weatherApiKey = window.regionsData?.weatherApiKey;

        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.setupSearch();
        this.setupFilters();
        this.setupViewToggle();
        this.renderRegions();

        // Preload map resources
        if (this.swisstopoApiKey) {
            this.preloadMapResources();
        }

        console.log('RegionsIndex initialized with', this.regions.length, 'regions');
    }

    setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('region-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.performSearch(e.target.value);
                }
            });
        }

        // Filter dropdowns
        const filters = ['country-filter', 'difficulty-filter', 'season-filter', 'style-filter'];
        filters.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.applyFilters());
            }
        });

        // Clear filters button
        const clearBtn = document.getElementById('clear-filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearFilters());
        }

        // View toggle button
        const toggleBtn = document.getElementById('toggle-view');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleView());
        }

        // Window resize handler for responsive map
        window.addEventListener('resize', () => {
            if (this.map && this.currentView === 'map') {
                setTimeout(() => this.map.invalidateSize(), 250);
            }
        });

        // Escape key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    setupSearch() {
        // Advanced search with debouncing
        const searchInput = document.getElementById('region-search');
        if (!searchInput) return;

        // Add search suggestions
        this.createSearchSuggestions();

        // Auto-complete functionality
        searchInput.addEventListener('focus', () => this.showSearchSuggestions());
        searchInput.addEventListener('blur', () => {
            setTimeout(() => this.hideSearchSuggestions(), 200);
        });
    }

    createSearchSuggestions() {
        const searchContainer = document.querySelector('.search-container');
        if (!searchContainer) return;

        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.id = 'search-suggestions';
        suggestionsDiv.className = 'search-suggestions';
        suggestionsDiv.innerHTML = `
            <div class="suggestions-header">Suggestions</div>
            <div class="suggestions-list" id="suggestions-list"></div>
        `;

        searchContainer.appendChild(suggestionsDiv);

        // Add CSS styles
        this.addSearchStyles();
    }

    addSearchStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .search-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border-radius: 0 0 1rem 1rem;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                max-height: 300px;
                overflow-y: auto;
                display: none;
                border: 1px solid #e5e7eb;
                border-top: none;
            }
            
            .search-suggestions.active {
                display: block;
                animation: slideDown 0.2s ease-out;
            }
            
            .suggestions-header {
                padding: 0.75rem 1rem;
                background: #f9fafb;
                font-weight: 600;
                font-size: 0.875rem;
                color: #6b7280;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .suggestion-item {
                padding: 0.75rem 1rem;
                cursor: pointer;
                border-bottom: 1px solid #f3f4f6;
                transition: background-color 0.15s ease;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .suggestion-item:hover {
                background: #f3f4f6;
            }
            
            .suggestion-item:last-child {
                border-bottom: none;
            }
            
            .suggestion-icon {
                color: #667eea;
                font-size: 1rem;
            }
            
            .suggestion-text {
                flex: 1;
            }
            
            .suggestion-name {
                font-weight: 600;
                color: #1f2937;
            }
            
            .suggestion-details {
                font-size: 0.75rem;
                color: #6b7280;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    }

    showSearchSuggestions() {
        const suggestions = document.getElementById('search-suggestions');
        const searchInput = document.getElementById('region-search');

        if (!suggestions || !searchInput) return;

        const query = searchInput.value.trim();
        const suggestionsList = document.getElementById('suggestions-list');

        if (query.length > 0) {
            const matches = this.findSearchMatches(query);
            this.renderSuggestions(matches, suggestionsList);
        } else {
            this.renderPopularRegions(suggestionsList);
        }

        suggestions.classList.add('active');
    }

    hideSearchSuggestions() {
        const suggestions = document.getElementById('search-suggestions');
        if (suggestions) {
            suggestions.classList.remove('active');
        }
    }

    findSearchMatches(query) {
        const lowercaseQuery = query.toLowerCase();
        return this.regions.filter(region =>
            region.name.toLowerCase().includes(lowercaseQuery) ||
            (region.country && region.country.name.toLowerCase().includes(lowercaseQuery)) ||
            (region.description && region.description.toLowerCase().includes(lowercaseQuery))
        ).slice(0, 5);
    }

    renderSuggestions(matches, container) {
        container.innerHTML = matches.map(region => `
            <div class="suggestion-item" onclick="regionsIndex.selectRegion(${region.id})">
                <i class="fas fa-mountain suggestion-icon"></i>
                <div class="suggestion-text">
                    <div class="suggestion-name">${region.name}</div>
                    <div class="suggestion-details">
                        ${region.country ? region.country.name : ''} • 
                        ${region.sectors_count || 0} secteurs
                    </div>
                </div>
            </div>
        `).join('');
    }

    renderPopularRegions(container) {
        const popular = this.regions
            .sort((a, b) => (b.sectors_count || 0) - (a.sectors_count || 0))
            .slice(0, 5);

        container.innerHTML = `
            <div style="padding: 0.75rem 1rem; color: #6b7280; font-size: 0.875rem;">
                Régions populaires
            </div>
            ${popular.map(region => `
                <div class="suggestion-item" onclick="regionsIndex.selectRegion(${region.id})">
                    <i class="fas fa-star suggestion-icon"></i>
                    <div class="suggestion-text">
                        <div class="suggestion-name">${region.name}</div>
                        <div class="suggestion-details">
                            ${region.sectors_count || 0} secteurs • ${region.routes_count || 0} voies
                        </div>
                    </div>
                </div>
            `).join('')}
        `;
    }

    selectRegion(regionId) {
        window.location.href = `/regions/${regionId}`;
    }

    handleSearch(query) {
        clearTimeout(this.searchTimeout);

        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);

        // Update suggestions in real-time
        if (query.length > 0) {
            this.showSearchSuggestions();
        }
    }

    performSearch(query) {
        const lowercaseQuery = query.toLowerCase().trim();

        if (lowercaseQuery === '') {
            this.filteredRegions = [...this.regions];
        } else {
            this.filteredRegions = this.regions.filter(region =>
                region.name.toLowerCase().includes(lowercaseQuery) ||
                (region.country && region.country.name.toLowerCase().includes(lowercaseQuery)) ||
                (region.description && region.description.toLowerCase().includes(lowercaseQuery)) ||
                (region.sectors && region.sectors.some(sector =>
                    sector.name.toLowerCase().includes(lowercaseQuery)
                ))
            );
        }

        this.applyFilters();
        this.hideSearchSuggestions();
    }

    setupFilters() {
        // Advanced filtering with multiple criteria
        this.filterCriteria = {
            country: '',
            difficulty: '',
            season: '',
            style: ''
        };
    }

    applyFilters() {
        // Get current filter values
        this.filterCriteria.country = document.getElementById('country-filter')?.value || '';
        this.filterCriteria.difficulty = document.getElementById('difficulty-filter')?.value || '';
        this.filterCriteria.season = document.getElementById('season-filter')?.value || '';
        this.filterCriteria.style = document.getElementById('style-filter')?.value || '';

        // Apply filters to search results
        let filtered = [...this.filteredRegions];

        if (this.filterCriteria.country) {
            filtered = filtered.filter(region =>
                region.country_id == this.filterCriteria.country
            );
        }

        if (this.filterCriteria.difficulty) {
            filtered = filtered.filter(region =>
                this.matchesDifficultyFilter(region, this.filterCriteria.difficulty)
            );
        }

        if (this.filterCriteria.season) {
            filtered = filtered.filter(region =>
                this.matchesSeasonFilter(region, this.filterCriteria.season)
            );
        }

        if (this.filterCriteria.style) {
            filtered = filtered.filter(region =>
                this.matchesStyleFilter(region, this.filterCriteria.style)
            );
        }

        this.filteredRegions = filtered;
        this.renderRegions();

        if (this.currentView === 'map') {
            this.updateMapMarkers();
        }
    }

    matchesDifficultyFilter(region, difficulty) {
        // Implement difficulty matching logic based on region's route difficulties
        const avgDiff = region.avg_difficulty || 0;

        switch (difficulty) {
            case 'beginner':
                return avgDiff >= 3 && avgDiff <= 5;
            case 'intermediate':
                return avgDiff >= 5 && avgDiff <= 6;
            case 'advanced':
                return avgDiff >= 6;
            default:
                return true;
        }
    }

    matchesSeasonFilter(region, season) {
        if (!region.best_season) return true;

        const currentMonth = new Date().getMonth() + 1;
        const seasonMonths = {
            spring: [3, 4, 5],
            summer: [6, 7, 8],
            autumn: [9, 10, 11],
            winter: [12, 1, 2]
        };

        if (season === 'current') {
            const currentSeason = Object.keys(seasonMonths).find(s =>
                seasonMonths[s].includes(currentMonth)
            );
            return region.best_season === currentSeason;
        }

        return region.best_season === season;
    }

    matchesStyleFilter(region, style) {
        // Check if region has sectors/routes of the specified style
        return region.climbing_styles ? region.climbing_styles.includes(style) : true;
    }

    clearFilters() {
        // Reset all filter inputs
        document.getElementById('country-filter').value = '';
        document.getElementById('difficulty-filter').value = '';
        document.getElementById('season-filter').value = '';
        document.getElementById('style-filter').value = '';
        document.getElementById('region-search').value = '';

        // Reset filtered data
        this.filteredRegions = [...this.regions];
        this.renderRegions();

        if (this.currentView === 'map') {
            this.updateMapMarkers();
        }
    }

    setupViewToggle() {
        this.currentView = 'grid'; // Default view
    }

    toggleView() {
        const toggleBtn = document.getElementById('toggle-view');
        const gridView = document.getElementById('grid-view');
        const mapView = document.getElementById('map-view');

        if (this.currentView === 'grid') {
            this.currentView = 'map';
            gridView.classList.remove('active');
            mapView.classList.add('active');
            toggleBtn.innerHTML = '<i class="fas fa-th-large"></i><span>Vue grille</span>';
            toggleBtn.classList.add('map-active');

            this.initializeMap();
        } else {
            this.currentView = 'grid';
            mapView.classList.remove('active');
            gridView.classList.add('active');
            toggleBtn.innerHTML = '<i class="fas fa-map"></i><span>Vue carte</span>';
            toggleBtn.classList.remove('map-active');
        }
    }

    async initializeMap() {
        if (this.map) {
            this.updateMapMarkers();
            return;
        }

        this.showLoading('Initialisation de la carte...');

        try {
            // Initialize Swiss coordinate system
            this.setupSwissProjections();

            // Create map with Swiss extent
            this.map = L.map('regions-map', {
                center: [46.8182, 8.2275], // Center of Switzerland
                zoom: 8,
                maxZoom: 18,
                minZoom: 6
            });

            // Add Swiss base layers
            await this.addSwissBaseLayers();

            // Add region markers
            this.addRegionMarkers();

            // Setup map controls
            this.setupMapControls();

            this.hideLoading();

        } catch (error) {
            console.error('Error initializing map:', error);
            this.hideLoading();
            this.showError('Erreur lors du chargement de la carte');
        }
    }

    setupSwissProjections() {
        // Define Swiss coordinate systems using Proj4
        if (window.proj4) {
            proj4.defs("EPSG:2056", "+proj=somerc +lat_0=46.95240555555556 +lon_0=7.439583333333333 +k_0=1 +x_0=2600000 +y_0=1200000 +ellps=bessel +towgs84=674.374,15.056,405.346,0,0,0,0 +units=m +no_defs");
            proj4.defs("EPSG:21781", "+proj=somerc +lat_0=46.95240555555556 +lon_0=7.439583333333333 +k_0=1 +x_0=600000 +y_0=200000 +ellps=bessel +towgs84=674.4,15.1,405.3,0,0,0,0 +units=m +no_defs");
        }
    }

    async addSwissBaseLayers() {
        const baseLayers = {};

        // Swisstopo pixel maps
        if (this.swisstopoApiKey) {
            baseLayers['Carte nationale'] = L.tileLayer(
                `https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg`,
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );

            baseLayers['Carte satellite'] = L.tileLayer(
                `https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg`,
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );

            baseLayers['Carte de randonnée'] = L.tileLayer(
                `https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe-pk25.noscale/default/current/3857/{z}/{x}/{y}.jpeg`,
                {
                    attribution: '© swisstopo',
                    maxZoom: 18
                }
            );
        }

        // Fallback to OpenStreetMap if Swisstopo not available
        if (Object.keys(baseLayers).length === 0) {
            baseLayers['OpenStreetMap'] = L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                }
            );
        }

        // Add first layer as default
        const firstLayer = Object.values(baseLayers)[0];
        firstLayer.addTo(this.map);

        // Add layer control
        L.control.layers(baseLayers).addTo(this.map);
    }

    addRegionMarkers() {
        this.clearMapMarkers();

        this.filteredRegions.forEach(region => {
            if (region.coordinates_lat && region.coordinates_lng) {
                const marker = this.createRegionMarker(region);
                this.mapMarkers.push(marker);
                marker.addTo(this.map);
            }
        });

        // Fit map to show all markers
        if (this.mapMarkers.length > 0) {
            const group = new L.featureGroup(this.mapMarkers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    createRegionMarker(region) {
        // Custom icon based on region type/difficulty
        const iconHtml = this.getRegionIcon(region);

        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-region-marker',
            iconSize: [40, 40],
            iconAnchor: [20, 40],
            popupAnchor: [0, -40]
        });

        const marker = L.marker([region.coordinates_lat, region.coordinates_lng], {
            icon: customIcon
        });

        // Create popup content
        const popupContent = this.createMarkerPopup(region);
        marker.bindPopup(popupContent, {
            maxWidth: 300,
            className: 'region-popup'
        });

        // Add click event
        marker.on('click', () => {
            this.showRegionDetails(region);
        });

        return marker;
    }

    getRegionIcon(region) {
        const difficulty = this.getRegionDifficulty(region);
        const color = this.getDifficultyColor(difficulty);

        return `
            <div class="marker-icon" style="background: ${color};">
                <i class="fas fa-mountain"></i>
                <div class="marker-count">${region.sectors_count || 0}</div>
            </div>
        `;
    }

    getRegionDifficulty(region) {
        const avgDiff = region.avg_difficulty || 0;
        if (avgDiff <= 5) return 'beginner';
        if (avgDiff <= 6) return 'intermediate';
        return 'advanced';
    }

    getDifficultyColor(difficulty) {
        const colors = {
            beginner: '#10b981',   // Green
            intermediate: '#f59e0b', // Orange
            advanced: '#ef4444'     // Red
        };
        return colors[difficulty] || '#6b7280';
    }

    createMarkerPopup(region) {
        return `
            <div class="region-popup-content">
                <div class="popup-header">
                    <h4>${region.name}</h4>
                    ${region.country ? `<span class="popup-country">${region.country.name}</span>` : ''}
                </div>
                <div class="popup-stats">
                    <div class="popup-stat">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${region.sectors_count || 0} secteurs</span>
                    </div>
                    <div class="popup-stat">
                        <i class="fas fa-route"></i>
                        <span>${region.routes_count || 0} voies</span>
                    </div>
                    ${region.altitude ? `
                        <div class="popup-stat">
                            <i class="fas fa-mountain"></i>
                            <span>${region.altitude}m</span>
                        </div>
                    ` : ''}
                </div>
                <div class="popup-actions">
                    <a href="/regions/${region.id}" class="popup-btn primary">
                        <i class="fas fa-eye"></i> Voir
                    </a>
                    <button onclick="regionsIndex.showWeather(${region.id})" class="popup-btn secondary">
                        <i class="fas fa-cloud-sun"></i> Météo
                    </button>
                </div>
            </div>
        `;
    }

    setupMapControls() {
        // Add custom map control styles
        this.addMapStyles();

        // Add scale control
        L.control.scale({
            metric: true,
            imperial: false,
            position: 'bottomleft'
        }).addTo(this.map);

        // Add compass/north arrow
        this.addCompassControl();
    }

    addMapStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .custom-region-marker {
                background: transparent;
                border: none;
            }
            
            .marker-icon {
                width: 40px;
                height: 40px;
                border-radius: 50% 50% 50% 0;
                transform: rotate(-45deg);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
                border: 3px solid white;
                position: relative;
            }
            
            .marker-icon i {
                color: white;
                font-size: 1rem;
                transform: rotate(45deg);
            }
            
            .marker-count {
                position: absolute;
                bottom: -8px;
                right: -8px;
                background: white;
                color: #1f2937;
                border-radius: 50%;
                width: 18px;
                height: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.75rem;
                font-weight: bold;
                transform: rotate(45deg);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }
            
            .region-popup .leaflet-popup-content {
                margin: 0;
                width: 280px !important;
            }
            
            .region-popup-content {
                padding: 0;
            }
            
            .popup-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 1rem;
                margin: -20px -20px 1rem -20px;
            }
            
            .popup-header h4 {
                margin: 0 0 0.25rem 0;
                font-weight: 700;
            }
            
            .popup-country {
                font-size: 0.875rem;
                opacity: 0.9;
            }
            
            .popup-stats {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .popup-stat {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: #6b7280;
            }
            
            .popup-stat i {
                color: #667eea;
                width: 1rem;
            }
            
            .popup-actions {
                display: flex;
                gap: 0.5rem;
            }
            
            .popup-btn {
                flex: 1;
                padding: 0.5rem;
                border-radius: 0.5rem;
                font-weight: 600;
                font-size: 0.875rem;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.25rem;
                transition: all 0.15s ease;
                border: none;
                cursor: pointer;
            }
            
            .popup-btn.primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .popup-btn.secondary {
                background: #f3f4f6;
                color: #6b7280;
            }
            
            .popup-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
        `;
        document.head.appendChild(style);
    }

    addCompassControl() {
        const CompassControl = L.Control.extend({
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
                container.innerHTML = '<i class="fas fa-compass" style="font-size: 1.25rem; color: #667eea;"></i>';
                container.style.backgroundColor = 'white';
                container.style.width = '40px';
                container.style.height = '40px';
                container.style.display = 'flex';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
                container.style.cursor = 'pointer';
                container.title = 'Centrer sur la Suisse';

                container.onclick = () => {
                    this._map.setView([46.8182, 8.2275], 8);
                };

                return container;
            }
        });

        new CompassControl({ position: 'topright' }).addTo(this.map);
    }

    updateMapMarkers() {
        if (!this.map) return;

        this.clearMapMarkers();
        this.addRegionMarkers();
    }

    clearMapMarkers() {
        this.mapMarkers.forEach(marker => {
            this.map.removeLayer(marker);
        });
        this.mapMarkers = [];
    }

    showRegionDetails(region) {
        this.showMapInfo(region);
    }

    showMapInfo(region) {
        const panel = document.getElementById('map-info-panel');
        const content = document.getElementById('panel-content');

        if (!panel || !content) return;

        content.innerHTML = `
            <div class="region-details">
                <h4>${region.name}</h4>
                ${region.country ? `<p class="region-country">${region.country.name}</p>` : ''}
                
                ${region.description ? `
                    <div class="region-description">
                        <strong>Description</strong>
                        <p>${region.description}</p>
                    </div>
                ` : ''}
                
                <div class="region-stats-detailed">
                    <div class="stat-row">
                        <span>Secteurs:</span>
                        <strong>${region.sectors_count || 0}</strong>
                    </div>
                    <div class="stat-row">
                        <span>Voies:</span>
                        <strong>${region.routes_count || 0}</strong>
                    </div>
                    ${region.altitude ? `
                        <div class="stat-row">
                            <span>Altitude:</span>
                            <strong>${region.altitude}m</strong>
                        </div>
                    ` : ''}
                    ${region.avg_difficulty ? `
                        <div class="stat-row">
                            <span>Difficulté moy.:</span>
                            <strong>${region.avg_difficulty}</strong>
                        </div>
                    ` : ''}
                </div>
                
                <div class="region-actions">
                    <a href="/regions/${region.id}" class="action-link primary">
                        <i class="fas fa-eye"></i> Voir en détail
                    </a>
                    <button onclick="regionsIndex.showWeather(${region.id})" class="action-link secondary">
                        <i class="fas fa-cloud-sun"></i> Météo
                    </button>
                </div>
            </div>
        `;

        panel.classList.add('active');

        // Add detailed styles
        this.addMapInfoStyles();
    }

    addMapInfoStyles() {
        if (document.getElementById('map-info-styles')) return;

        const style = document.createElement('style');
        style.id = 'map-info-styles';
        style.textContent = `
            .region-details h4 {
                margin: 0 0 0.5rem 0;
                color: #1f2937;
                font-weight: 700;
            }
            
            .region-country {
                color: #6b7280;
                font-size: 0.875rem;
                margin: 0 0 1rem 0;
            }
            
            .region-description {
                margin-bottom: 1rem;
            }
            
            .region-description strong {
                display: block;
                margin-bottom: 0.5rem;
                color: #1f2937;
            }
            
            .region-description p {
                color: #6b7280;
                line-height: 1.6;
                margin: 0;
            }
            
            .region-stats-detailed {
                background: #f9fafb;
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .stat-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.25rem 0;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .stat-row:last-child {
                border-bottom: none;
            }
            
            .stat-row span {
                color: #6b7280;
                font-size: 0.875rem;
            }
            
            .stat-row strong {
                color: #1f2937;
                font-weight: 600;
            }
            
            .region-actions {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-link {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.75rem;
                border-radius: 0.5rem;
                font-weight: 600;
                font-size: 0.875rem;
                text-decoration: none;
                transition: all 0.15s ease;
                border: none;
                cursor: pointer;
            }
            
            .action-link.primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .action-link.secondary {
                background: #f3f4f6;
                color: #6b7280;
            }
            
            .action-link:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
        `;
        document.head.appendChild(style);
    }

    closeMapInfo() {
        const panel = document.getElementById('map-info-panel');
        if (panel) {
            panel.classList.remove('active');
        }
    }

    renderRegions() {
        const container = document.getElementById('regions-grid');
        if (!container) return;

        if (this.filteredRegions.length === 0) {
            container.innerHTML = this.renderEmptyState();
            return;
        }

        container.innerHTML = this.filteredRegions.map(region =>
            this.renderRegionCard(region)
        ).join('');

        // Update results count
        this.updateResultsCount();
    }

    renderRegionCard(region) {
        return `
            <div class="card-modern region-card" data-region-id="${region.id}" data-country-id="${region.country_id}">
                <div class="card-image">
                    ${region.cover_image ?
                `<img src="${region.cover_image}" alt="${region.name}" loading="lazy">` :
                `<div class="card-placeholder">
                            <i class="fas fa-mountain"></i>
                        </div>`
            }
                    <div class="card-overlay">
                        <div class="card-badges">
                            ${region.difficulty_level ?
                `<span class="badge badge-difficulty">${region.difficulty_level}</span>` : ''
            }
                            ${region.best_season ?
                `<span class="badge badge-season">${this.formatSeason(region.best_season)}</span>` : ''
            }
                        </div>
                    </div>
                </div>
                
                <div class="card-content">
                    <div class="card-header">
                        <h3 class="card-title">${region.name}</h3>
                        ${region.country ?
                `<span class="card-country">${region.country.name}</span>` : ''
            }
                    </div>
                    
                    ${region.description ? `
                        <p class="card-description">
                            ${this.truncateText(region.description, 120)}
                        </p>
                    ` : ''}
                    
                    <div class="card-stats">
                        <div class="stat-mini">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${region.sectors_count || 0} secteurs</span>
                        </div>
                        <div class="stat-mini">
                            <i class="fas fa-route"></i>
                            <span>${region.routes_count || 0} voies</span>
                        </div>
                        ${region.altitude ? `
                            <div class="stat-mini">
                                <i class="fas fa-mountain"></i>
                                <span>${region.altitude}m</span>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="card-actions">
                        <a href="/regions/${region.id}" class="btn-primary">
                            <i class="fas fa-eye"></i>
                            Découvrir
                        </a>
                        <button class="btn-secondary" onclick="regionsIndex.showRegionOnMap(${region.id})">
                            <i class="fas fa-map"></i>
                            Carte
                        </button>
                        ${region.coordinates_lat && region.coordinates_lng ? `
                            <button class="btn-weather" onclick="regionsIndex.showWeather(${region.id})">
                                <i class="fas fa-cloud-sun"></i>
                                Météo
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    renderEmptyState() {
        return `
            <div class="empty-state">
                <i class="fas fa-mountain"></i>
                <h3>Aucune région trouvée</h3>
                <p>Aucune région ne correspond à vos critères de recherche.</p>
                <button class="btn-primary" onclick="regionsIndex.clearFilters()">
                    <i class="fas fa-refresh"></i>
                    Effacer les filtres
                </button>
            </div>
        `;
    }

    formatSeason(season) {
        const seasons = {
            spring: 'Printemps',
            summer: 'Été',
            autumn: 'Automne',
            winter: 'Hiver',
            'year-round': 'Toute l\'année'
        };
        return seasons[season] || season;
    }

    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }

    updateResultsCount() {
        // Update any results counter in the UI
        const total = this.filteredRegions.length;
        const counters = document.querySelectorAll('.results-count');
        counters.forEach(counter => {
            counter.textContent = `${total} région${total !== 1 ? 's' : ''} trouvée${total !== 1 ? 's' : ''}`;
        });
    }

    showRegionOnMap(regionId) {
        const region = this.regions.find(r => r.id == regionId);
        if (!region || !region.coordinates_lat || !region.coordinates_lng) return;

        // Switch to map view if not already
        if (this.currentView !== 'map') {
            this.toggleView();
        }

        // Wait for map to initialize
        setTimeout(() => {
            if (this.map) {
                this.map.setView([region.coordinates_lat, region.coordinates_lng], 12);
                this.showRegionDetails(region);
            }
        }, 500);
    }

    async showWeather(regionId) {
        const region = this.regions.find(r => r.id == regionId);
        if (!region || !region.coordinates_lat || !region.coordinates_lng) {
            this.showError('Coordonnées non disponibles pour cette région');
            return;
        }

        const modal = document.getElementById('weather-modal');
        const content = document.getElementById('weather-content');

        if (!modal || !content) return;

        modal.classList.add('active');
        content.innerHTML = '<div class="loading-weather">Chargement des données météo...</div>';

        try {
            const weatherData = await this.fetchWeatherData(region.coordinates_lat, region.coordinates_lng);
            content.innerHTML = this.renderWeatherData(weatherData, region.name);
        } catch (error) {
            console.error('Error fetching weather:', error);
            content.innerHTML = '<div class="error-weather">Erreur lors du chargement des données météo</div>';
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

        // Cache the result
        this.weatherCache.set(cacheKey, {
            data: data,
            timestamp: Date.now()
        });

        return data;
    }

    renderWeatherData(data, regionName) {
        const temp = Math.round(data.main.temp);
        const feelsLike = Math.round(data.main.feels_like);
        const humidity = data.main.humidity;
        const windSpeed = Math.round(data.wind.speed * 3.6); // Convert m/s to km/h
        const description = data.weather[0].description;
        const icon = data.weather[0].icon;

        return `
            <div class="weather-display">
                <div class="weather-header">
                    <h4>${regionName}</h4>
                    <div class="weather-icon">
                        <img src="https://openweathermap.org/img/wn/${icon}@2x.png" alt="${description}">
                    </div>
                </div>
                
                <div class="weather-main">
                    <div class="temp-display">
                        <span class="temp-value">${temp}°C</span>
                        <span class="feels-like">Ressenti ${feelsLike}°C</span>
                    </div>
                    <div class="weather-desc">${description}</div>
                </div>
                
                <div class="weather-details">
                    <div class="weather-detail">
                        <i class="fas fa-tint"></i>
                        <span>Humidité</span>
                        <strong>${humidity}%</strong>
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-wind"></i>
                        <span>Vent</span>
                        <strong>${windSpeed} km/h</strong>
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-thermometer-half"></i>
                        <span>Min/Max</span>
                        <strong>${Math.round(data.main.temp_min)}° / ${Math.round(data.main.temp_max)}°</strong>
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-eye"></i>
                        <span>Visibilité</span>
                        <strong>${data.visibility ? (data.visibility / 1000).toFixed(1) + ' km' : 'N/A'}</strong>
                    </div>
                </div>
                
                <div class="climbing-conditions">
                    <h5>Conditions d'escalade</h5>
                    <div class="conditions-grid">
                        ${this.getClimbingConditions(data)}
                    </div>
                </div>
            </div>
        `;
    }

    getClimbingConditions(weatherData) {
        const temp = weatherData.main.temp;
        const humidity = weatherData.main.humidity;
        const windSpeed = weatherData.wind.speed * 3.6;
        const isRaining = weatherData.weather[0].main.includes('Rain');

        let conditions = [];

        // Temperature condition
        if (temp >= 15 && temp <= 25) {
            conditions.push({ icon: 'fas fa-thermometer-half', text: 'Température idéale', status: 'good' });
        } else if (temp < 5) {
            conditions.push({ icon: 'fas fa-snowflake', text: 'Température froide', status: 'poor' });
        } else if (temp > 30) {
            conditions.push({ icon: 'fas fa-sun', text: 'Température élevée', status: 'warning' });
        } else {
            conditions.push({ icon: 'fas fa-thermometer-half', text: 'Température acceptable', status: 'ok' });
        }

        // Humidity condition
        if (humidity > 80) {
            conditions.push({ icon: 'fas fa-tint', text: 'Humidité élevée', status: 'warning' });
        } else if (humidity < 30) {
            conditions.push({ icon: 'fas fa-tint-slash', text: 'Air sec', status: 'good' });
        }

        // Wind condition
        if (windSpeed > 30) {
            conditions.push({ icon: 'fas fa-wind', text: 'Vent fort', status: 'poor' });
        } else if (windSpeed < 10) {
            conditions.push({ icon: 'fas fa-leaf', text: 'Vent calme', status: 'good' });
        }

        // Rain condition
        if (isRaining) {
            conditions.push({ icon: 'fas fa-cloud-rain', text: 'Conditions humides', status: 'poor' });
        } else {
            conditions.push({ icon: 'fas fa-sun', text: 'Conditions sèches', status: 'good' });
        }

        return conditions.map(condition => `
            <div class="condition-item ${condition.status}">
                <i class="${condition.icon}"></i>
                <span>${condition.text}</span>
            </div>
        `).join('');
    }

    closeWeatherModal() {
        const modal = document.getElementById('weather-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    centerMapOnSwitzerland() {
        if (this.map) {
            this.map.setView([46.8182, 8.2275], 8);
        }
    }

    toggleMapLayers() {
        // This would open a layers panel - implement based on needs
        console.log('Toggle map layers');
    }

    toggleHikingPaths() {
        // Add hiking paths overlay
        console.log('Toggle hiking paths');
    }

    closeAllModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => modal.classList.remove('active'));

        this.closeMapInfo();
    }

    showLoading(message = 'Chargement...') {
        const overlay = document.getElementById('loading-overlay');
        const text = document.getElementById('loading-text');

        if (overlay) {
            if (text) text.textContent = message;
            overlay.classList.add('active');
        }
    }

    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }

    showError(message) {
        // Simple error notification - could be enhanced with a toast system
        alert(message);
    }

    preloadMapResources() {
        // Preload map tiles and resources
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/8/134/91.jpeg';
        document.head.appendChild(link);
    }
}

// Global functions for onclick handlers
window.showRegionOnMap = function (regionId) {
    if (window.regionsIndex) {
        window.regionsIndex.showRegionOnMap(regionId);
    }
};

window.showWeather = function (regionId) {
    if (window.regionsIndex) {
        window.regionsIndex.showWeather(regionId);
    }
};

window.closeMapInfo = function () {
    if (window.regionsIndex) {
        window.regionsIndex.closeMapInfo();
    }
};

window.closeWeatherModal = function () {
    if (window.regionsIndex) {
        window.regionsIndex.closeWeatherModal();
    }
};

window.centerMapOnSwitzerland = function () {
    if (window.regionsIndex) {
        window.regionsIndex.centerMapOnSwitzerland();
    }
};

window.toggleMapLayers = function () {
    if (window.regionsIndex) {
        window.regionsIndex.toggleMapLayers();
    }
};

window.toggleHikingPaths = function () {
    if (window.regionsIndex) {
        window.regionsIndex.toggleHikingPaths();
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    window.regionsIndex = new RegionsIndex();
});

// Add weather modal styles
document.addEventListener('DOMContentLoaded', function () {
    const style = document.createElement('style');
    style.textContent = `
        .weather-display {
            max-width: 100%;
        }
        
        .weather-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .weather-header h4 {
            margin: 0;
            color: #1f2937;
        }
        
        .weather-icon img {
            width: 60px;
            height: 60px;
        }
        
        .weather-main {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .temp-display {
            margin-bottom: 0.5rem;
        }
        
        .temp-value {
            font-size: 3rem;
            font-weight: 800;
            color: #1f2937;
        }
        
        .feels-like {
            display: block;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .weather-desc {
            color: #6b7280;
            font-size: 1.125rem;
            text-transform: capitalize;
        }
        
        .weather-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .weather-detail {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.5rem;
        }
        
        .weather-detail i {
            color: #667eea;
            font-size: 1.25rem;
        }
        
        .weather-detail span {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .weather-detail strong {
            font-weight: 600;
            color: #1f2937;
        }
        
        .climbing-conditions h5 {
            margin: 0 0 1rem 0;
            color: #1f2937;
            font-weight: 700;
        }
        
        .conditions-grid {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .condition-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .condition-item.good {
            background: #f0fdf4;
            color: #16a34a;
        }
        
        .condition-item.ok {
            background: #fefce8;
            color: #ca8a04;
        }
        
        .condition-item.warning {
            background: #fef3c7;
            color: #d97706;
        }
        
        .condition-item.poor {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .condition-item i {
            width: 1.25rem;
            text-align: center;
        }
        
        .loading-weather,
        .error-weather {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }
        
        .error-weather {
            color: #dc2626;
        }
    `;
    document.head.appendChild(style);
});