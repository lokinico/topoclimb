/**
 * Sélecteur hiérarchique TopoclimbCH
 * Gestion de la hiérarchie Région > Site > Secteur > Voie
 */

class HierarchySelector {
    constructor() {
        this.config = window.HierarchySelector || {};
        this.selection = {
            region: null,
            site: null,
            sector: null,
            routes: []
        };
        this.selectedItems = new Set();
        this.searchTimeouts = {};

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadRegions();
        this.parsePreselected();
    }

    bindEvents() {
        // Recherche globale
        const globalSearch = document.getElementById('global-search');
        if (globalSearch) {
            globalSearch.addEventListener('input', this.debounce((e) => {
                this.globalSearch(e.target.value);
            }, 300));
        }

        // Recherche par niveau
        document.querySelectorAll('.level-search input').forEach(input => {
            input.addEventListener('input', this.debounce((e) => {
                const level = e.target.closest('.level-container').dataset.level;
                this.searchInLevel(level, e.target.value);
            }, 300));
        });

        // Actions des boutons
        this.bindActionButtons();
    }

    bindActionButtons() {
        const confirmBtn = document.getElementById('confirm-selection');
        const clearBtn = document.getElementById('clear-selection');
        const saveBookBtn = document.getElementById('save-book-selection');

        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.confirmSelection());
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearSelection());
        }

        if (saveBookBtn) {
            saveBookBtn.addEventListener('click', () => this.saveBookSelection());
        }
    }

    // API calls
    async loadRegions(search = '') {
        try {
            this.showLoading('regions');
            const response = await this.apiCall('regions', null, search);
            this.renderItems('regions', response.data);
            this.enableLevel('regions');
        } catch (error) {
            this.showError('regions', 'Erreur lors du chargement des régions');
        }
    }

    async loadSites(regionId, search = '') {
        try {
            this.showLoading('sites');
            const response = await this.apiCall('sites', regionId, search);
            this.renderItems('sites', response.data);
            this.enableLevel('sites');
        } catch (error) {
            this.showError('sites', 'Erreur lors du chargement des sites');
        }
    }

    async loadSectors(parentId, search = '') {
        try {
            this.showLoading('sectors');
            const response = await this.apiCall('sectors', parentId, search);
            this.renderItems('sectors', response.data);
            this.enableLevel('sectors');
        } catch (error) {
            this.showError('sectors', 'Erreur lors du chargement des secteurs');
        }
    }

    async loadRoutes(sectorId, search = '') {
        try {
            this.showLoading('routes');
            const response = await this.apiCall('routes', sectorId, search);
            this.renderItems('routes', response.data);
            this.enableLevel('routes');
        } catch (error) {
            this.showError('routes', 'Erreur lors du chargement des voies');
        }
    }

    async apiCall(level, parentId = null, search = '') {
        const url = new URL(this.config.apiBaseUrl, window.location.origin);
        url.searchParams.set('level', level);
        if (parentId) url.searchParams.set('parent_id', parentId);
        if (search) url.searchParams.set('search', search);

        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        return await response.json();
    }

    // Rendu des éléments
    renderItems(level, items) {
        const container = document.getElementById(`${level}-list`);
        if (!container) return;

        if (items.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Aucun résultat trouvé</p>
                </div>
            `;
            return;
        }

        const template = document.getElementById(`${level.slice(0, -1)}-item-template`);
        if (!template) {
            console.error(`Template not found for ${level}`);
            return;
        }

        container.innerHTML = items.map(item => {
            return this.renderTemplate(template.innerHTML, item);
        }).join('');

        // Bind click events
        container.querySelectorAll('.hierarchy-item').forEach(item => {
            item.addEventListener('click', (e) => {
                this.selectItem(level, item, items.find(i => i.id == item.dataset.id));
            });
        });
    }

    renderTemplate(template, data) {
        return template.replace(/\{(\w+)\}/g, (match, key) => {
            if (key in data) {
                return data[key] || '';
            }
            return match;
        });
    }

    // Sélection d'éléments
    selectItem(level, element, data) {
        // Désélectionner les éléments du même niveau
        element.parentElement.querySelectorAll('.hierarchy-item').forEach(item => {
            item.classList.remove('selected');
        });

        // Sélectionner l'élément cliqué
        element.classList.add('selected');

        // Mettre à jour la sélection
        this.updateSelection(level, data);

        // Charger le niveau suivant
        this.loadNextLevel(level, data);

        // Mettre à jour l'affichage
        this.updateBreadcrumb();
        this.updateResults();
    }

    updateSelection(level, data) {
        switch (level) {
            case 'regions':
                this.selection.region = data;
                this.selection.site = null;
                this.selection.sector = null;
                this.selection.routes = [];
                this.clearLevel('sites');
                this.clearLevel('sectors');
                this.clearLevel('routes');
                break;

            case 'sites':
                this.selection.site = data;
                this.selection.sector = null;
                this.selection.routes = [];
                this.clearLevel('sectors');
                this.clearLevel('routes');
                break;

            case 'sectors':
                this.selection.sector = data;
                this.selection.routes = [];
                this.clearLevel('routes');
                break;

            case 'routes':
                // Gestion multi-sélection pour les voies
                const routeId = data.id;
                const index = this.selection.routes.findIndex(r => r.id === routeId);

                if (index === -1) {
                    this.selection.routes.push(data);
                    element.classList.add('selected');
                } else {
                    this.selection.routes.splice(index, 1);
                    element.classList.remove('selected');
                }
                break;
        }
    }

    loadNextLevel(currentLevel, data) {
        switch (currentLevel) {
            case 'regions':
                this.loadSites(data.id);
                // Charger aussi les secteurs directement attachés à la région
                this.loadSectors(data.id);
                break;

            case 'sites':
                this.loadSectors(data.id);
                break;

            case 'sectors':
                this.loadRoutes(data.id);
                break;
        }
    }

    // Gestion des niveaux
    enableLevel(level) {
        const container = document.querySelector(`[data-level="${level}"]`);
        if (container) {
            container.classList.remove('disabled');
            container.classList.add('active');
        }
    }

    disableLevel(level) {
        const container = document.querySelector(`[data-level="${level}"]`);
        if (container) {
            container.classList.add('disabled');
            container.classList.remove('active');
        }
    }

    clearLevel(level) {
        const container = document.getElementById(`${level}-list`);
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-arrow-left"></i>
                    <p>Sélectionnez d'abord un élément précédent</p>
                </div>
            `;
        }
        this.disableLevel(level);
    }

    showLoading(level) {
        const container = document.getElementById(`${level}-list`);
        if (container) {
            container.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Chargement...
                </div>
            `;
        }
    }

    showError(level, message) {
        const container = document.getElementById(`${level}-list`);
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    <p class="text-danger">${message}</p>
                </div>
            `;
        }
    }

    // Breadcrumb
    updateBreadcrumb() {
        const breadcrumb = document.getElementById('selection-breadcrumb');
        if (!breadcrumb) return;

        const items = [];

        if (this.selection.region) {
            items.push(`<li class="breadcrumb-item">${this.selection.region.name}</li>`);
        }

        if (this.selection.site) {
            items.push(`<li class="breadcrumb-item">${this.selection.site.name}</li>`);
        }

        if (this.selection.sector) {
            items.push(`<li class="breadcrumb-item">${this.selection.sector.name}</li>`);
        }

        if (this.selection.routes.length > 0) {
            if (this.selection.routes.length === 1) {
                items.push(`<li class="breadcrumb-item active">${this.selection.routes[0].name}</li>`);
            } else {
                items.push(`<li class="breadcrumb-item active">${this.selection.routes.length} voies sélectionnées</li>`);
            }
        }

        if (items.length === 0) {
            breadcrumb.innerHTML = '<li class="breadcrumb-item text-muted">Aucune sélection</li>';
        } else {
            breadcrumb.innerHTML = items.join('');
        }
    }

    // Recherche
    searchInLevel(level, query) {
        if (this.searchTimeouts[level]) {
            clearTimeout(this.searchTimeouts[level]);
        }

        this.searchTimeouts[level] = setTimeout(() => {
            switch (level) {
                case 'regions':
                    this.loadRegions(query);
                    break;
                case 'sites':
                    if (this.selection.region) {
                        this.loadSites(this.selection.region.id, query);
                    }
                    break;
                case 'sectors':
                    const parentId = this.selection.site?.id || this.selection.region?.id;
                    if (parentId) {
                        this.loadSectors(parentId, query);
                    }
                    break;
                case 'routes':
                    if (this.selection.sector) {
                        this.loadRoutes(this.selection.sector.id, query);
                    }
                    break;
            }
        }, 300);
    }

    globalSearch(query) {
        if (!query.trim()) {
            this.loadRegions();
            return;
        }

        // Recherche dans tous les niveaux
        this.searchInLevel('regions', query);
        if (this.selection.region) {
            this.searchInLevel('sites', query);
            this.searchInLevel('sectors', query);
        }
        if (this.selection.sector) {
            this.searchInLevel('routes', query);
        }
    }

    // Résultats selon le mode
    updateResults() {
        const resultsContainer = document.getElementById('selection-results');

        if (this.hasSelection()) {
            resultsContainer.style.display = 'block';

            switch (this.config.mode) {
                case 'book':
                    this.updateBookResults();
                    break;
                case 'stats':
                    this.updateStatsResults();
                    break;
                case 'select':
                    this.updateSelectResults();
                    break;
            }
        } else {
            resultsContainer.style.display = 'none';
        }
    }

    updateBookResults() {
        const sectorsContainer = document.getElementById('selected-sectors');
        const routesContainer = document.getElementById('selected-routes');

        if (sectorsContainer && this.selection.sector) {
            sectorsContainer.innerHTML = `
                <div class="selected-item">
                    <strong>${this.selection.sector.name}</strong>
                    <span class="badge badge-secondary">${this.selection.sector.route_count} voies</span>
                </div>
            `;
        }

        if (routesContainer) {
            routesContainer.innerHTML = this.selection.routes.map(route => `
                <div class="selected-item">
                    <span class="route-number">${route.number}</span>
                    <span class="route-name">${route.name}</span>
                    <span class="route-difficulty">${route.difficulty}</span>
                </div>
            `).join('');
        }
    }

    updateStatsResults() {
        const statsContainer = document.getElementById('stats-display');
        if (!statsContainer) return;

        const stats = this.calculateStats();

        statsContainer.innerHTML = `
            <div class="stat-card">
                <h5>Régions</h5>
                <div class="stat-value">${stats.regions}</div>
            </div>
            <div class="stat-card">
                <h5>Sites</h5>
                <div class="stat-value">${stats.sites}</div>
            </div>
            <div class="stat-card">
                <h5>Secteurs</h5>
                <div class="stat-value">${stats.sectors}</div>
            </div>
            <div class="stat-card">
                <h5>Voies</h5>
                <div class="stat-value">${stats.routes}</div>
            </div>
        `;
    }

    updateSelectResults() {
        const itemsContainer = document.getElementById('selected-items');
        if (!itemsContainer) return;

        const items = [];

        if (this.selection.region) {
            items.push(`<div class="selected-item">Région: <strong>${this.selection.region.name}</strong></div>`);
        }
        if (this.selection.site) {
            items.push(`<div class="selected-item">Site: <strong>${this.selection.site.name}</strong></div>`);
        }
        if (this.selection.sector) {
            items.push(`<div class="selected-item">Secteur: <strong>${this.selection.sector.name}</strong></div>`);
        }
        if (this.selection.routes.length > 0) {
            items.push(`<div class="selected-item">Voies: <strong>${this.selection.routes.length} sélectionnées</strong></div>`);
        }

        itemsContainer.innerHTML = items.join('');
    }

    // Actions
    confirmSelection() {
        const data = {
            selection: this.selection,
            mode: this.config.mode
        };

        // Envoyer les données au parent ou faire un redirect
        if (window.opener) {
            window.opener.postMessage({
                type: 'hierarchy-selection',
                data: data
            }, window.location.origin);
            window.close();
        } else {
            console.log('Sélection confirmée:', data);
            // Ici vous pourriez rediriger ou déclencher un event
        }
    }

    clearSelection() {
        this.selection = {
            region: null,
            site: null,
            sector: null,
            routes: []
        };

        // Désélectionner visuellement
        document.querySelectorAll('.hierarchy-item.selected').forEach(item => {
            item.classList.remove('selected');
        });

        // Réinitialiser les niveaux
        this.clearLevel('sites');
        this.clearLevel('sectors');
        this.clearLevel('routes');

        this.updateBreadcrumb();
        this.updateResults();
    }

    saveBookSelection() {
        const data = {
            book_id: this.config.bookId,
            sectors: this.selection.sector ? [this.selection.sector.id] : [],
            routes: this.selection.routes.map(r => r.id),
            csrf_token: this.config.csrfToken
        };

        fetch('/books/save-selection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Sélection sauvegardée avec succès');
                } else {
                    alert('Erreur lors de la sauvegarde');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la sauvegarde');
            });
    }

    // Utilitaires
    hasSelection() {
        return this.selection.region || this.selection.site ||
            this.selection.sector || this.selection.routes.length > 0;
    }

    calculateStats() {
        return {
            regions: this.selection.region ? 1 : 0,
            sites: this.selection.site ? 1 : 0,
            sectors: this.selection.sector ? 1 : 0,
            routes: this.selection.routes.length
        };
    }

    parsePreselected() {
        if (!this.config.preselected) return;

        // Format: "region:1,site:2,sector:3"
        const parts = this.config.preselected.split(',');

        parts.forEach(part => {
            const [type, id] = part.split(':');
            // Implémenter la présélection si nécessaire
        });
    }

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

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    new HierarchySelector();
});