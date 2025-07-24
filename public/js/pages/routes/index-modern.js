/**
 * Routes Index Page - Version moderne modulaire
 * Page de listing des voies d'escalade avec architecture moderne
 */

// Enregistrement du module de page routes index
TopoclimbCH.modules.register('page-routes-index', ['utils', 'api', 'ui'], async (utils, api, ui) => {
    
    class RoutesIndexPage {
        constructor() {
            this.routes = [];
            this.filters = {
                search: '',
                difficulty: '',
                style: '',
                sector: '',
                region: ''
            };
            this.currentPage = 1;
            this.itemsPerPage = 20;
            this.components = {};
            this.initialized = false;
        }
        
        /**
         * Initialise la page routes
         */
        async init() {
            if (this.initialized) {
                console.warn('Routes index page already initialized');
                return;
            }
            
            console.log('🧗 Initializing routes index page');
            
            try {
                // Charger les données initiales
                await this.loadInitialData();
                
                // Initialiser les composants
                await this.initializeComponents();
                
                // Configuration des fonctionnalités
                this.setupFilters();
                this.setupSearch();
                this.setupSorting();
                this.setupPagination();
                this.setupInteractions();
                
                this.initialized = true;
                console.log('✅ Routes index page initialized successfully');
                
            } catch (error) {
                console.error('❌ Failed to initialize routes index page:', error);
                this.initializeFallback();
            }
        }
        
        /**
         * Charge les données initiales
         */
        async loadInitialData() {
            try {
                // Charger les routes avec les filtres actuels
                await this.loadRoutes();
                
                // Charger les options de filtres
                await this.loadFilterOptions();
                
                console.log('📊 Initial data loaded');
            } catch (error) {
                console.error('Failed to load initial data:', error);
                throw error;
            }
        }
        
        /**
         * Charge les routes avec pagination et filtres
         */
        async loadRoutes() {
            try {
                const response = await api.get('/api/routes', {
                    page: this.currentPage,
                    per_page: this.itemsPerPage,
                    search: this.filters.search,
                    difficulty: this.filters.difficulty,
                    style: this.filters.style,
                    sector_id: this.filters.sector,
                    region_id: this.filters.region
                });
                
                this.routes = response.data || [];
                this.totalRoutes = response.total || 0;
                this.totalPages = Math.ceil(this.totalRoutes / this.itemsPerPage);
                
                console.log(`📋 Loaded ${this.routes.length} routes`);
                
                // Mettre à jour l'affichage
                this.renderRoutes();
                this.updatePagination();
                
            } catch (error) {
                console.error('Failed to load routes:', error);
                ui.toast.error('Erreur lors du chargement des voies');
            }
        }
        
        /**
         * Charge les options pour les filtres
         */
        async loadFilterOptions() {
            try {
                const [difficultiesResponse, sectorsResponse, regionsResponse] = await Promise.all([
                    api.get('/api/difficulties'),
                    api.get('/api/sectors'),
                    api.get('/api/regions')
                ]);
                
                this.difficulties = difficultiesResponse.data || [];
                this.sectors = sectorsResponse.data || [];
                this.regions = regionsResponse.data || [];
                
                this.populateFilterSelects();
                
            } catch (error) {
                console.error('Failed to load filter options:', error);
            }
        }
        
        /**
         * Remplit les selects de filtres
         */
        populateFilterSelects() {
            // Difficultés
            const difficultySelect = document.getElementById('difficulty-filter');
            if (difficultySelect && this.difficulties.length > 0) {
                difficultySelect.innerHTML = '<option value="">Toutes difficultés</option>';
                this.difficulties.forEach(diff => {
                    difficultySelect.innerHTML += `<option value="${diff.grade}">${diff.grade}</option>`;
                });
            }
            
            // Secteurs
            const sectorSelect = document.getElementById('sector-filter');
            if (sectorSelect && this.sectors.length > 0) {
                sectorSelect.innerHTML = '<option value="">Tous secteurs</option>';
                this.sectors.forEach(sector => {
                    sectorSelect.innerHTML += `<option value="${sector.id}">${sector.name}</option>`;
                });
            }
            
            // Régions
            const regionSelect = document.getElementById('region-filter');
            if (regionSelect && this.regions.length > 0) {
                regionSelect.innerHTML = '<option value="">Toutes régions</option>';
                this.regions.forEach(region => {
                    regionSelect.innerHTML += `<option value="${region.id}">${region.name}</option>`;
                });
            }
        }
        
        /**
         * Initialise tous les composants
         */
        async initializeComponents() {
            // 1. Cartes en mode liste
            this.initializeRoutesCards();
            
            // 2. Mode vue (grille/liste)
            this.initializeViewModes();
            
            // 3. Statistiques générales
            this.initializeStatistics();
            
            // 4. Actions groupées
            this.initializeGroupActions();
        }
        
        /**
         * Améliore l'affichage des cartes de routes
         */
        initializeRoutesCards() {
            const routeCards = document.querySelectorAll('.route-card');
            
            routeCards.forEach((card, index) => {
                // Animation d'apparition échelonnée
                card.style.animationDelay = `${index * 0.05}s`;
                card.classList.add('fade-in-up');
                
                // Hover effects
                card.addEventListener('mouseenter', () => {
                    card.classList.add('elevated');
                });
                
                card.addEventListener('mouseleave', () => {
                    card.classList.remove('elevated');
                });
                
                // Quick actions
                this.addQuickActions(card);
            });
            
            console.log(`🃏 Enhanced ${routeCards.length} route cards`);
        }
        
        /**
         * Ajoute des actions rapides aux cartes
         */
        addQuickActions(card) {
            const routeId = card.dataset.routeId;
            if (!routeId) return;
            
            // Bouton favoris rapide
            const favoriteBtn = card.querySelector('.quick-favorite');
            if (favoriteBtn) {
                favoriteBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleFavorite(routeId, favoriteBtn);
                });
            }
            
            // Bouton partage rapide
            const shareBtn = card.querySelector('.quick-share');
            if (shareBtn) {
                shareBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.shareRoute(routeId);
                });
            }
            
            // Preview au hover
            card.addEventListener('mouseenter', () => {
                this.showRoutePreview(routeId, card);
            });
        }
        
        /**
         * Configuration des filtres
         */
        setupFilters() {
            const filterElements = document.querySelectorAll('.route-filter');
            
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
            
            // Filtres avancés
            const advancedToggle = document.getElementById('advanced-filters-toggle');
            if (advancedToggle) {
                advancedToggle.addEventListener('click', () => {
                    this.toggleAdvancedFilters();
                });
            }
        }
        
        /**
         * Applique les filtres
         */
        async applyFilters() {
            // Récupérer les valeurs des filtres
            this.filters.search = document.getElementById('search-input')?.value || '';
            this.filters.difficulty = document.getElementById('difficulty-filter')?.value || '';
            this.filters.style = document.getElementById('style-filter')?.value || '';
            this.filters.sector = document.getElementById('sector-filter')?.value || '';
            this.filters.region = document.getElementById('region-filter')?.value || '';
            
            // Reset pagination
            this.currentPage = 1;
            
            // Recharger les données
            await this.loadRoutes();
            
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
                difficulty: '',
                style: '',
                sector: '',
                region: ''
            };
            
            // Reset des champs
            document.querySelectorAll('.route-filter').forEach(filter => {
                filter.value = '';
            });
            
            const searchInput = document.getElementById('search-input');
            if (searchInput) searchInput.value = '';
            
            // Recharger
            await this.loadRoutes();
            
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
            
            // Raccourcis clavier
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    this.applyFilters();
                }
            });
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
                const response = await api.get('/api/routes', {
                    ...this.getFiltersParams(),
                    sort: sortBy,
                    page: this.currentPage,
                    per_page: this.itemsPerPage
                });
                
                this.routes = response.data || [];
                this.renderRoutes();
                
                console.log(`📊 Routes sorted by: ${sortBy}`);
                
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
            await this.loadRoutes();
            
            // Scroll vers le haut
            document.querySelector('.routes-container')?.scrollIntoView({
                behavior: 'smooth'
            });
        }
        
        /**
         * Rendu des routes
         */
        renderRoutes() {
            const container = document.getElementById('routes-container');
            if (!container) return;
            
            if (this.routes.length === 0) {
                container.innerHTML = this.renderEmptyState();
                return;
            }
            
            const html = this.routes.map(route => this.renderRouteCard(route)).join('');
            container.innerHTML = html;
            
            // Réinitialiser les interactions
            this.initializeRoutesCards();
        }
        
        /**
         * Rendu d'une carte de route
         */
        renderRouteCard(route) {
            return `
                <div class="route-card card mb-3" data-route-id="${route.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="route-info">
                                <h5 class="card-title">
                                    <a href="/routes/${route.id}" class="text-decoration-none">
                                        ${utils.escapeHtml(route.name)}
                                    </a>
                                </h5>
                                <div class="route-meta text-muted small">
                                    <span class="difficulty-badge badge bg-primary me-2">
                                        ${route.difficulty || 'N/A'}
                                    </span>
                                    ${route.sector ? `<span class="me-2">📍 ${utils.escapeHtml(route.sector.name)}</span>` : ''}
                                    ${route.length ? `<span class="me-2">📏 ${route.length}m</span>` : ''}
                                </div>
                                ${route.comment ? `<p class="card-text mt-2">${utils.truncate(route.comment, 100)}</p>` : ''}
                            </div>
                            <div class="route-actions">
                                <button class="btn btn-sm btn-outline-secondary quick-favorite" title="Ajouter aux favoris">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary quick-share" title="Partager">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        ${route.beauty ? `
                            <div class="route-rating mt-2">
                                ${'★'.repeat(route.beauty)}${'☆'.repeat(5 - route.beauty)}
                            </div>
                        ` : ''}
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
                        <i class="fas fa-search fa-3x text-muted"></i>
                    </div>
                    <h4 class="text-muted">Aucune voie trouvée</h4>
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
            // Mode vue
            this.setupViewModes();
            
            // Export
            this.setupExport();
            
            // Actions groupées
            this.setupGroupActions();
        }
        
        /**
         * Configuration des modes de vue
         */
        setupViewModes() {
            const viewButtons = document.querySelectorAll('.view-mode-btn');
            
            viewButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const mode = btn.dataset.view;
                    this.changeViewMode(mode);
                });
            });
        }
        
        /**
         * Change le mode de vue
         */
        changeViewMode(mode) {
            const container = document.getElementById('routes-container');
            if (!container) return;
            
            // Mettre à jour les classes
            container.className = `routes-${mode}`;
            
            // Mettre à jour les boutons
            document.querySelectorAll('.view-mode-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === mode);
            });
            
            // Sauvegarder la préférence
            localStorage.setItem('routes-view-mode', mode);
            
            console.log(`👁️ View mode changed to: ${mode}`);
        }
        
        /**
         * Toggle favoris
         */
        async toggleFavorite(routeId, button) {
            try {
                const response = await api.post(`/api/routes/${routeId}/favorite`);
                
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
         * Partage de route
         */
        shareRoute(routeId) {
            const route = this.routes.find(r => r.id == routeId);
            if (!route) return;
            
            const url = `${window.location.origin}/routes/${routeId}`;
            
            if (navigator.share) {
                navigator.share({
                    title: `${route.name} - Voie d'escalade`,
                    text: `Découvrez cette voie d'escalade : ${route.name}`,
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    ui.toast.success('Lien copié dans le presse-papiers !');
                });
            }
        }
        
        /**
         * Obtient les paramètres de filtres
         */
        getFiltersParams() {
            return {
                search: this.filters.search,
                difficulty: this.filters.difficulty,
                style: this.filters.style,
                sector_id: this.filters.sector,
                region_id: this.filters.region
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
            console.log('🔄 Initializing fallback mode for routes index');
            
            // Fonctionnalités de base seulement
            this.setupBasicSearch();
            this.setupBasicFilters();
            
            ui.toast.warning('Page chargée en mode simplifié', { duration: 5000 });
        }
        
        /**
         * Recherche de base
         */
        setupBasicSearch() {
            const searchInput = document.getElementById('search-input');
            if (!searchInput) return;
            
            searchInput.addEventListener('input', utils.debounce(() => {
                const searchTerm = searchInput.value.toLowerCase();
                const routeCards = document.querySelectorAll('.route-card');
                
                routeCards.forEach(card => {
                    const routeName = card.querySelector('.card-title').textContent.toLowerCase();
                    card.style.display = routeName.includes(searchTerm) ? 'block' : 'none';
                });
            }, 300));
        }
        
        /**
         * Filtres de base
         */
        setupBasicFilters() {
            const filterElements = document.querySelectorAll('.route-filter');
            
            filterElements.forEach(filter => {
                filter.addEventListener('change', () => {
                    // Logique de filtrage côté client simple
                    this.applyBasicFilters();
                });
            });
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
    
    return RoutesIndexPage;
});

// Auto-initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Vérifier qu'on est sur une page routes index
    if (!document.body.classList.contains('routes-index-page') && 
        !window.location.pathname.match(/^\/routes\/?$/)) {
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
        const RoutesIndexPage = await TopoclimbCH.modules.load('page-routes-index');
        const routesPage = new RoutesIndexPage();
        await routesPage.init();
        
        // Nettoyage
        window.addEventListener('beforeunload', () => {
            routesPage.cleanup();
        });
        
    } catch (error) {
        console.error('❌ Failed to initialize routes index page:', error);
    }
});

console.log('🧗 Routes Index Page module ready');