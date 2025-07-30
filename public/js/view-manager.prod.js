/**
 * Gestionnaire des modes d'affichage pour les pages de liste
 * Supporte les modes: grid (cartes), list (liste), compact (compact)
 */
class ViewManager {
    constructor(containerSelector = '.entities-container') {
        this.container = document.querySelector(containerSelector);
        this.currentView = null; // Sera détecté depuis le DOM
        if (!this.container) {
            return;
        }
        // Détecter quelle vue est active dans le HTML
        this.detectInitialView();
        this.init();
    }
    detectInitialView() {
        // Chercher quelle vue a la classe 'active' dans le HTML
        const activeView = this.container.querySelector('.view-grid.active, .view-list.active, .view-compact.active');
        if (activeView) {
            if (activeView.classList.contains('view-grid')) {
                this.currentView = 'grid';
            } else if (activeView.classList.contains('view-list')) {
                this.currentView = 'list';
            } else if (activeView.classList.contains('view-compact')) {
                this.currentView = 'compact';
            }
        } else {
            // Fallback si aucune vue active trouvée
            this.currentView = 'grid';
        }
    }
    init() {
        // Debug: Vérifier les vues disponibles dans le container
        const views = this.container.querySelectorAll('.view-grid, .view-list, .view-compact');
        views.forEach((view, i) => {
            const computed = window.getComputedStyle(view);
        });
        this.setupViewControls();
        this.loadSavedView();
        this.setupQuickActions();
    }
    setupViewControls() {
        // Chercher les boutons dans toute la page, pas seulement dans le container
        const viewControls = document.querySelectorAll('[data-view]');
        viewControls.forEach((control, index) => {
            // Supprimer les anciens événements
            control.removeEventListener('click', this.handleViewChange);
            // Ajouter le nouvel événement
            control.addEventListener('click', (e) => {
                e.preventDefault();
                const viewType = control.dataset.view;
                this.switchView(viewType);
                this.updateActiveButton(control);
            });
        });
    }
    switchView(viewType) {
        if (!['grid', 'list', 'compact'].includes(viewType)) {
            return;
        }
        // Ne pas changer si c'est déjà la vue active
        if (this.currentView === viewType) {
            return;
        }
        // FORCER le masquage de TOUTES les vues d'abord
        const allViews = this.container.querySelectorAll('.view-grid, .view-list, .view-compact');
        allViews.forEach((view) => {
            view.classList.remove('active');
            // Force hide via style également 
            view.style.display = 'none';
        });
        // Trouver et FORCER l'affichage de la vue cible
        const targetView = this.container.querySelector(`.view-${viewType}`);
        if (targetView) {
            targetView.classList.add('active');
            // Force show via style également
            if (viewType === 'grid') {
                targetView.style.display = 'grid';
            } else {
                targetView.style.display = 'block';
            }
                        const finalComputed = window.getComputedStyle(targetView);
        } else {
        }
        this.currentView = viewType;
        this.saveViewPreference(viewType);
    }
    updateActiveButton(activeButton) {
        // Retirer la classe active de tous les boutons
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.classList.remove('active');
        });
        // Ajouter la classe active au bouton cliqué
        activeButton.classList.add('active');
    }
    saveViewPreference(viewType) {
        try {
            localStorage.setItem('topoclimb_view_preference', viewType);
        } catch (e) {
        }
    }
    loadSavedView() {
        try {
            const savedView = localStorage.getItem('topoclimb_view_preference');
            if (savedView && ['grid', 'list', 'compact'].includes(savedView)) {
                // Si la vue sauvegardée est différente de celle détectée, faire le switch
                if (savedView !== this.currentView) {
                    this.switchView(savedView);
                } else {
                }
                // Mettre à jour le bouton actif
                const button = document.querySelector(`[data-view="${savedView}"]`);
                if (button) {
                    this.updateActiveButton(button);
                }
            } else {
                // S'assurer que le bouton correspondant est actif
                const currentButton = document.querySelector(`[data-view="${this.currentView}"]`);
                if (currentButton) {
                    this.updateActiveButton(currentButton);
                }
            }
        } catch (e) {
            // En cas d'erreur, garder la vue détectée depuis le DOM
        }
    }
    setupQuickActions() {
        // Actions météo
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="weather"]')) {
                const button = e.target.closest('[data-action="weather"]');
                const entityId = button.dataset.id;
                this.handleWeatherAction(entityId);
            }
        });
        // Actions carte
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="map"]')) {
                const button = e.target.closest('[data-action="map"]');
                const entityId = button.dataset.id;
                this.handleMapAction(entityId);
            }
        });
        // Actions GPS (spécifique aux secteurs)
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="gps"]')) {
                const button = e.target.closest('[data-action="gps"]');
                const entityId = button.dataset.id;
                this.handleGpsAction(entityId);
            }
        });
        // Actions favoris
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="favorite"]')) {
                const button = e.target.closest('[data-action="favorite"]');
                const entityId = button.dataset.id;
                this.handleFavoriteAction(entityId, button);
            }
        });
        // Actions partage
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="share"]')) {
                const button = e.target.closest('[data-action="share"]');
                const entityId = button.dataset.id;
                this.handleShareAction(entityId);
            }
        });
    }
    handleWeatherAction(entityId) {
        this.showToast('Météo en cours de chargement...', 'info');
        setTimeout(() => {
            this.showToast('Fonctionnalité météo bientôt disponible', 'warning');
        }, 1000);
    }
    handleMapAction(entityId) {
        this.showToast('Ouverture de la carte...', 'info');
        setTimeout(() => {
            this.showToast('Fonctionnalité carte bientôt disponible', 'warning');
        }, 1000);
    }
    handleGpsAction(entityId) {
        this.showToast('Ouverture GPS Navigation...', 'info');
        setTimeout(() => {
            this.showToast('Fonctionnalité GPS bientôt disponible', 'warning');
        }, 1000);
    }
    handleShareAction(entityId) {
        // Essayer d'utiliser l'API native de partage si disponible
        if (navigator.share) {
            navigator.share({
                title: 'TopoclimbCH',
                text: 'Découvrez ce contenu sur TopoclimbCH',
                url: window.location.href
            }).then(() => {
                this.showToast('Partagé avec succès', 'success');
            }).catch(() => {
                this.copyToClipboard();
            });
        } else {
            // Fallback: copier l'URL dans le presse-papier
            this.copyToClipboard();
        }
    }
    copyToClipboard() {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.showToast('URL copiée dans le presse-papier', 'success');
            }).catch(() => {
                this.showToast('Impossible de copier l\'URL', 'error');
            });
        } else {
            // Fallback legacy
            const textArea = document.createElement('textarea');
            textArea.value = window.location.href;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                this.showToast('URL copiée dans le presse-papier', 'success');
            } catch (err) {
                this.showToast('Impossible de copier l\'URL', 'error');
            }
            document.body.removeChild(textArea);
        }
    }
    handleFavoriteAction(entityId, button) {
        const isFavorite = button.classList.contains('favorited');
        if (isFavorite) {
            button.classList.remove('favorited');
            button.innerHTML = '<i class="fas fa-heart"></i>';
            this.showToast('Retiré des favoris', 'success');
        } else {
            button.classList.add('favorited');
            button.innerHTML = '<i class="fas fa-heart text-danger"></i>';
            this.showToast('Ajouté aux favoris', 'success');
        }
    }
    showToast(message, type = 'info') {
        // Créer un toast simple
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.textContent = message;
        // Styles inline pour le toast
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '12px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '500',
            zIndex: '9999',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease',
            maxWidth: '300px'
        });
        // Couleurs selon le type
        const colors = {
            info: '#17a2b8',
            success: '#28a745',
            warning: '#ffc107',
            error: '#dc3545'
        };
        toast.style.backgroundColor = colors[type] || colors.info;
        document.body.appendChild(toast);
        // Animation d'entrée
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        // Suppression automatique
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
}
// Auto-initialisation FORCÉE
document.addEventListener('DOMContentLoaded', () => {
    // Attendre un peu pour que tout soit bien chargé
    setTimeout(() => {
        // Chercher tous les conteneurs possibles
        const selectors = [
            '.regions-container',
            '.sites-container', 
            '.sectors-container',
            '.routes-container',
            '.books-container',
            '.entities-container'
        ];
        let found = false;
        selectors.forEach(selector => {
            const container = document.querySelector(selector);
            if (container) {
                window.viewManager = new ViewManager(selector);
                found = true;
                                const buttons = document.querySelectorAll('[data-view]');
                buttons.forEach((btn, i) => {
                });
            }
        });
        if (!found) {
        }
    }, 100); // Réduire le délai pour test plus rapide
});
// Export global pour debug
window.ViewManager = ViewManager;