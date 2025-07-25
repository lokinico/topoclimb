/**
 * Gestionnaire des modes d'affichage pour les pages de liste
 * Supporte les modes: grid (cartes), list (liste), compact (compact)
 */
class ViewManager {
    constructor(containerSelector = '.entities-container') {
        this.container = document.querySelector(containerSelector);
        this.currentView = 'grid';
        
        console.log('ViewManager constructor:', containerSelector, this.container);
        
        if (!this.container) {
            console.warn('ViewManager: Container not found:', containerSelector);
            return;
        }
        
        this.init();
    }
    
    init() {
        console.log('ViewManager init started');
        this.setupViewControls();
        this.loadSavedView();
        this.setupQuickActions();
        console.log('ViewManager init completed');
    }
    
    setupViewControls() {
        // Chercher les boutons dans toute la page, pas seulement dans le container
        const viewControls = document.querySelectorAll('[data-view]');
        console.log('ViewManager: Found view controls:', viewControls.length);
        
        viewControls.forEach((control, index) => {
            console.log(`ViewManager: Setting up control ${index}:`, control.dataset.view);
            
            // Supprimer les anciens événements
            control.removeEventListener('click', this.handleViewChange);
            
            // Ajouter le nouvel événement
            control.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('ViewManager: Button clicked:', control.dataset.view);
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
        
        // Chercher TOUS les éléments de vue dans le container
        const allViews = this.container.querySelectorAll('[class*="view-"]');
        console.log('ViewManager: Found all views:', allViews.length);
        
        // Masquer toutes les vues
        allViews.forEach((view, index) => {
            console.log(`ViewManager: Processing view ${index}:`, view.className);
            view.classList.remove('active');
            view.style.display = 'none';
        });
        
        // Chercher spécifiquement la vue cible
        const targetSelectors = [
            `.view-${viewType}`,
            `.${viewType}-grid`,
            `.${viewType}-list`, 
            `.${viewType}-compact`,
            `#${this.container.id.replace('-container', '')}-${viewType}`
        ];
        
        let targetView = null;
        for (const selector of targetSelectors) {
            targetView = this.container.querySelector(selector);
            if (targetView) {
                console.log('ViewManager: Found target view with selector:', selector);
                break;
            }
        }
        
        if (targetView) {
            targetView.classList.add('active');
            targetView.style.display = viewType === 'grid' ? 'grid' : 'block';
            console.log('ViewManager: View switched successfully to:', viewType);
        } else {
            console.error('ViewManager: Could not find target view for:', viewType);
            console.log('ViewManager: Available views in container:', this.container.innerHTML);
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
        console.log('ViewManager: Updated active button to:', activeButton.dataset.view);
    }
    
    saveViewPreference(viewType) {
        try {
            localStorage.setItem('topoclimb_view_preference', viewType);
            console.log('ViewManager: Saved view preference:', viewType);
        } catch (e) {
            console.warn('Could not save view preference:', e);
        }
    }
    
    loadSavedView() {
        try {
            const savedView = localStorage.getItem('topoclimb_view_preference');
            if (savedView && ['grid', 'list', 'compact'].includes(savedView)) {
                console.log('ViewManager: Loading saved view:', savedView);
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
console.log('ViewManager script loaded');

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing ViewManager...');
    
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
                console.log('ViewManager: ✅ Initializing for container:', selector);
                window.viewManager = new ViewManager(selector);
                found = true;
                
                // Test immédiat des boutons
                const buttons = document.querySelectorAll('[data-view]');
                console.log('ViewManager: ✅ Found', buttons.length, 'view buttons');
                buttons.forEach((btn, i) => {
                    console.log(`ViewManager: Button ${i} - ${btn.dataset.view}:`, btn);
                });
            }
        });
        
        if (!found) {
            console.error('ViewManager: ❌ No valid containers found!');
            console.log('Available containers:', document.querySelectorAll('[class*="container"]'));
            console.log('Available view buttons:', document.querySelectorAll('[data-view]'));
        }
    }, 100); // Réduire le délai pour test plus rapide
});

// Export global pour debug
window.ViewManager = ViewManager;