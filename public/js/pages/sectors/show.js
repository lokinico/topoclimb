// public/js/pages/sectors/show.js

/**
 * JavaScript pour la page d'affichage d'un secteur
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
        // Initialiser les composants
        this.initializeSwiper();
        this.initializeMap();
        this.initializeRouteFilters();
        this.initializeViewToggle();
        this.initializeWeatherWidget();
        this.initializeActions();

        // Charger les données des voies
        this.loadRoutesData();
    }

    /**
     * Initialiser le slider Swiper pour la galerie
     */
    initializeSwiper() {
        const swiperElement = document.querySelector('.swiper');
        if (!swiperElement || typeof Swiper === 'undefined') return;

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
            },
            mousewheel: {
                thresholdDelta: 50,
            }
        });

        // Pause au survol
        swiperElement.addEventListener('mouseenter', () => {
            if (this.swiper.autoplay) {
                this.swiper.autoplay.stop();
            }
        });

        swiperElement.addEventListener('mouseleave', () => {
            if (this.swiper.autoplay) {
                this.swiper.autoplay.start();
            }
        });
    }

    /**
     * Initialiser la carte Leaflet
     */
    initializeMap() {
        const mapElement = document.getElementById('map');
        if (!mapElement || typeof L === 'undefined') return;

        const lat = parseFloat(mapElement.dataset.lat);
        const lng = parseFloat(mapElement.dataset.lng);
        const sectorName = mapElement.dataset.name || 'Secteur';

        if (isNaN(lat) || isNaN(lng)) return;

        // Créer la carte
        this.map = L.map('map').setView([lat, lng], 15);

        // Ajouter la couche de tuiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(this.map);

        // Marker personnalisé pour le secteur
        const sectorIcon = L.divIcon({
            className: 'sector-marker',
            html: '<div class="sector-marker-content"><i class="fas fa-mountain"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        const marker = L.marker([lat, lng], { icon: sectorIcon })
            .addTo(this.map)
            .bindPopup(`
                <div class="map-popup">
                    <h6>${sectorName}</h6>
                    <p>Secteur d'escalade</p>
                    <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="btn btn-sm btn-primary">
                        Ouvrir dans Google Maps
                    </a>
                </div>
            `);

        // Ajouter des contrôles supplémentaires
        L.control.scale().addTo(this.map);

        // Géolocalisation
        if (navigator.geolocation) {
            const locationButton = L.control({ position: 'topleft' });
            locationButton.onAdd = () => {
                const button = L.DomUtil.create('button', 'leaflet-control-locate');
                button.innerHTML = '<i class="fas fa-location-arrow"></i>';
                button.title = 'Ma position';
                button.onclick = () => this.locateUser();
                return button;
            };
            locationButton.addTo(this.map);
        }
    }

    /**
     * Géolocaliser l'utilisateur
     */
    locateUser() {
        if (!navigator.geolocation) {
            showToast('Géolocalisation non disponible', 'error');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;

                // Ajouter un marker pour l'utilisateur
                L.marker([userLat, userLng])
                    .addTo(this.map)
                    .bindPopup('Votre position')
                    .openPopup();

                // Calculer la distance
                const sectorLat = parseFloat(document.getElementById('map').dataset.lat);
                const sectorLng = parseFloat(document.getElementById('map').dataset.lng);

                if (!isNaN(sectorLat) && !isNaN(sectorLng)) {
                    const distance = this.calculateDistance(userLat, userLng, sectorLat, sectorLng);
                    showToast(`Distance au secteur: ${distance.toFixed(1)} km`, 'info');
                }
            },
            (error) => {
                showToast('Impossible de vous localiser', 'error');
            }
        );
    }

    /**
     * Calculer la distance entre deux points
     */
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371; // Rayon de la Terre en km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    /**
     * Initialiser les filtres des voies
     */
    initializeRouteFilters() {
        const filterTabs = document.querySelectorAll('#route-filter-tabs a');

        filterTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();

                // Mettre à jour les onglets actifs
                filterTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                this.currentFilter = tab.dataset.filter;
                this.filterRoutes();
            });
        });

        // Filtres de recherche en temps réel
        const searchInput = document.querySelector('#route-search');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => {
                this.filterRoutes();
            }, 300));
        }

        // Filtres par difficulté
        const difficultyFilter = document.querySelector('#difficulty-filter');
        if (difficultyFilter) {
            difficultyFilter.addEventListener('change', () => {
                this.filterRoutes();
            });
        }
    }

    /**
     * Initialiser le basculement de vue (liste/grille)
     */
    initializeViewToggle() {
        const listViewBtn = document.getElementById('list-view');
        const cardViewBtn = document.getElementById('card-view');
        const routesContainer = document.getElementById('routes-list');

        if (!listViewBtn || !cardViewBtn || !routesContainer) return;

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
        const difficultyFilter = document.querySelector('#difficulty-filter')?.value || '';

        let visibleCount = 0;

        routeItems.forEach(item => {
            const routeName = item.querySelector('.card-title')?.textContent.toLowerCase() || '';
            const routeStyle = item.dataset.style || '';
            const routeDifficulty = item.dataset.difficulty || '';

            let shouldShow = true;

            // Filtre par style
            if (this.currentFilter !== 'all' && routeStyle !== this.currentFilter) {
                shouldShow = false;
            }

            // Filtre par recherche
            if (searchTerm && !routeName.includes(searchTerm)) {
                shouldShow = false;
            }

            // Filtre par difficulté
            if (difficultyFilter && !routeDifficulty.includes(difficultyFilter)) {
                shouldShow = false;
            }

            // Afficher/masquer l'élément avec animation
            if (shouldShow) {
                item.style.display = 'block';
                item.classList.add('fade-in');
                visibleCount++;
            } else {
                item.style.display = 'none';
                item.classList.remove('fade-in');
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
     * Charger les données des voies
     */
    async loadRoutesData() {
        const sectorId = document.querySelector('[data-sector-id]')?.dataset.sectorId;
        if (!sectorId) return;

        try {
            const response = await fetch(`/api/sectors/${sectorId}/routes`);
            if (response.ok) {
                this.routesData = await response.json();
            }
        } catch (error) {
            console.warn('Impossible de charger les données des voies:', error);
        }
    }

    /**
     * Initialiser le widget météo
     */
    initializeWeatherWidget() {
        const weatherWidget = document.querySelector('.weather-widget');
        if (!weatherWidget) return;

        // Actualiser les conditions météo
        const refreshBtn = weatherWidget.querySelector('.weather-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshWeatherData();
            });
        }

        // Mise à jour automatique toutes les 30 minutes
        setInterval(() => {
            this.refreshWeatherData();
        }, 30 * 60 * 1000);
    }

    /**
     * Actualiser les données météo
     */
    async refreshWeatherData() {
        const sectorId = document.querySelector('[data-sector-id]')?.dataset.sectorId;
        if (!sectorId) return;

        try {
            const response = await fetch(`/api/sectors/${sectorId}/weather`);
            if (response.ok) {
                const weatherData = await response.json();
                this.updateWeatherWidget(weatherData);
            }
        } catch (error) {
            console.warn('Impossible de charger les données météo:', error);
        }
    }

    /**
     * Mettre à jour le widget météo
     */
    updateWeatherWidget(data) {
        const widget = document.querySelector('.weather-widget');
        if (!widget || !data) return;

        // Mettre à jour la température
        const tempElement = widget.querySelector('.weather-temp');
        if (tempElement && data.temperature) {
            tempElement.textContent = `${data.temperature}°C`;
        }

        // Mettre à jour les conditions
        const conditionElement = widget.querySelector('.weather-condition');
        if (conditionElement && data.condition) {
            conditionElement.textContent = data.condition;
        }

        // Mettre à jour l'heure de dernière mise à jour
        const lastUpdate = widget.querySelector('.weather-last-update');
        if (lastUpdate) {
            lastUpdate.textContent = `Dernière mise à jour: ${new Date().toLocaleTimeString()}`;
        }
    }

    /**
     * Initialiser les actions de la page
     */
    initializeActions() {
        // Partage sur les réseaux sociaux
        this.initializeSocialShare();

        // Actions sur les voies
        this.initializeRouteActions();

        // Gestion des favoris
        this.initializeFavorites();
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
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                // Fallback: copier l'URL
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Lien copié dans le presse-papiers', 'success');
                });
            }
        });
    }

    /**
     * Initialiser les actions sur les voies
     */
    initializeRouteActions() {
        // Actions rapides sur les voies
        document.addEventListener('click', (e) => {
            const action = e.target.closest('[data-route-action]');
            if (!action) return;

            e.preventDefault();
            const routeId = action.dataset.routeId;
            const actionType = action.dataset.routeAction;

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
     * Ouvrir la modale de log rapide
     */
    openQuickLogModal(routeId) {
        // Créer une modale de log rapide
        const modal = document.createElement('div');
        modal.className = 'modal quick-log-modal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Enregistrer une ascension</h5>
                        <button type="button" class="btn-close" data-modal-close></button>
                    </div>
                    <div class="modal-body">
                        <form id="quick-log-form">
                            <input type="hidden" name="route_id" value="${routeId}">
                            <div class="mb-3">
                                <label>Type d'ascension</label>
                                <select name="ascent_type" class="form-select" required>
                                    <option value="onsight">À vue</option>
                                    <option value="flash">Flash</option>
                                    <option value="redpoint">Après travail</option>
                                    <option value="attempt">Tentative</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Date</label>
                                <input type="date" name="ascent_date" class="form-control" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        openModal(modal);

        // Gérer la soumission
        modal.querySelector('#quick-log-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitQuickLog(new FormData(e.target));
        });
    }

    /**
     * Soumettre un log rapide
     */
    async submitQuickLog(formData) {
        try {
            const response = await fetch('/api/ascents/quick-log', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                closeModal();
                showToast('Ascension enregistrée avec succès', 'success');
            } else {
                throw new Error('Erreur lors de l\'enregistrement');
            }
        } catch (error) {
            showToast('Erreur lors de l\'enregistrement', 'error');
        }
    }

    /**
     * Basculer le statut favori d'une voie
     */
    async toggleRouteFavorite(routeId) {
        try {
            const response = await fetch(`/api/routes/${routeId}/favorite`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                const btn = document.querySelector(`[data-route-id="${routeId}"][data-route-action="add-favorite"]`);

                if (btn) {
                    btn.innerHTML = data.is_favorite
                        ? '<i class="fas fa-heart"></i>'
                        : '<i class="far fa-heart"></i>';
                }

                showToast(data.is_favorite ? 'Ajouté aux favoris' : 'Retiré des favoris', 'success');
            }
        } catch (error) {
            showToast('Erreur lors de la mise à jour', 'error');
        }
    }

    /**
     * Initialiser la gestion des favoris
     */
    initializeFavorites() {
        const favoriteBtn = document.querySelector('.favorite-sector');
        if (!favoriteBtn) return;

        favoriteBtn.addEventListener('click', async () => {
            const sectorId = document.querySelector('[data-sector-id]')?.dataset.sectorId;
            if (!sectorId) return;

            try {
                const response = await fetch(`/api/sectors/${sectorId}/favorite`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    favoriteBtn.innerHTML = data.is_favorite
                        ? '<i class="fas fa-heart"></i> Retirer des favoris'
                        : '<i class="far fa-heart"></i> Ajouter aux favoris';

                    showToast(data.is_favorite ? 'Secteur ajouté aux favoris' : 'Secteur retiré des favoris', 'success');
                }
            } catch (error) {
                showToast('Erreur lors de la mise à jour', 'error');
            }
        });
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
}

// Initialiser la page au chargement
document.addEventListener('DOMContentLoaded', () => {
    new SectorShowPage();
});