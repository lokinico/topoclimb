// public/js/pages/sectors/show.js - VERSION CORRIGÉE

/**
 * Polyfills pour compatibilité navigateurs
 */
if (!Element.prototype.closest) {
    Element.prototype.closest = function (selector) {
        var element = this;
        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) {
                return element;
            }
            element = element.parentNode;
        }
        return null;
    };
}

if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector ||
        Element.prototype.webkitMatchesSelector;
}

/**
 * JavaScript pour la page d'affichage d'un secteur - VERSION SANS AJAX
 */
class SectorShowPage {
    constructor() {
        this.map = null;
        this.swiper = null;
        this.routesData = [];
        this.currentFilter = 'all';
        this.currentView = 'list';

        this.init();
    }

    init() {
        console.log('🏔️ Initializing Sector Show Page...');

        try {
            // Initialiser les composants dans l'ordre
            this.initializeSwiper();
            this.initializeMap();
            this.initializeRouteFilters();
            this.initializeViewToggle();
            this.initializeActions();

            // Charger les données des voies (SANS AJAX)
            this.loadRoutesFromDOM();

            console.log('✅ Sector page initialized successfully');
        } catch (error) {
            console.error('❌ Error initializing sector page:', error);
        }
    }

    /**
     * Charger les données des voies depuis le DOM (PAS D'AJAX)
     */
    loadRoutesFromDOM() {
        console.log('Loading routes data from DOM...');

        const routeElements = document.querySelectorAll('.route-item');
        this.routesData = [];

        if (routeElements.length === 0) {
            console.log('No routes found in DOM');
            return;
        }

        routeElements.forEach((element, index) => {
            try {
                const titleElement = element.querySelector('.card-title a');

                if (titleElement) {
                    const routeData = {
                        id: element.dataset.routeId || index,
                        name: titleElement.textContent.trim(),
                        difficulty: element.dataset.difficulty || '',
                        style: element.dataset.style || '',
                        length: element.dataset.length || null,
                        beauty: element.dataset.beauty || '0',
                        equipment: element.dataset.equipment || ''
                    };
                    this.routesData.push(routeData);
                }
            } catch (error) {
                console.warn('Error processing route:', error);
            }
        });

        console.log(`✅ Routes loaded: ${this.routesData.length} routes`);
    }

