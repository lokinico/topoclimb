/**
 * Gestionnaire des modes d'affichage pour les pages de liste
 * Supporte les modes: grid (cartes), list (liste), compact (compact)
 */
class ViewManager {
    constructor(containerSelector = '.entities-container') {
        this.container = document.querySelector(containerSelector);
        this.currentView = null; // Sera détecté depuis le DOM
        
        console.log('ViewManager constructor:', containerSelector, this.container);
        
        if (!this.container) {
            console.warn('ViewManager: Container not found:', containerSelector);
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
        
        console.log('ViewManager: Detected initial view from DOM:', this.currentView);
    }
    
    init() {
        console.log('ViewManager init started - Current view detected:', this.currentView);
        
        // Debug: Vérifier les vues disponibles dans le container
        const views = this.container.querySelectorAll('.view-grid, .view-list, .view-compact');
        console.log('ViewManager: Found views:', views.length);
        views.forEach((view, i) => {
            const computed = window.getComputedStyle(view);
            console.log(`ViewManager: View ${i}:`, view.className, 
                'Active:', view.classList.contains('active'), 
                'Display:', computed.display);
        });
        
        this.setupViewControls();
        this.loadSavedView();
        this.setupQuickActions();
        console.log('ViewManager init completed - Final state:', this.currentView);
    }
    
    setupViewControls() {
        // Chercher les boutons dans toute la page, pas seulement dans le container
        const viewControls = document.querySelectorAll('[data-view]');
        console.log('ViewManager: Found view controls:', viewControls.length);
        
        viewControls.forEach((control, index) => {
            console.log(`ViewManager: Setting up control ${index}:`, control.dataset.view);
            
            // Supprimer les anciens événements
            control.removeEventListener('click', this.handleViewChange);
            control.removeEventListener('keydown', this.handleKeyboardNavigation);
            
            // Ajouter le nouvel événement de clic
            control.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('ViewManager: Button clicked:', control.dataset.view);
                const viewType = control.dataset.view;
                this.switchView(viewType);
                this.updateActiveButton(control);
            });
            
            // Ajouter la navigation clavier
            control.addEventListener('keydown', (e) => {
                this.handleKeyboardNavigation(e, control);
            });
        });
    }
    
    handleKeyboardNavigation(e, currentControl) {
        const viewControls = Array.from(document.querySelectorAll('[data-view]'));
        const currentIndex = viewControls.indexOf(currentControl);
        
        switch (e.key) {
            case 'ArrowLeft':
            case 'ArrowUp':
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : viewControls.length - 1;
                viewControls[prevIndex].focus();
                break;
                
            case 'ArrowRight':
            case 'ArrowDown':
                e.preventDefault();
                const nextIndex = currentIndex < viewControls.length - 1 ? currentIndex + 1 : 0;
                viewControls[nextIndex].focus();
                break;
                
            case 'Home':
                e.preventDefault();
                viewControls[0].focus();
                break;
                
            case 'End':
                e.preventDefault();
                viewControls[viewControls.length - 1].focus();
                break;
                
            case 'Enter':
            case ' ':
                e.preventDefault();
                const viewType = currentControl.dataset.view;
                this.switchView(viewType);
                this.updateActiveButton(currentControl);
                break;
        }
    }
    
    switchView(viewType) {
        if (!['grid', 'list', 'compact'].includes(viewType)) {
            console.warn('ViewManager: Invalid view type:', viewType);
            return;
        }
        
        // Ne pas changer si c'est déjà la vue active
        if (this.currentView === viewType) {
            console.log('ViewManager: ✅ View already active:', viewType);
            return;
        }
        
        console.log('ViewManager: 🔄 Switching from', this.currentView, 'to view:', viewType);
        
        // FORCER le masquage de TOUTES les vues d'abord
        const allViews = this.container.querySelectorAll('.view-grid, .view-list, .view-compact');
        console.log('ViewManager: Found views for hiding:', allViews.length);
        
        allViews.forEach((view) => {
            console.log('ViewManager: 👁️ Force hiding view:', view.className);
            view.classList.remove('active');
            // Force hide via style également 
            view.style.display = 'none';
        });
        
        // Trouver et FORCER l'affichage de la vue cible
        const targetView = this.container.querySelector(`.view-${viewType}`);
        
        if (targetView) {
            console.log('ViewManager: ✅ Force activating view:', targetView.className);
            targetView.classList.add('active');
            
            // Force show via style également
            if (viewType === 'grid') {
                targetView.style.display = 'grid';
            } else {
                targetView.style.display = 'block';
            }
            
            // Gestion du focus pour l'accessibilité
            targetView.focus({ preventScroll: true });
            console.log('ViewManager: ✅ Focus moved to new view:', targetView.id);
            
            console.log('ViewManager: ✅ View switched successfully to:', viewType);
            
            // Test final
            const finalComputed = window.getComputedStyle(targetView);
            console.log('ViewManager: Final computed display:', finalComputed.display);
        } else {
            console.error('ViewManager: ❌ Could not find target view for:', viewType);
            console.log('ViewManager: Available views:', 
                Array.from(this.container.querySelectorAll('[class*="view-"]')).map(v => v.className));
        }
        
        this.currentView = viewType;
        this.saveViewPreference(viewType);
        this.announceViewChange(viewType);
    }
    
    updateActiveButton(activeButton) {
        // Retirer la classe active de tous les boutons et mettre à jour aria-pressed
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
        });
        
        // Ajouter la classe active au bouton cliqué et mettre à jour aria-pressed
        activeButton.classList.add('active');
        activeButton.setAttribute('aria-pressed', 'true');
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
            console.log('ViewManager: Raw saved view from localStorage:', savedView);
            console.log('ViewManager: Current view from DOM:', this.currentView);
            
            if (savedView && ['grid', 'list', 'compact'].includes(savedView)) {
                console.log('ViewManager: 💾 Loading saved view:', savedView);
                
                // Si la vue sauvegardée est différente de celle détectée, faire le switch
                if (savedView !== this.currentView) {
                    console.log('ViewManager: Switching from', this.currentView, 'to saved view', savedView);
                    this.switchView(savedView);
                } else {
                    console.log('ViewManager: Saved view matches current view, no switch needed');
                }
                
                // Mettre à jour le bouton actif
                const button = document.querySelector(`[data-view="${savedView}"]`);
                if (button) {
                    this.updateActiveButton(button);
                }
            } else {
                console.log('ViewManager: 🆕 No saved view, using detected view:', this.currentView);
                
                // S'assurer que le bouton correspondant est actif
                const currentButton = document.querySelector(`[data-view="${this.currentView}"]`);
                if (currentButton) {
                    this.updateActiveButton(currentButton);
                }
                
                // Annoncer la vue initiale
                this.announceViewChange(this.currentView);
            }
        } catch (e) {
            console.warn('ViewManager: ⚠️ Could not load view preference:', e);
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
    
    announceViewChange(viewType) {
        const announcer = document.getElementById('view-change-announcer');
        if (announcer) {
            // Capitaliser la première lettre pour une meilleure synthèse vocale
            const viewName = viewType.charAt(0).toUpperCase() + viewType.slice(1);
            let announcement = '';
            
            switch (viewType) {
                case 'grid':
                    announcement = 'Affichage en mode cartes activé.';
                    break;
                case 'list':
                    announcement = 'Affichage en mode liste activé.';
                    break;
                case 'compact':
                    announcement = 'Affichage en mode compact activé.';
                    break;
                default:
                    announcement = `Affichage en mode ${viewName} activé.`;
            }
            
            announcer.textContent = announcement;
            console.log('ViewManager: Announced view change:', announcement);
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