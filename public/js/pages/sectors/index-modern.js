/**
 * Sectors Index Page - Version moderne modulaire
 * Page de listing des secteurs d'escalade avec architecture moderne
 */

// Enregistrement du module de page sectors index
TopoclimbCH.modules.register('page-sectors-index', ['utils', 'api', 'ui'], async (utils, api, ui) => {
    
    class SectorsIndexPage {
        constructor() {
            this.sectors = [];
            this.filters = {
                search: '',
                region: '',
                site: '',
                difficulty_min: '',
                difficulty_max: ''
            };
            this.currentPage = 1;
            this.itemsPerPage = 24;
            this.components = {};
            this.initialized = false;
            this.mapView = false;
        }
        
        /**
         * Initialise la page secteurs
         */
        async init() {
            if (this.initialized) {
                console.warn('Sectors index page already initialized');
                return;
            }
            
            console.log('🏔️ Initializing sectors index page');
            
            try {
                // Charger les données initiales
                await this.loadInitialData();
                
                // Initialiser les composants
                await this.initializeComponents();
                
                // Configuration des fonctionnalités
                this.setupFilters();
                this.setupSearch();
                this.setupMapView();
                this.setupSorting();
                this.setupPagination();
                this.setupInteractions();
                
                this.initialized = true;
                console.log('✅ Sectors index page initialized successfully');
                
            } catch (error) {
                console.error('❌ Failed to initialize sectors index page:', error);
                this.initializeFallback();
            }
        }
        
        /**
         * Charge les données initiales
         */
        async loadInitialData() {
            try {
                // Charger les secteurs avec les filtres actuels
                await this.loadSectors();
                
                // Charger les options de filtres
                await this.loadFilterOptions();
                
                console.log('📊 Initial data loaded');
            } catch (error) {
                console.error('Failed to load initial data:', error);
                throw error;
            }
        }
        
        /**
         * Charge les secteurs avec pagination et filtres
         */
        async loadSectors() {
            try {
                const response = await api.get('/api/sectors', {
                    page: this.currentPage,
                    per_page: this.itemsPerPage,
                    search: this.filters.search,
                    region_id: this.filters.region,
                    site_id: this.filters.site,
                    difficulty_min: this.filters.difficulty_min,
                    difficulty_max: this.filters.difficulty_max
                });
                
                this.sectors = response.data || [];
                this.totalSectors = response.total || 0;
                this.totalPages = Math.ceil(this.totalSectors / this.itemsPerPage);
                
                console.log(`🏔️ Loaded ${this.sectors.length} sectors`);
                
                // Mettre à jour l'affichage
                if (this.mapView) {
                    this.renderSectorsMap();
                } else {
                    this.renderSectors();
                }
                this.updatePagination();
                
            } catch (error) {
                console.error('Failed to load sectors:', error);
                ui.toast.error('Erreur lors du chargement des secteurs');
            }
        }
        
        /**
         * Charge les options pour les filtres
         */
        async loadFilterOptions() {
            try {
                const [regionsResponse, sitesResponse] = await Promise.all([
                    api.get('/api/regions'),
                    api.get('/api/sites')
                ]);
                
                this.regions = regionsResponse.data || [];
                this.sites = sitesResponse.data || [];
                
                this.populateFilterSelects();
                
            } catch (error) {
                console.error('Failed to load filter options:', error);
            }
        }
        
        /**
         * Remplit les selects de filtres
         */
        populateFilterSelects() {
            // Régions
            const regionSelect = document.getElementById('region-filter');
            if (regionSelect && this.regions.length > 0) {
                regionSelect.innerHTML = '<option value="">Toutes régions</option>';
                this.regions.forEach(region => {
                    regionSelect.innerHTML += `<option value="${region.id}">${region.name}</option>`;
                });
            }
            
            // Sites
            const siteSelect = document.getElementById('site-filter');
            if (siteSelect && this.sites.length > 0) {
                siteSelect.innerHTML = '<option value="">Tous sites</option>';
                this.sites.forEach(site => {
                    siteSelect.innerHTML += `<option value="${site.id}">${site.name}</option>`;
                });
            }
        }
        
        /**
         * Initialise tous les composants
         */
        async initializeComponents() {
            // 1. Cartes de secteurs
            this.initializeSectorCards();
            
            // 2. Vue cartographique
            await this.initializeMapComponent();
            
            // 3. Statistiques générales
            this.initializeStatistics();
            
            // 4. Météo pour les secteurs
            this.initializeWeatherInfo();
        }
        
        /**
         * Améliore l'affichage des cartes de secteurs
         */
        initializeSectorCards() {
            const sectorCards = document.querySelectorAll('.sector-card');
            
            sectorCards.forEach((card, index) => {
                // Animation d'apparition échelonnée
                card.style.animationDelay = `${index * 0.08}s`;
                card.classList.add('fade-in-up');
                
                // Hover effects avec preview
                card.addEventListener('mouseenter', () => {
                    card.classList.add('elevated');
                    this.showSectorPreview(card);
                });
                
                card.addEventListener('mouseleave', () => {
                    card.classList.remove('elevated');
                    this.hideSectorPreview();
                });
                
                // Actions rapides
                this.addQuickActions(card);
            });
            
            console.log(`🃏 Enhanced ${sectorCards.length} sector cards`);
        }
        
        /**
         * Ajoute des actions rapides aux cartes
         */
        addQuickActions(card) {
            const sectorId = card.dataset.sectorId;
            if (!sectorId) return;
            
            // Bouton météo rapide
            const weatherBtn = card.querySelector('.quick-weather');
            if (weatherBtn) {
                weatherBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showWeatherPopup(sectorId);
                });
            }
            
            // Bouton navigation GPS
            const gpsBtn = card.querySelector('.quick-gps');
            if (gpsBtn) {
                gpsBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.openGPSNavigation(sectorId);
                });
            }
            
            // Bouton favoris
            const favoriteBtn = card.querySelector('.quick-favorite');
            if (favoriteBtn) {
                favoriteBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleFavorite(sectorId, favoriteBtn);
                });
            }
        }
        
        /**
         * Initialise le composant carte
         */
        async initializeMapComponent() {
            const mapContainer = document.getElementById('sectors-map');
            if (!mapContainer) return;
            
            try {
                // Charger le gestionnaire de carte si nécessaire
                if (!window.SwissMapManager) {
                    await this.loadScript('/js/components/swiss-map-manager.js');
                }
                
                const SwissMapManager = await TopoclimbCH.modules.load('swiss-map-manager');
                
                this.components.map = new SwissMapManager('sectors-map', {
                    center: [46.8, 8.2], // Centre de la Suisse
                    zoom: 8,
                    showControls: true
                });
                
                await this.components.map.init();
                console.log('🗺️ Sectors map initialized');
                
            } catch (error) {
                console.error('Map initialization failed:', error);
            }
        }
        
        /**
         * Configuration des filtres
         */
        setupFilters() {
            const filterElements = document.querySelectorAll('.sector-filter');
            
            filterElements.forEach(filter => {
                filter.addEventListener('change', () => {
                    this.applyFilters();
                });
            });
            
            // Reset filters
            const resetBtn = document.getElementById('reset-filters');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    this.resetFilters();
                });
            }
            
            // Filtre par région dépendant
            const regionFilter = document.getElementById('region-filter');
            const siteFilter = document.getElementById('site-filter');
            
            if (regionFilter && siteFilter) {
                regionFilter.addEventListener('change', () => {
                    this.updateSitesByRegion(regionFilter.value);
                });
            }
        }
        
        /**
         * Met à jour les sites selon la région
         */
        async updateSitesByRegion(regionId) {
            const siteFilter = document.getElementById('site-filter');
            if (!siteFilter) return;
            
            if (!regionId) {
                // Restaurer tous les sites
                this.populateFilterSelects();
                return;
            }
            
            try {
                const response = await api.get(`/api/regions/${regionId}/sites`);
                const sites = response.data || [];
                
                siteFilter.innerHTML = '<option value="">Tous sites</option>';
                sites.forEach(site => {
                    siteFilter.innerHTML += `<option value="${site.id}">${site.name}</option>`;
                });
                
            } catch (error) {
                console.error('Failed to load sites for region:', error);
            }
        }
        
        /**
         * Applique les filtres
         */
        async applyFilters() {
            // Récupérer les valeurs des filtres
            this.filters.search = document.getElementById('search-input')?.value || '';
            this.filters.region = document.getElementById('region-filter')?.value || '';
            this.filters.site = document.getElementById('site-filter')?.value || '';
            this.filters.difficulty_min = document.getElementById('difficulty-min')?.value || '';
            this.filters.difficulty_max = document.getElementById('difficulty-max')?.value || '';
            
            // Reset pagination
            this.currentPage = 1;
            
            // Recharger les données
            await this.loadSectors();
            
            // Mettre à jour l'URL
            this.updateUrlParams();
            
            console.log('🔍 Filters applied:', this.filters);
        }
        
        /**
         * Reset des filtres
         */
        async resetFilters() {
            this.filters = {
                search: '',
                region: '',
                site: '',
                difficulty_min: '',
                difficulty_max: ''
            };
            
            // Reset des champs
            document.querySelectorAll('.sector-filter').forEach(filter => {
                filter.value = '';
            });
            
            const searchInput = document.getElementById('search-input');
            if (searchInput) searchInput.value = '';
            
            // Recharger
            await this.loadSectors();
            
            ui.toast.info('Filtres réinitialisés');
        }
        
        /**
         * Configuration de la recherche
         */
        setupSearch() {
            const searchInput = document.getElementById('search-input');
            if (!searchInput) return;
            
            let searchTimeout;
            
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.applyFilters();
                }, 300);
            });
        }
        
        /**
         * Configuration de la vue carte
         */
        setupMapView() {
            const mapToggle = document.getElementById('map-view-toggle');
            if (!mapToggle) return;
            
            mapToggle.addEventListener('click', () => {
                this.toggleMapView();
            });
        }
        
        /**
         * Bascule entre vue liste et vue carte
         */
        async toggleMapView() {
            this.mapView = !this.mapView;
            
            const listContainer = document.getElementById('sectors-list');
            const mapContainer = document.getElementById('sectors-map');
            const toggleBtn = document.getElementById('map-view-toggle');
            
            if (this.mapView) {
                listContainer?.classList.add('d-none');
                mapContainer?.classList.remove('d-none');
                toggleBtn?.classList.add('active');
                
                await this.renderSectorsMap();
                
            } else {
                listContainer?.classList.remove('d-none');
                mapContainer?.classList.add('d-none');
                toggleBtn?.classList.remove('active');
                
                this.renderSectors();
            }
            
            console.log(`🗺️ Map view: ${this.mapView ? 'enabled' : 'disabled'}`);
        }
        
        /**
         * Rendu des secteurs sur la carte
         */
        async renderSectorsMap() {
            if (!this.components.map) return;
            
            // Nettoyer les marqueurs existants
            this.components.map.clearMarkers();
            
            // Ajouter les secteurs avec coordonnées
            this.sectors.forEach(sector => {
                if (sector.coordinates_lat && sector.coordinates_lng) {
                    const marker = this.components.map.addMarker(
                        sector.coordinates_lat,
                        sector.coordinates_lng,
                        {
                            fillColor: this.getSectorColor(sector),
                            popup: this.createSectorPopup(sector)
                        }
                    );
                    
                    // Click handler
                    marker.on('click', () => {
                        window.location.href = `/sectors/${sector.id}`;
                    });
                }
            });
            
            // Ajuster la vue pour inclure tous les marqueurs
            if (this.sectors.length > 0) {
                this.components.map.fitToMarkers();
            }
        }
        
        /**
         * Détermine la couleur d'un secteur
         */
        getSectorColor(sector) {
            // Couleur selon le niveau de difficulté dominant
            const avgDifficulty = sector.avg_difficulty || 5;
            
            if (avgDifficulty <= 4) return '#28a745'; // Vert - Facile
            if (avgDifficulty <= 6) return '#ffc107'; // Jaune - Modéré
            if (avgDifficulty <= 7) return '#fd7e14'; // Orange - Difficile
            return '#dc3545'; // Rouge - Très difficile
        }
        
        /**
         * Crée le popup d'un secteur
         */
        createSectorPopup(sector) {
            return `
                <div class="sector-popup">
                    <h6 class="mb-1">${utils.escapeHtml(sector.name)}</h6>
                    <p class="small text-muted mb-2">
                        ${sector.routes_count || 0} voies
                        ${sector.avg_difficulty ? ` • Moy: ${sector.avg_difficulty}` : ''}
                    </p>
                    <div class="popup-actions">
                        <a href="/sectors/${sector.id}" class="btn btn-sm btn-primary">
                            Voir détails
                        </a>
                    </div>
                </div>
            `;
        }
        
        /**
         * Configuration du tri
         */
        setupSorting() {
            const sortSelect = document.getElementById('sort-select');
            if (!sortSelect) return;
            
            sortSelect.addEventListener('change', () => {
                this.applySorting(sortSelect.value);
            });
        }
        
        /**
         * Applique le tri
         */
        async applySorting(sortBy) {
            try {
                const response = await api.get('/api/sectors', {
                    ...this.getFiltersParams(),
                    sort: sortBy,
                    page: this.currentPage,
                    per_page: this.itemsPerPage
                });
                
                this.sectors = response.data || [];
                
                if (this.mapView) {
                    this.renderSectorsMap();
                } else {
                    this.renderSectors();
                }
                
                console.log(`📊 Sectors sorted by: ${sortBy}`);
                
            } catch (error) {
                console.error('Sorting failed:', error);
                ui.toast.error('Erreur lors du tri');
            }
        }
        
        /**
         * Configuration de la pagination
         */
        setupPagination() {
            const paginationContainer = document.querySelector('.pagination-container');
            if (!paginationContainer) return;
            
            paginationContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('page-link')) {
                    e.preventDefault();
                    const page = parseInt(e.target.dataset.page);
                    if (page && page !== this.currentPage) {
                        this.goToPage(page);
                    }
                }
            });
        }
        
        /**
         * Navigue vers une page
         */
        async goToPage(page) {
            if (page < 1 || page > this.totalPages) return;
            
            this.currentPage = page;
            await this.loadSectors();
            
            // Scroll vers le haut
            document.querySelector('.sectors-container')?.scrollIntoView({
                behavior: 'smooth'
            });
        }
        
        /**
         * Rendu des secteurs
         */
        renderSectors() {
            const container = document.getElementById('sectors-container');
            if (!container) return;
            
            if (this.sectors.length === 0) {
                container.innerHTML = this.renderEmptyState();
                return;
            }
            
            const html = this.sectors.map(sector => this.renderSectorCard(sector)).join('');
            container.innerHTML = html;
            
            // Réinitialiser les interactions
            this.initializeSectorCards();
        }
        
        /**
         * Rendu d'une carte de secteur
         */
        renderSectorCard(sector) {
            return `
                <div class="sector-card card h-100" data-sector-id="${sector.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-1">
                                <a href="/sectors/${sector.id}" class="text-decoration-none">
                                    ${utils.escapeHtml(sector.name)}
                                </a>
                            </h5>
                            <div class="sector-actions">
                                <button class="btn btn-sm btn-outline-secondary quick-weather" 
                                        title="Météo" data-sector-id="${sector.id}">
                                    <i class="fas fa-cloud-sun"></i>
                                </button>
                                ${sector.coordinates_lat && sector.coordinates_lng ? `
                                    <button class="btn btn-sm btn-outline-secondary quick-gps" 
                                            title="Navigation GPS" data-sector-id="${sector.id}">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-outline-secondary quick-favorite" 
                                        title="Favoris" data-sector-id="${sector.id}">
                                    <i class="far fa-heart"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="sector-meta text-muted small mb-2">
                            ${sector.site_name ? `<span class="me-2">📍 ${utils.escapeHtml(sector.site_name)}</span>` : ''}
                            ${sector.region_name ? `<span class="me-2">🏔️ ${utils.escapeHtml(sector.region_name)}</span>` : ''}
                            ${sector.altitude ? `<span class="me-2">⛰️ ${sector.altitude}m</span>` : ''}
                        </div>
                        
                        ${sector.description ? `
                            <p class="card-text">${utils.truncate(sector.description, 100)}</p>
                        ` : ''}
                        
                        <div class="sector-stats row text-center mt-3">
                            <div class="col-4">
                                <div class="stat-value h6 mb-0">${sector.routes_count || 0}</div>
                                <div class="stat-label small text-muted">Voies</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value h6 mb-0">
                                    ${sector.difficulty_range || 'N/A'}
                                </div>
                                <div class="stat-label small text-muted">Difficultés</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value h6 mb-0">
                                    ${sector.avg_beauty ? '★'.repeat(Math.round(sector.avg_beauty)) : 'N/A'}
                                </div>
                                <div class="stat-label small text-muted">Beauté</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        /**
         * Rendu de l'état vide
         */
        renderEmptyState() {
            return `
                <div class="empty-state text-center py-5">
                    <div class="empty-icon mb-3">
                        <i class="fas fa-mountain fa-3x text-muted"></i>
                    </div>
                    <h4 class="text-muted">Aucun secteur trouvé</h4>
                    <p class="text-muted">Essayez de modifier vos critères de recherche</p>
                    <button class="btn btn-outline-primary" onclick="this.resetFilters()">
                        Réinitialiser les filtres
                    </button>
                </div>
            `;
        }
        
        /**
         * Met à jour la pagination
         */
        updatePagination() {
            const paginationContainer = document.querySelector('.pagination-container');
            if (!paginationContainer || this.totalPages <= 1) {
                if (paginationContainer) paginationContainer.style.display = 'none';
                return;
            }
            
            paginationContainer.style.display = 'block';
            
            let paginationHtml = '<nav><ul class="pagination justify-content-center">';
            
            // Bouton précédent
            paginationHtml += `
                <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${this.currentPage - 1}">Précédent</a>
                </li>
            `;
            
            // Pages
            const startPage = Math.max(1, this.currentPage - 2);
            const endPage = Math.min(this.totalPages, this.currentPage + 2);
            
            for (let page = startPage; page <= endPage; page++) {
                paginationHtml += `
                    <li class="page-item ${page === this.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${page}">${page}</a>
                    </li>
                `;
            }
            
            // Bouton suivant
            paginationHtml += `
                <li class="page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${this.currentPage + 1}">Suivant</a>
                </li>
            `;
            
            paginationHtml += '</ul></nav>';
            paginationContainer.innerHTML = paginationHtml;
        }
        
        /**
         * Configuration des interactions
         */
        setupInteractions() {
            // Actions spéciales
            this.setupWeatherActions();
            this.setupGPSActions();
            this.setupExport();
        }
        
        /**
         * Actions météo
         */
        setupWeatherActions() {
            // Sera utilisé par les boutons weather
        }
        
        /**
         * Actions GPS
         */
        setupGPSActions() {
            // Sera utilisé par les boutons GPS
        }
        
        /**
         * Affiche la météo d'un secteur
         */
        async showWeatherPopup(sectorId) {
            const sector = this.sectors.find(s => s.id == sectorId);
            if (!sector || !sector.coordinates_lat || !sector.coordinates_lng) {
                ui.toast.error('Coordonnées GPS non disponibles');
                return;
            }
            
            try {
                const weatherData = await api.get('/api/weather', {
                    lat: sector.coordinates_lat,
                    lng: sector.coordinates_lng
                });
                
                // Créer et afficher le popup météo
                const popup = this.createWeatherPopup(sector, weatherData);
                document.body.appendChild(popup);
                
            } catch (error) {
                console.error('Weather fetch failed:', error);
                ui.toast.error('Erreur lors du chargement de la météo');
            }
        }
        
        /**
         * Ouvre la navigation GPS
         */
        openGPSNavigation(sectorId) {
            const sector = this.sectors.find(s => s.id == sectorId);
            if (!sector || !sector.coordinates_lat || !sector.coordinates_lng) {
                ui.toast.error('Coordonnées GPS non disponibles');
                return;
            }
            
            const url = `https://www.google.com/maps/dir/?api=1&destination=${sector.coordinates_lat},${sector.coordinates_lng}`;
            window.open(url, '_blank');
        }
        
        /**
         * Toggle favoris
         */
        async toggleFavorite(sectorId, button) {
            try {
                const response = await api.post(`/api/sectors/${sectorId}/favorite`);
                
                if (response.success) {
                    const icon = button.querySelector('i');
                    const isFavorited = response.favorited;
                    
                    icon.className = isFavorited ? 'fas fa-heart text-danger' : 'far fa-heart';
                    button.title = isFavorited ? 'Retirer des favoris' : 'Ajouter aux favoris';
                    
                    ui.toast.success(isFavorited ? 'Ajouté aux favoris !' : 'Retiré des favoris');
                }
                
            } catch (error) {
                console.error('Favorite toggle failed:', error);
                ui.toast.error('Erreur lors de la gestion des favoris');
            }
        }
        
        /**
         * Obtient les paramètres de filtres
         */
        getFiltersParams() {
            return {
                search: this.filters.search,
                region_id: this.filters.region,
                site_id: this.filters.site,
                difficulty_min: this.filters.difficulty_min,
                difficulty_max: this.filters.difficulty_max
            };
        }
        
        /**
         * Met à jour les paramètres URL
         */
        updateUrlParams() {
            const params = new URLSearchParams();
            
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            
            if (this.currentPage > 1) {
                params.set('page', this.currentPage);
            }
            
            const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
            window.history.replaceState({}, '', newUrl);
        }
        
        /**
         * Mode de secours
         */
        initializeFallback() {
            console.log('🔄 Initializing fallback mode for sectors index');
            
            // Fonctionnalités de base seulement
            this.setupBasicSearch();
            this.setupBasicFilters();
            
            ui.toast.warning('Page chargée en mode simplifié', { duration: 5000 });
        }
        
        /**
         * Nettoyage
         */
        cleanup() {
            // Nettoyer les composants
            Object.values(this.components).forEach(component => {
                if (component && component.destroy) {
                    component.destroy();
                }
            });
        }
    }
    
    return SectorsIndexPage;
});

// Auto-initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Vérifier qu'on est sur une page sectors index
    if (!document.body.classList.contains('sectors-index-page') && 
        !window.location.pathname.match(/^\/sectors\/?$/)) {
        return;
    }
    
    try {
        // Attendre TopoclimbCH
        if (!window.TopoclimbCH || !window.TopoclimbCH.initialized) {
            await new Promise(resolve => {
                const checkReady = () => {
                    if (window.TopoclimbCH && window.TopoclimbCH.initialized) {
                        resolve();
                    } else {
                        setTimeout(checkReady, 100);
                    }
                };
                checkReady();
            });
        }
        
        // Initialiser la page
        const SectorsIndexPage = await TopoclimbCH.modules.load('page-sectors-index');
        const sectorsPage = new SectorsIndexPage();
        await sectorsPage.init();
        
        // Nettoyage
        window.addEventListener('beforeunload', () => {
            sectorsPage.cleanup();
        });
        
    } catch (error) {
        console.error('❌ Failed to initialize sectors index page:', error);
    }
});

console.log('🏔️ Sectors Index Page module ready');