    /**
     * Initialiser le slider Swiper pour la galerie
     */
    initializeSwiper() {
        const swiperElement = document.querySelector('.swiper');
        if (!swiperElement) {
            console.log('No swiper element found');
            return;
        }

        // Vérifier si Swiper est disponible
        if (typeof Swiper === 'undefined') {
            console.warn('Swiper library not loaded');
            return;
        }

        try {
            this.swiper = new Swiper('.swiper', {
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    dynamicBullets: true,
                },
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                effect: 'slide',
                speed: 500,
                lazy: {
                    loadPrevNext: true,
                },
                keyboard: {
                    enabled: true,
                }
            });

            console.log('✅ Swiper initialized');
        } catch (error) {
            console.error('❌ Error initializing Swiper:', error);
        }
    }

    /**
     * Initialiser la carte Leaflet
     */
    initializeMap() {
        const mapElement = document.getElementById('map');
        if (!mapElement) {
            console.log('No map element found');
            return;
        }

        if (typeof L === 'undefined') {
            console.warn('Leaflet library not loaded');
            return;
        }

        const lat = parseFloat(mapElement.dataset.lat);
        const lng = parseFloat(mapElement.dataset.lng);
        const sectorName = mapElement.dataset.name || 'Secteur';

        if (isNaN(lat) || isNaN(lng)) {
            console.warn('Invalid coordinates for map');
            return;
        }

        try {
            // Créer la carte
            this.map = L.map('map').setView([lat, lng], 15);

            // Ajouter la couche de tuiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18,
            }).addTo(this.map);

            // Marker pour le secteur
            const marker = L.marker([lat, lng])
                .addTo(this.map)
                .bindPopup(`
                    <div class="map-popup">
                        <h6>${sectorName}</h6>
                        <p>Secteur d'escalade</p>
                        <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="btn btn-sm btn-primary">
                            Google Maps
                        </a>
                    </div>
                `);

            console.log('✅ Map initialized');
        } catch (error) {
            console.error('❌ Error initializing map:', error);
        }
    }

    /**
     * Initialiser les filtres des voies
     */
    initializeRouteFilters() {
        // Filtres par onglets
        const filterTabs = document.querySelectorAll('#route-filter-tabs a');
        filterTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();

                // Mettre à jour les onglets actifs
                filterTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                this.currentFilter = tab.dataset.filter || 'all';
                this.filterRoutes();
            });
        });

        // Recherche en temps réel
        const searchInput = document.querySelector('#route-search');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => {
                this.filterRoutes();
            }, 300));
        }

        console.log('✅ Route filters initialized');
    }

    /**
     * Initialiser le basculement de vue (liste/grille)
     */
    initializeViewToggle() {
        const listViewBtn = document.getElementById('list-view');
        const cardViewBtn = document.getElementById('card-view');

        if (!listViewBtn || !cardViewBtn) {
            console.log('View toggle buttons not found');
            return;
        }

        listViewBtn.addEventListener('change', () => {
            if (listViewBtn.checked) {
                this.currentView = 'list';
                this.updateRoutesView();
            }
        });

        cardViewBtn.addEventListener('change', () => {
            if (cardViewBtn.checked) {
                this.currentView = 'grid';
                this.updateRoutesView();
            }
        });

        console.log('✅ View toggle initialized');
    }

    /**
     * Mettre à jour la vue des voies
     */
    updateRoutesView() {
        const routeItems = document.querySelectorAll('.route-item');

        routeItems.forEach(item => {
            if (this.currentView === 'grid') {
                item.className = 'col-md-6 route-item';
            } else {
                item.className = 'col-12 route-item';
            }
        });
    }

    /**
     * Filtrer les voies
     */
    filterRoutes() {
        const routeItems = document.querySelectorAll('.route-item');
        const searchTerm = document.querySelector('#route-search')?.value.toLowerCase() || '';

        let visibleCount = 0;

        routeItems.forEach(item => {
            const titleElement = item.querySelector('.card-title a');
            const routeName = titleElement ? titleElement.textContent.toLowerCase() : '';
            const routeStyle = item.dataset.style || '';

            let shouldShow = true;

            // Filtre par style
            if (this.currentFilter !== 'all' && routeStyle !== this.currentFilter) {
                shouldShow = false;
            }

            // Filtre par recherche
            if (searchTerm && !routeName.includes(searchTerm)) {
                shouldShow = false;
            }

            // Afficher/masquer l'élément
            if (shouldShow) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Mettre à jour le compteur
        this.updateRouteCount(visibleCount);
    }

    /**
     * Mettre à jour le compteur de voies
     */
    updateRouteCount(count) {
        const counter = document.querySelector('.routes-count');
        if (counter) {
            counter.textContent = `(${count})`;
        }
    }

    /**
     * Initialiser les actions de la page
     */
    initializeActions() {
        // Partage
        this.initializeSocialShare();

        // Actions sur les voies
        this.initializeRouteActions();

        console.log('✅ Actions initialized');
    }

    /**
     * Initialiser le partage social
     */
    initializeSocialShare() {
        const shareBtn = document.querySelector('.share-sector');
        if (!shareBtn) return;

        shareBtn.addEventListener('click', () => {
            const url = window.location.href;
            const title = document.title;

            if (navigator.share) {
                navigator.share({ title: title, url: url });
            } else {
                // Fallback: copier l'URL
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(() => {
                        this.showToast('Lien copié!', 'success');
                    });
                }
            }
        });
    }

    /**
     * Initialiser les actions sur les voies
     */
    initializeRouteActions() {
        // Actions rapides sur les voies
        document.addEventListener('click', (e) => {
            // Utiliser le polyfill closest()
            const action = e.target.closest ? e.target.closest('[data-route-action]') : null;
            if (!action) return;

            e.preventDefault();
            const routeId = action.dataset.routeId;
            const actionType = action.dataset.routeAction;

            console.log(`Route action: ${actionType} for route ${routeId}`);

            switch (actionType) {
                case 'quick-log':
                    this.openQuickLogModal(routeId);
                    break;
                case 'add-favorite':
                    this.toggleRouteFavorite(routeId);
                    break;
            }
        });
    }

    /**
     * Afficher un toast message
     */
    showToast(message, type = 'info') {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
            color: white;
            border-radius: 5px;
            z-index: 10000;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    /**
     * Fonction debounce pour optimiser les recherches
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Méthodes stub pour éviter les erreurs
    openQuickLogModal(routeId) {
        console.log('Quick log modal for route:', routeId);
        this.showToast('Fonctionnalité en développement', 'info');
    }

    toggleRouteFavorite(routeId) {
        console.log('Toggle favorite for route:', routeId);
        this.showToast('Fonctionnalité en développement', 'info');
    }
}

// Initialiser seulement quand le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.sectorShowPage = new SectorShowPage();
    });
} else {
    // DOM déjà chargé
    window.sectorShowPage = new SectorShowPage();
}