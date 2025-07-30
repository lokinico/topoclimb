/**
 * JavaScript commun pour toutes les pages d'index (regions, sites, sectors, routes)
 * Gestion des filtres avancés, actions rapides, pagination, etc.
 */
class EntityPageManager {
    constructor(entityType, config = {}) {
        this.entityType = entityType;
        this.config = {
            enableFavorites: true,
            enableShare: true,
            enableWeather: false,
            enableGPS: true,
            autoSubmitDelay: 800,
            ...config
        };
        this.init();
    }
    init() {
        this.setupFilterEvents();
        // this.setupViewToggle(); // Désactivé - conflit avec ViewManager
        this.setupQuickActions();
        this.setupPagination();
        this.setupSearch();
        this.restoreFiltersState();
    }
    /**
     * Configuration des événements de filtres avancés
     */
    setupFilterEvents() {
        const form = document.getElementById('filters-form');
        if (!form) return;
        const inputs = form.querySelectorAll('input[type="text"], input[type="number"]');
        const selects = form.querySelectorAll('select');
        // Soumission automatique pour les champs texte avec délai
        inputs.forEach(input => {
            input.addEventListener('input', (e) => {
                clearTimeout(input.searchTimeout);
                input.searchTimeout = setTimeout(() => {
                    this.submitFilters();
                }, this.config.autoSubmitDelay);
            });
        });
        // Soumission immédiate pour les selects
        selects.forEach(select => {
            select.addEventListener('change', () => {
                this.submitFilters();
            });
        });
        // Reset des filtres
        const resetBtn = document.getElementById('reset-filters');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetFilters();
            });
        }
    }
    /**
     * Gestion du toggle entre vue grille et liste
     */
    setupViewToggle() {
        const gridViewBtn = document.getElementById('grid-view');
        const listViewBtn = document.getElementById('list-view');
        const container = document.querySelector('.entities-grid, .sites-grid, .sectors-grid, .routes-grid, .regions-grid');
        if (!gridViewBtn || !listViewBtn || !container) return;
        gridViewBtn.addEventListener('click', () => {
            this.setViewMode('grid', container, gridViewBtn, listViewBtn);
        });
        listViewBtn.addEventListener('click', () => {
            this.setViewMode('list', container, gridViewBtn, listViewBtn);
        });
    }
    setViewMode(mode, container, gridBtn, listBtn) {
        if (mode === 'grid') {
            container.classList.remove('list-view');
            container.classList.add('grid-view');
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        } else {
            container.classList.remove('grid-view');
            container.classList.add('list-view');
            listBtn.classList.add('active');
            gridBtn.classList.remove('active');
        }
        localStorage.setItem(`${this.entityType}_view_mode`, mode);
    }
    /**
     * Configuration des actions rapides sur les cartes
     */
    setupQuickActions() {
        // Favoris
        if (this.config.enableFavorites) {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.quick-favorite, .quick-action-btn[title*="favoris"]')) {
                    e.preventDefault();
                    const btn = e.target.closest('.quick-favorite, .quick-action-btn[title*="favoris"]');
                    const entityId = btn.dataset.siteId || btn.dataset.sectorId || btn.dataset.routeId || btn.dataset.regionId;
                    this.toggleFavorite(entityId, btn);
                }
            });
        }
        // Partage
        if (this.config.enableShare) {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.quick-share, .quick-action-btn[title*="Partager"]')) {
                    e.preventDefault();
                    const btn = e.target.closest('.quick-share, .quick-action-btn[title*="Partager"]');
                    const entityId = btn.dataset.siteId || btn.dataset.sectorId || btn.dataset.routeId || btn.dataset.regionId;
                    this.shareEntity(entityId, btn);
                }
            });
        }
        // Météo
        if (this.config.enableWeather) {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.quick-weather')) {
                    e.preventDefault();
                    const btn = e.target.closest('.quick-weather');
                    const entityId = btn.dataset.sectorId || btn.dataset.siteId;
                    this.showWeather(entityId, btn);
                }
            });
        }
    }
    /**
     * Configuration de la pagination améliorée
     */
    setupPagination() {
        // Amélioration des liens de pagination avec AJAX (optionnel)
        const paginationLinks = document.querySelectorAll('.pagination-nav .page-link');
        paginationLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // Optionnel: implémenter la pagination AJAX ici
                this.saveScrollPosition();
            });
        });
        // Restaurer la position de scroll
        this.restoreScrollPosition();
    }
    /**
     * Amélioration de la recherche
     */
    setupSearch() {
        const searchInput = document.getElementById('search');
        if (!searchInput) return;
        // Ajout d'un indicateur de recherche
        const searchContainer = searchInput.parentElement;
        const loadingIcon = document.createElement('i');
        loadingIcon.className = 'fas fa-spinner fa-spin search-loading';
        loadingIcon.style.cssText = 'position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: none; color: #007bff;';
        if (searchContainer.style.position !== 'relative') {
            searchContainer.style.position = 'relative';
        }
        searchContainer.appendChild(loadingIcon);
        // Afficher l'indicateur pendant la recherche
        searchInput.addEventListener('input', () => {
            loadingIcon.style.display = 'block';
            setTimeout(() => {
                loadingIcon.style.display = 'none';
            }, this.config.autoSubmitDelay + 100);
        });
    }
    /**
     * Soumission des filtres
     */
    submitFilters() {
        const form = document.getElementById('filters-form');
        if (form) {
            this.saveFiltersState();
            form.submit();
        }
    }
    /**
     * Reset des filtres
     */
    resetFilters() {
        const form = document.getElementById('filters-form');
        if (!form) return;
        // Réinitialiser tous les champs
        const inputs = form.querySelectorAll('input[type="text"], input[type="number"]');
        const selects = form.querySelectorAll('select');
        inputs.forEach(input => input.value = '');
        selects.forEach(select => select.selectedIndex = 0);
        // Fermer les filtres avancés
        const advancedFilters = document.getElementById('advanced-filters');
        const toggleButton = document.getElementById('toggle-advanced-filters');
        if (advancedFilters && toggleButton) {
            advancedFilters.classList.remove('show');
            toggleButton.classList.remove('expanded');
        }
        // Nettoyer le localStorage
        localStorage.removeItem(`${this.entityType}_filters`);
        // Rediriger vers la page sans paramètres
        const baseUrl = window.location.pathname;
        window.location.href = baseUrl;
    }
    /**
     * Gestion des favoris
     */
    async toggleFavorite(entityId, button) {
        if (!entityId) return;
        const icon = button.querySelector('i');
        const isFavorited = icon.classList.contains('fas');
        try {
            // Animation immédiate
            button.style.transform = 'scale(1.2)';
            setTimeout(() => {
                button.style.transform = '';
            }, 150);
            const response = await fetch(`/api/${this.entityType}/${entityId}/favorite`, {
                method: isFavorited ? 'DELETE' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (response.ok) {
                // Basculer l'icône
                if (isFavorited) {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    button.title = 'Ajouter aux favoris';
                } else {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    button.title = 'Retirer des favoris';
                }
                // Animation de succès
                this.showToast(`${isFavorited ? 'Retiré des' : 'Ajouté aux'} favoris`, 'success');
            } else {
                throw new Error('Erreur réseau');
            }
        } catch (error) {
            this.showToast('Erreur lors de la mise à jour des favoris', 'error');
        }
    }
    /**
     * Partage d'entité
     */
    async shareEntity(entityId, button) {
        if (!entityId) return;
        const url = `${window.location.origin}/${this.entityType}/${entityId}`;
        const title = button.closest('.entity-card').querySelector('.card-title a').textContent;
        if (navigator.share) {
            try {
                await navigator.share({
                    title: `${title} - TopoclimbCH`,
                    text: `Découvrez ${title} sur TopoclimbCH`,
                    url: url
                });
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.fallbackShare(url, title);
                }
            }
        } else {
            this.fallbackShare(url, title);
        }
    }
    /**
     * Partage de secours (copie dans le presse-papiers)
     */
    async fallbackShare(url, title) {
        try {
            await navigator.clipboard.writeText(url);
            this.showToast('Lien copié dans le presse-papiers', 'success');
        } catch (error) {
            // Fallback pour navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                this.showToast('Lien copié dans le presse-papiers', 'success');
            } catch (err) {
                this.showToast('Impossible de copier le lien', 'error');
            }
            document.body.removeChild(textArea);
        }
    }
    /**
     * Affichage de la météo
     */
    async showWeather(entityId, button) {
        if (!entityId) return;
        const icon = button.querySelector('i');
        const originalClass = icon.className;
        // Animation de chargement
        icon.className = 'fas fa-spinner fa-spin';
        try {
            const response = await fetch(`/api/weather/${this.entityType}/${entityId}`);
            const data = await response.json();
            if (data.success) {
                this.showWeatherModal(data.weather, button);
            } else {
                throw new Error(data.error || 'Erreur météo');
            }
        } catch (error) {
            this.showToast('Erreur lors du chargement de la météo', 'error');
        } finally {
            icon.className = originalClass;
        }
    }
    /**
     * Modal météo
     */
    showWeatherModal(weatherData, button) {
        const modal = document.createElement('div');
        modal.className = 'weather-modal-overlay';
        modal.innerHTML = `
            <div class="weather-modal">
                <div class="weather-modal-header">
                    <h5>Conditions météo</h5>
                    <button class="weather-modal-close">&times;</button>
                </div>
                <div class="weather-modal-body">
                    <div class="weather-current">
                        <div class="weather-temp">${weatherData.temperature}°C</div>
                        <div class="weather-desc">${weatherData.description}</div>
                        <div class="weather-details">
                            <span>Vent: ${weatherData.wind_speed} km/h</span>
                            <span>Humidité: ${weatherData.humidity}%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        // Fermeture
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('weather-modal-close')) {
                document.body.removeChild(modal);
            }
        });
        // Auto-fermeture après 5 secondes
        setTimeout(() => {
            if (document.body.contains(modal)) {
                document.body.removeChild(modal);
            }
        }, 5000);
    }
    /**
     * Sauvegarde de l'état des filtres
     */
    saveFiltersState() {
        const form = document.getElementById('filters-form');
        if (!form) return;
        const formData = new FormData(form);
        const filters = Object.fromEntries(formData.entries());
        localStorage.setItem(`${this.entityType}_filters`, JSON.stringify(filters));
    }
    /**
     * Restauration de l'état des filtres
     */
    restoreFiltersState() {
        const savedFilters = localStorage.getItem(`${this.entityType}_filters`);
        if (!savedFilters) return;
        try {
            const filters = JSON.parse(savedFilters);
            const form = document.getElementById('filters-form');
            if (!form) return;
            // Ne restaurer que si la page n'a pas déjà des paramètres d'URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.toString()) return;
            Object.entries(filters).forEach(([key, value]) => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input && value) {
                    input.value = value;
                }
            });
        } catch (error) {
        }
    }
    /**
     * Sauvegarde de la position de scroll
     */
    saveScrollPosition() {
        sessionStorage.setItem(`${this.entityType}_scroll`, window.pageYOffset);
    }
    /**
     * Restauration de la position de scroll
     */
    restoreScrollPosition() {
        const savedPosition = sessionStorage.getItem(`${this.entityType}_scroll`);
        if (savedPosition) {
            window.scrollTo(0, parseInt(savedPosition));
            sessionStorage.removeItem(`${this.entityType}_scroll`);
        }
    }
    /**
     * Affichage de notifications toast
     */
    showToast(message, type = 'info') {
        // Supprimer les toasts existants
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => toast.remove());
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
                <span>${message}</span>
            </div>
        `;
        // Styles inline pour s'assurer qu'ils s'appliquent
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            font-weight: 500;
            min-width: 300px;
        `;
        document.body.appendChild(toast);
        // Animation d'entrée
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        // Auto-suppression
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
}
// Initialisation automatique selon la page
document.addEventListener('DOMContentLoaded', function() {
    const bodyClass = document.body.className;
    let entityType = null;
    let config = {};
    if (bodyClass.includes('regions-index')) {
        entityType = 'regions';
        config = { enableWeather: false, enableGPS: false };
    } else if (bodyClass.includes('sites-index')) {
        entityType = 'sites';
        config = { enableWeather: true, enableGPS: true };
    } else if (bodyClass.includes('sectors-index')) {
        entityType = 'sectors';
        config = { enableWeather: true, enableGPS: true };
    } else if (bodyClass.includes('routes-index')) {
        entityType = 'routes';
        config = { enableWeather: false, enableGPS: false };
    }
    if (entityType) {
        window.entityPageManager = new EntityPageManager(entityType, config);
    }
});
// Export pour usage externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EntityPageManager;
}