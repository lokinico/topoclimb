/**
 * Site Show - JavaScript pour la page de détail d'un site
 */

class SiteShow {
    constructor() {
        this.siteId = this.getSiteIdFromUrl();
        this.init();
    }

    init() {
        this.enhanceStatCards();
        this.enhanceSectorCards();
        this.setupQuickActions();
        this.setupKeyboardShortcuts();
        this.setupActionLinks();
        this.enablePrintMode();
    }

    /**
     * Extraire l'ID du site depuis l'URL
     */
    getSiteIdFromUrl() {
        const path = window.location.pathname;
        const matches = path.match(/\/sites\/(\d+)/);
        return matches ? parseInt(matches[1]) : null;
    }

    /**
     * Améliorer les cartes de statistiques
     */
    enhanceStatCards() {
        const statCards = document.querySelectorAll('.stat-card');

        statCards.forEach((card, index) => {
            // Animation d'apparition échelonnée
            card.style.animationDelay = `${index * 0.1}s`;

            // Effet hover amélioré
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-4px) scale(1.02)';
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });

            // Animation du nombre
            this.animateStatNumber(card);
        });
    }

    /**
     * Animer les nombres dans les cartes de stats
     */
    animateStatNumber(card) {
        const numberEl = card.querySelector('.stat-number');
        if (!numberEl) return;

        const finalNumber = parseInt(numberEl.textContent) || 0;
        const duration = 1000; // 1 seconde
        const increment = finalNumber / (duration / 16); // 60 FPS
        let current = 0;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalNumber) {
                            numberEl.textContent = finalNumber;
                            clearInterval(timer);
                        } else {
                            numberEl.textContent = Math.floor(current);
                        }
                    }, 16);
                    observer.unobserve(entry.target);
                }
            });
        });

        observer.observe(card);
    }

    /**
     * Améliorer les cartes de secteurs
     */
    enhanceSectorCards() {
        const sectorCards = document.querySelectorAll('.sector-card');

        sectorCards.forEach((card, index) => {
            // Animation d'apparition échelonnée
            card.style.animationDelay = `${index * 0.1}s`;

            // Améliorer l'interactivité
            this.enhanceSectorCard(card);

            // Actions rapides
            this.addSectorQuickActions(card);
        });

        // Filtrage des secteurs
        this.setupSectorFiltering();
    }

    /**
     * Améliorer une carte de secteur individuelle
     */
    enhanceSectorCard(card) {
        // Effet hover
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateX(5px)';
            card.style.borderLeftWidth = '6px';
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateX(0)';
            card.style.borderLeftWidth = '4px';
        });

        // Clic sur la carte = navigation vers le secteur
        card.addEventListener('click', (e) => {
            if (e.target.closest('.btn')) return; // Ignorer les clics sur les boutons

            const link = card.querySelector('.sector-header a');
            if (link) {
                if (e.ctrlKey || e.metaKey) {
                    window.open(link.href, '_blank');
                } else {
                    window.location.href = link.href;
                }
            }
        });

        card.style.cursor = 'pointer';

        // Navigation clavier
        card.setAttribute('tabindex', '0');
        card.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    card.click();
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
     * Ajouter des actions rapides aux secteurs
     */
    addSectorQuickActions(card) {
        const actions = card.querySelector('.sector-actions');
        if (!actions) return;

        // Créer un menu contextuel
        const contextMenu = document.createElement('div');
        contextMenu.className = 'sector-context-menu';
        contextMenu.innerHTML = `
            <div class="context-item" data-action="view">
                <i class="fas fa-eye"></i> Voir détail
            </div>
            <div class="context-item" data-action="edit">
                <i class="fas fa-edit"></i> Modifier
            </div>
            <div class="context-item" data-action="routes">
                <i class="fas fa-route"></i> Voir les voies
            </div>
        `;

        // Menu contextuel au clic droit
        card.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.showContextMenu(contextMenu, e.pageX, e.pageY, card);
        });
    }

    /**
     * Afficher le menu contextuel
     */
    showContextMenu(menu, x, y, card) {
        // Supprimer les menus existants
        document.querySelectorAll('.sector-context-menu').forEach(m => m.remove());

        // Positionner le menu
        menu.style.position = 'fixed';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.style.zIndex = '1000';

        document.body.appendChild(menu);

        // Actions du menu
        menu.addEventListener('click', (e) => {
            const action = e.target.closest('.context-item')?.dataset.action;
            if (action) {
                this.executeContextAction(action, card);
            }
            menu.remove();
        });

        // Fermer au clic externe
        setTimeout(() => {
            document.addEventListener('click', () => menu.remove(), { once: true });
        }, 10);
    }

    /**
     * Exécuter une action contextuelle
     */
    executeContextAction(action, card) {
        switch (action) {
            case 'view':
                const viewLink = card.querySelector('.sector-header a');
                if (viewLink) viewLink.click();
                break;

            case 'edit':
                const editLink = card.querySelector('a[href*="/edit"]');
                if (editLink) editLink.click();
                break;

            case 'routes':
                const sectorLink = card.querySelector('.sector-header a');
                if (sectorLink) {
                    window.open(sectorLink.href + '#routes', '_blank');
                }
                break;
        }
    }

    /**
     * Configuration du filtrage des secteurs
     */
    setupSectorFiltering() {
        // Ajouter une barre de filtrage simple
        const sectorsSection = document.querySelector('.sectors-list')?.parentElement;
        if (!sectorsSection) return;

        const filterBar = document.createElement('div');
        filterBar.className = 'sector-filter-bar mb-3';
        filterBar.innerHTML = `
            <div class="row align-items-center">
                <div class="col-md-6">
                    <input type="text" 
                           class="form-control form-control-sm" 
                           placeholder="Filtrer les secteurs..." 
                           id="sector-filter">
                </div>
                <div class="col-md-6">
                    <div class="sector-sort-controls">
                        <select class="form-select form-select-sm" id="sector-sort">
                            <option value="name">Nom</option>
                            <option value="routes">Nombre de voies</option>
                            <option value="beauty">Beauté moyenne</option>
                        </select>
                    </div>
                </div>
            </div>
        `;

        sectorsSection.querySelector('h3').after(filterBar);

        // Événements de filtrage
        document.getElementById('sector-filter').addEventListener('input', (e) => {
            this.filterSectors(e.target.value);
        });

        document.getElementById('sector-sort').addEventListener('change', (e) => {
            this.sortSectors(e.target.value);
        });
    }

    /**
     * Filtrer les secteurs
     */
    filterSectors(query) {
        const cards = document.querySelectorAll('.sector-card');
        const lowerQuery = query.toLowerCase();

        cards.forEach(card => {
            const name = card.querySelector('.sector-header h4').textContent.toLowerCase();
            const description = card.querySelector('.sector-description')?.textContent.toLowerCase() || '';

            if (name.includes(lowerQuery) || description.includes(lowerQuery)) {
                card.style.display = 'block';
                card.style.animation = 'fadeIn 0.3s ease';
            } else {
                card.style.display = 'none';
            }
        });

        // Afficher un message si aucun résultat
        const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
        this.toggleEmptyFilterMessage(visibleCards.length === 0 && query.trim() !== '');
    }

    /**
     * Trier les secteurs
     */
    sortSectors(criteria) {
        const container = document.querySelector('.sectors-list');
        if (!container) return;

        const cards = Array.from(container.querySelectorAll('.sector-card'));

        cards.sort((a, b) => {
            switch (criteria) {
                case 'name':
                    const nameA = a.querySelector('.sector-header h4').textContent;
                    const nameB = b.querySelector('.sector-header h4').textContent;
                    return nameA.localeCompare(nameB);

                case 'routes':
                    const routesA = parseInt(a.querySelector('.badge')?.textContent) || 0;
                    const routesB = parseInt(b.querySelector('.badge')?.textContent) || 0;
                    return routesB - routesA;

                case 'beauty':
                    const beautyA = parseFloat(a.querySelector('.badge-warning')?.textContent.replace('★ ', '')) || 0;
                    const beautyB = parseFloat(b.querySelector('.badge-warning')?.textContent.replace('★ ', '')) || 0;
                    return beautyB - beautyA;

                default:
                    return 0;
            }
        });

        // Réorganiser dans le DOM
        cards.forEach(card => container.appendChild(card));
    }

    /**
     * Afficher/masquer le message de filtrage vide
     */
    toggleEmptyFilterMessage(show) {
        let message = document.querySelector('.empty-filter-message');

        if (show && !message) {
            message = document.createElement('div');
            message.className = 'empty-filter-message text-center py-4 text-muted';
            message.innerHTML = `
                <i class="fas fa-filter"></i>
                <p>Aucun secteur ne correspond au filtre</p>
            `;
            document.querySelector('.sectors-list').appendChild(message);
        } else if (!show && message) {
            message.remove();
        }
    }

    /**
     * Configuration des actions rapides
     */
    setupQuickActions() {
        const actionLinks = document.querySelectorAll('.action-link');

        actionLinks.forEach(link => {
            link.addEventListener('mouseenter', () => {
                link.style.transform = 'translateX(5px)';
            });

            link.addEventListener('mouseleave', () => {
                link.style.transform = 'translateX(0)';
            });
        });

        // Actions spéciales
        this.setupHierarchySelector();
        this.setupStatsAction();
    }

    /**
     * Configuration du sélecteur hiérarchique
     */
    setupHierarchySelector() {
        const hierarchyLink = document.querySelector('a[href*="/selector"]');
        if (!hierarchyLink) return;

        hierarchyLink.addEventListener('click', (e) => {
            // Si Ctrl+Clic, ouvrir en modal au lieu d'un nouvel onglet
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                this.openHierarchySelectorModal();
            }
        });
    }

    /**
     * Ouvrir le sélecteur hiérarchique en modal
     */
    openHierarchySelectorModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade hierarchy-modal';
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Sélecteur hiérarchique</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe src="/sites/selector?mode=select&site_id=${this.siteId}" 
                                style="width: 100%; height: 600px; border: none;">
                        </iframe>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    /**
     * Configuration de l'action statistiques
     */
    setupStatsAction() {
        const statsLink = document.querySelector('a[href*="mode=stats"]');
        if (!statsLink) return;

        statsLink.addEventListener('click', (e) => {
            if (e.shiftKey) {
                e.preventDefault();
                this.showQuickStats();
            }
        });
    }

    /**
     * Afficher des statistiques rapides
     */
    async showQuickStats() {
        try {
            const response = await fetch(`/api/sites/${this.siteId}/stats`);
            const stats = await response.json();

            const modal = document.createElement('div');
            modal.className = 'modal fade stats-modal';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Statistiques rapides</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="stats-grid">
                                ${Object.entries(stats).map(([key, value]) => `
                                    <div class="stat-item">
                                        <strong>${value}</strong>
                                        <small>${key}</small>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });

        } catch (error) {
            console.error('Erreur lors du chargement des statistiques:', error);
        }
    }

    /**
     * Raccourcis clavier
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Raccourcis uniquement si pas de focus sur input
            if (document.activeElement.tagName === 'INPUT') return;

            switch (e.key) {
                case 'e':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        const editBtn = document.querySelector('a[href*="/edit"]:not(.sector-card a)');
                        if (editBtn) editBtn.click();
                    }
                    break;

                case 'n':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        const newBtn = document.querySelector('a[href*="create"][href*="sector"]');
                        if (newBtn) newBtn.click();
                    }
                    break;

                case 'h':
                    e.preventDefault();
                    const hierarchyBtn = document.querySelector('a[href*="/selector"]');
                    if (hierarchyBtn) hierarchyBtn.click();
                    break;

                case 'p':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        window.print();
                    }
                    break;

                case 'Escape':
                    // Fermer les menus contextuels
                    document.querySelectorAll('.sector-context-menu').forEach(menu => menu.remove());
                    break;
            }
        });
    }

    /**
     * Configuration des liens d'action
     */
    setupActionLinks() {
        // Améliorer tous les liens avec des icônes
        const actionLinks = document.querySelectorAll('a[class*="btn"]');

        actionLinks.forEach(link => {
            link.addEventListener('mouseenter', () => {
                const icon = link.querySelector('i');
                if (icon) {
                    icon.style.transform = 'scale(1.1)';
                }
            });

            link.addEventListener('mouseleave', () => {
                const icon = link.querySelector('i');
                if (icon) {
                    icon.style.transform = 'scale(1)';
                }
            });
        });
    }

    /**
     * Mode impression
     */
    enablePrintMode() {
        // Optimiser pour l'impression
        window.addEventListener('beforeprint', () => {
            document.body.classList.add('print-mode');

            // Masquer les éléments non pertinents pour l'impression
            document.querySelectorAll('.btn, .dropdown, .action-link, .nav-link').forEach(el => {
                el.style.display = 'none';
            });

            // Afficher toutes les cartes (ignorer les filtres)
            document.querySelectorAll('.sector-card').forEach(card => {
                card.style.display = 'block';
            });
        });

        window.addEventListener('afterprint', () => {
            document.body.classList.remove('print-mode');

            // Restaurer l'affichage normal
            document.querySelectorAll('.btn, .dropdown, .action-link, .nav-link').forEach(el => {
                el.style.display = '';
            });
        });
    }
}

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    new SiteShow();
});

// CSS additionnel pour les améliorations
const additionalCSS = `
    .sector-context-menu {
        background: white;
        border: 1px solid var(--border-color, #ddd);
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 8px 0;
        min-width: 150px;
    }
    
    .context-item {
        padding: 8px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s;
    }
    
    .context-item:hover {
        background-color: var(--bg-light, #f8f9fa);
    }
    
    .sector-filter-bar {
        background: var(--bg-light, #f8f9fa);
        padding: 15px;
        border-radius: 6px;
        border: 1px solid var(--border-color, #e1e8ed);
    }
    
    .hierarchy-modal .modal-dialog {
        max-width: 90%;
        margin: 2rem auto;
    }
    
    .stats-modal .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
    }
    
    .stats-modal .stat-item {
        text-align: center;
        padding: 15px;
        background: var(--bg-light, #f8f9fa);
        border-radius: 6px;
    }
    
    .stats-modal .stat-item strong {
        display: block;
        font-size: 24px;
        color: var(--primary-color, #2c3e50);
    }
    
    .stats-modal .stat-item small {
        color: var(--text-muted, #6c757d);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    @media print {
        .print-mode .sidebar-section,
        .print-mode .sector-filter-bar {
            display: none !important;
        }
        
        .print-mode .sector-card {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
`;

// Injecter le CSS additionnel
if (!document.querySelector('#site-show-enhancement-css')) {
    const style = document.createElement('style');
    style.id = 'site-show-enhancement-css';
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}