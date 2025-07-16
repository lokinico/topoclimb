/**
 * Sites Index - JavaScript pour la liste des sites
 */

class SitesIndex {
    constructor() {
        this.searchTimeout = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.enhanceSearchForm();
        this.addKeyboardShortcuts();
    }

    bindEvents() {
        // Recherche en temps réel
        const searchInput = document.querySelector('#search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearchInput(e.target.value);
            });

            // Focus automatique sur la recherche
            searchInput.addEventListener('focus', () => {
                searchInput.select();
            });
        }

        // Amélioration des cartes de sites
        this.enhanceSiteCards();

        // Navigation au clavier
        this.setupKeyboardNavigation();
    }

    /**
     * Gestion de la recherche avec debouncing
     */
    handleSearchInput(query) {
        // Annuler la recherche précédente
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        // Attendre 300ms avant de chercher
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);

        // Feedback visuel immédiat
        this.showSearchFeedback(query);
    }

    /**
     * Effectuer la recherche
     */
    performSearch(query) {
        if (query.length < 2 && query.length > 0) {
            this.showSearchMessage('Saisissez au moins 2 caractères');
            return;
        }

        if (query.length === 0) {
            // Retourner à la liste complète
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('search');

            const newUrl = window.location.pathname +
                (urlParams.toString() ? '?' + urlParams.toString() : '');

            window.history.replaceState({}, '', newUrl);
            location.reload();
            return;
        }

        // Mettre à jour l'URL sans recharger
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('search', query);

        const newUrl = window.location.pathname + '?' + urlParams.toString();
        window.history.replaceState({}, '', newUrl);

        // Simuler une recherche AJAX (à implémenter côté serveur)
        this.searchSitesAjax(query);
    }

    /**
     * Recherche AJAX des sites
     */
    async searchSitesAjax(query) {
        try {
            this.showSearchLoading();

            const urlParams = new URLSearchParams(window.location.search);
            const regionId = urlParams.get('region_id');

            const response = await fetch(`/api/sites/search?region_id=${regionId}&q=${encodeURIComponent(query)}`);

            if (!response.ok) {
                throw new Error('Erreur de recherche');
            }

            const data = await response.json();
            this.updateSitesList(data.sites);
            this.updateSearchInfo(query, data.sites.length);

        } catch (error) {
            console.error('Erreur de recherche:', error);
            this.showSearchError();
        }
    }

    /**
     * Mettre à jour la liste des sites
     */
    updateSitesList(sites) {
        const grid = document.querySelector('.sites-grid');
        if (!grid) return;

        if (sites.length === 0) {
            grid.innerHTML = this.getEmptySearchTemplate();
            return;
        }

        grid.innerHTML = sites.map(site => this.getSiteCardTemplate(site)).join('');
        this.enhanceSiteCards();
    }

    /**
     * Template pour une carte de site
     */
    getSiteCardTemplate(site) {
        return `
            <div class="site-card" data-site-id="${site.id}">
                <div class="site-header">
                    <h3 class="site-name">
                        <a href="/sites/${site.id}">${site.name}</a>
                    </h3>
                    <div class="site-code">${site.code.toUpperCase()}</div>
                </div>
                
                ${site.description ? `
                    <p class="site-description">
                        ${this.truncateText(site.description, 120)}
                    </p>
                ` : ''}
                
                <div class="site-stats">
                    <div class="stat-item">
                        <i class="fas fa-climbing"></i>
                        <span class="stat-value">${site.sector_count}</span>
                        <span class="stat-label">secteur${site.sector_count > 1 ? 's' : ''}</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-route"></i>
                        <span class="stat-value">${site.route_count}</span>
                        <span class="stat-label">voie${site.route_count > 1 ? 's' : ''}</span>
                    </div>
                    ${site.avg_beauty ? `
                        <div class="stat-item">
                            <i class="fas fa-star"></i>
                            <span class="stat-value">${parseFloat(site.avg_beauty).toFixed(1)}</span>
                            <span class="stat-label">beauté moy.</span>
                        </div>
                    ` : ''}
                </div>
                
                ${site.min_difficulty && site.max_difficulty ? `
                    <div class="site-difficulty">
                        <span class="difficulty-range">
                            ${site.min_difficulty} - ${site.max_difficulty}
                        </span>
                    </div>
                ` : ''}
                
                <div class="site-meta">
                    ${site.year ? `
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i> ${site.year}
                        </span>
                    ` : ''}
                    ${site.publisher ? `
                        <span class="meta-item">
                            <i class="fas fa-book"></i> ${site.publisher}
                        </span>
                    ` : ''}
                </div>
                
                <div class="site-actions">
                    <a href="/sites/${site.id}" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> Voir détail
                    </a>
                    <a href="/sites/${site.id}/edit" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
            </div>
        `;
    }

    /**
     * Template pour résultat vide
     */
    getEmptySearchTemplate() {
        const query = document.querySelector('#search').value;
        return `
            <div class="col-12">
                <div class="empty-results">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Aucun site trouvé</h3>
                    <p>Aucun site ne correspond à votre recherche "${query}"</p>
                    <button class="btn btn-primary" onclick="document.querySelector('#search').value = ''; window.location.reload();">
                        Voir tous les sites
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Améliorer les cartes de sites existantes
     */
    enhanceSiteCards() {
        const cards = document.querySelectorAll('.site-card');

        cards.forEach((card, index) => {
            // Animation d'apparition échelonnée
            card.style.animationDelay = `${index * 0.1}s`;

            // Amélioration hover
            this.enhanceCardHover(card);

            // Actions rapides au clavier
            this.addCardKeyboardActions(card);
        });
    }

    /**
     * Améliorer les effets hover des cartes
     */
    enhanceCardHover(card) {
        const actions = card.querySelector('.site-actions');

        card.addEventListener('mouseenter', () => {
            actions.style.transform = 'translateY(0)';
            actions.style.opacity = '1';
        });

        card.addEventListener('mouseleave', () => {
            actions.style.transform = 'translateY(10px)';
            actions.style.opacity = '0.8';
        });

        // Clic sur la carte = navigation vers le détail
        card.addEventListener('click', (e) => {
            if (e.target.closest('.btn')) return; // Ignorer les clics sur les boutons

            const link = card.querySelector('.site-name a');
            if (link) {
                if (e.ctrlKey || e.metaKey) {
                    window.open(link.href, '_blank');
                } else {
                    window.location.href = link.href;
                }
            }
        });

        // Indication visuelle que la carte est cliquable
        card.style.cursor = 'pointer';
    }

    /**
     * Actions clavier pour les cartes
     */
    addCardKeyboardActions(card) {
        card.setAttribute('tabindex', '0');

        card.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    const link = card.querySelector('.site-name a');
                    if (link) link.click();
                    break;

                case 'e':
                    e.preventDefault();
                    const editLink = card.querySelector('a[href*="/edit"]');
                    if (editLink) editLink.click();
                    break;
            }
        });
    }

    /**
     * Navigation au clavier
     */
    setupKeyboardNavigation() {
        let currentCardIndex = -1;
        const cards = () => document.querySelectorAll('.site-card');

        document.addEventListener('keydown', (e) => {
            // Navigation uniquement si pas de focus sur input
            if (document.activeElement.tagName === 'INPUT') return;

            switch (e.key) {
                case 'ArrowDown':
                case 'j':
                    e.preventDefault();
                    currentCardIndex = Math.min(currentCardIndex + 1, cards().length - 1);
                    this.focusCard(currentCardIndex, cards());
                    break;

                case 'ArrowUp':
                case 'k':
                    e.preventDefault();
                    currentCardIndex = Math.max(currentCardIndex - 1, 0);
                    this.focusCard(currentCardIndex, cards());
                    break;

                case '/':
                    e.preventDefault();
                    document.querySelector('#search')?.focus();
                    break;

                case 'Escape':
                    document.querySelector('#search').blur();
                    cards().forEach(card => card.blur());
                    currentCardIndex = -1;
                    break;
            }
        });
    }

    /**
     * Donner le focus à une carte
     */
    focusCard(index, cards) {
        cards.forEach(card => card.classList.remove('keyboard-focus'));

        if (cards[index]) {
            cards[index].focus();
            cards[index].classList.add('keyboard-focus');
            cards[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * Améliorer le formulaire de recherche
     */
    enhanceSearchForm() {
        const form = document.querySelector('.search-form');
        const input = document.querySelector('#search');

        if (!form || !input) return;

        // Prévenir la soumission si moins de 2 caractères
        form.addEventListener('submit', (e) => {
            if (input.value.trim().length > 0 && input.value.trim().length < 2) {
                e.preventDefault();
                this.showSearchMessage('Saisissez au moins 2 caractères');
                input.focus();
            }
        });

        // Améliorer visuellement l'input pendant la frappe
        input.addEventListener('input', () => {
            const value = input.value.trim();

            if (value.length === 0) {
                input.classList.remove('has-content', 'searching');
            } else if (value.length < 2) {
                input.classList.add('has-content');
                input.classList.remove('searching');
            } else {
                input.classList.add('has-content', 'searching');
            }
        });
    }

    /**
     * Feedback visuel de recherche
     */
    showSearchFeedback(query) {
        const input = document.querySelector('#search');

        if (query.length >= 2) {
            input.classList.add('searching');
        } else {
            input.classList.remove('searching');
        }
    }

    /**
     * Afficher le chargement de recherche
     */
    showSearchLoading() {
        const grid = document.querySelector('.sites-grid');
        if (grid) {
            grid.innerHTML = `
                <div class="col-12 text-center">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-3">Recherche en cours...</p>
                    </div>
                </div>
            `;
        }
    }

    /**
     * Afficher une erreur de recherche
     */
    showSearchError() {
        const grid = document.querySelector('.sites-grid');
        if (grid) {
            grid.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Erreur de recherche</strong><br>
                        Veuillez réessayer dans quelques instants.
                    </div>
                </div>
            `;
        }
    }

    /**
     * Afficher un message de recherche
     */
    showSearchMessage(message) {
        const input = document.querySelector('#search');

        // Créer ou mettre à jour le message
        let messageEl = document.querySelector('.search-message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'search-message text-warning mt-2';
            input.parentNode.appendChild(messageEl);
        }

        messageEl.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;

        // Masquer après 3 secondes
        setTimeout(() => {
            if (messageEl) messageEl.remove();
        }, 3000);
    }

    /**
     * Mettre à jour les infos de recherche
     */
    updateSearchInfo(query, count) {
        let searchInfo = document.querySelector('.search-info');

        if (!searchInfo) {
            searchInfo = document.createElement('div');
            searchInfo.className = 'search-info';
            document.querySelector('.sites-results').prepend(searchInfo);
        }

        searchInfo.innerHTML = `
            <p class="text-muted">
                <i class="fas fa-search"></i>
                ${count} résultat${count > 1 ? 's' : ''} pour "${query}"
            </p>
        `;
    }

    /**
     * Raccourcis clavier globaux
     */
    addKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Raccourcis uniquement si pas de focus sur input
            if (document.activeElement.tagName === 'INPUT') return;

            switch (e.key) {
                case 'n':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        const createBtn = document.querySelector('a[href*="/create"]');
                        if (createBtn) createBtn.click();
                    }
                    break;

                case 'h':
                    e.preventDefault();
                    const hierarchyBtn = document.querySelector('a[href*="/selector"]');
                    if (hierarchyBtn) hierarchyBtn.click();
                    break;
            }
        });
    }

    /**
     * Utilitaire pour tronquer le texte
     */
    truncateText(text, length) {
        if (text.length <= length) return text;
        return text.substring(0, length).trim() + '...';
    }
}

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    new SitesIndex();
});

// CSS additionnel pour les améliorations JavaScript
const additionalCSS = `
    .site-card {
        transition: all 0.3s ease, transform 0.2s ease;
    }
    
    .site-card.keyboard-focus {
        outline: 3px solid var(--primary-color, #2c3e50);
        outline-offset: 2px;
    }
    
    .site-actions {
        transition: all 0.3s ease;
    }
    
    .form-control.searching {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='%23007bff' d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'%3e%3c/path%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
        padding-right: 40px;
    }
    
    .loading-spinner {
        padding: 40px;
        color: var(--text-muted, #6c757d);
    }
    
    .search-message {
        font-size: 12px;
        padding: 8px 12px;
        border-radius: 4px;
        background: var(--warning-light, #fff3cd);
        border: 1px solid var(--warning-color, #ffc107);
    }
`;

// Injecter le CSS additionnel
if (!document.querySelector('#sites-index-enhancement-css')) {
    const style = document.createElement('style');
    style.id = 'sites-index-enhancement-css';
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}