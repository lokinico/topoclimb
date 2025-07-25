/**
 * Gestionnaire des modes d'affichage pour les pages de liste
 * Supporte les modes: grid (cartes), list (liste), compact (compact)
 */
class ViewManager {
    constructor(containerSelector = '.entities-container') {
        this.container = document.querySelector(containerSelector);
        this.currentView = 'grid';
        
        if (!this.container) {
            console.warn('ViewManager: Container not found:', containerSelector);
            return;
        }
        
        this.init();
    }
    
    init() {
        this.setupViewControls();
        this.loadSavedView();
        this.setupQuickActions();
    }
    
    setupViewControls() {
        const viewControls = document.querySelectorAll('[data-view]');
        
        viewControls.forEach(control => {
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
            console.warn('ViewManager: Invalid view type:', viewType);
            return;
        }
        
        console.log('ViewManager: Switching to view:', viewType);
        
        // Masquer toutes les vues
        const allViews = this.container.querySelectorAll('.view-grid, .view-list, .view-compact');
        console.log('ViewManager: Found views:', allViews.length);
        
        allViews.forEach(view => {
            view.classList.remove('active');
            view.style.display = 'none';
        });
        
        // Afficher la vue sélectionnée
        const targetView = this.container.querySelector(`.view-${viewType}`);
        console.log('ViewManager: Target view found:', !!targetView);
        
        if (targetView) {
            targetView.classList.add('active');
            targetView.style.display = viewType === 'grid' ? 'grid' : 'block';
            console.log('ViewManager: View switched successfully to:', viewType);
        } else {
            console.error('ViewManager: Could not find target view:', `.view-${viewType}`);
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
            console.warn('Could not save view preference:', e);
        }
    }
    
    loadSavedView() {
        try {
            const savedView = localStorage.getItem('topoclimb_view_preference');
            if (savedView && ['grid', 'list', 'compact'].includes(savedView)) {
                this.switchView(savedView);
                
                // Mettre à jour le bouton actif
                const button = document.querySelector(`[data-view="${savedView}"]`);
                if (button) {
                    this.updateActiveButton(button);
                }
            }
        } catch (e) {
            console.warn('Could not load view preference:', e);
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
        
        // Actions favoris
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="favorite"]')) {
                const button = e.target.closest('[data-action="favorite"]');
                const entityId = button.dataset.id;
                this.handleFavoriteAction(entityId, button);
            }
        });
    }
    
    handleWeatherAction(entityId) {
        // Pour l'instant, afficher un toast informatif
        this.showToast('Météo en cours de chargement...', 'info');
        
        // TODO: Intégrer avec l'API météo
        setTimeout(() => {
            this.showToast('Fonctionnalité météo bientôt disponible', 'warning');
        }, 1000);
    }
    
    handleMapAction(entityId) {
        // Pour l'instant, afficher un toast informatif
        this.showToast('Ouverture de la carte...', 'info');
        
        // TODO: Intégrer avec le système de cartes
        setTimeout(() => {
            this.showToast('Fonctionnalité carte bientôt disponible', 'warning');
        }, 1000);
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
        
        // TODO: Synchroniser avec le backend
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

// Auto-initialisation
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser pour différents types de conteneurs
    const containers = [
        '.regions-container',
        '.sites-container', 
        '.sectors-container',
        '.routes-container',
        '.books-container',
        '.entities-container'
    ];
    
    containers.forEach(selector => {
        if (document.querySelector(selector)) {
            new ViewManager(selector);
        }
    });
});