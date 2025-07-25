/**
 * VIEW MANAGER SIMPLIFIÉ POUR DEBUGGING
 */
console.log('🚀 ViewManager DEBUG chargé');

document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 DOM loaded, initialisation debug ViewManager...');
    
    // Trouver tous les boutons de vue
    const viewButtons = document.querySelectorAll('[data-view]');
    console.log('🔘 Boutons trouvés:', viewButtons.length);
    
    // Trouver le conteneur
    const container = document.querySelector('.entities-container');
    console.log('📦 Conteneur trouvé:', container ? 'OUI' : 'NON');
    
    if (!container) {
        console.error('❌ Aucun conteneur .entities-container trouvé !');
        return;
    }
    
    // Trouver toutes les vues
    const gridView = container.querySelector('.view-grid');
    const listView = container.querySelector('.view-list');
    const compactView = container.querySelector('.view-compact');
    
    console.log('👁️ Vues trouvées:');
    console.log('  - Grid:', gridView ? 'OUI' : 'NON');
    console.log('  - List:', listView ? 'OUI' : 'NON');
    console.log('  - Compact:', compactView ? 'OUI' : 'NON');
    
    // Fonction pour changer de vue
    function switchView(viewType) {
        console.log('🔄 Changement vers:', viewType);
        
        // Masquer toutes les vues
        [gridView, listView, compactView].forEach(view => {
            if (view) {
                view.classList.remove('active');
                console.log('👁️ Vue masquée:', view.className);
            }
        });
        
        // Afficher la vue demandée
        let targetView = null;
        if (viewType === 'grid') targetView = gridView;
        if (viewType === 'list') targetView = listView;
        if (viewType === 'compact') targetView = compactView;
        
        if (targetView) {
            targetView.classList.add('active');
            console.log('✅ Vue activée:', targetView.className);
        } else {
            console.error('❌ Vue non trouvée pour:', viewType);
        }
        
        // Mettre à jour les boutons
        viewButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.view === viewType) {
                btn.classList.add('active');
            }
        });
    }
    
    // Ajouter les événements aux boutons
    viewButtons.forEach((button, index) => {
        console.log(`🔘 Configuration bouton ${index}:`, button.dataset.view);
        
        button.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('🖱️ CLIC sur bouton:', button.dataset.view);
            switchView(button.dataset.view);
        });
    });
    
    // Initialiser vue par défaut
    console.log('🏁 Initialisation vue par défaut: grid');
    switchView('grid');
    
    console.log('✅ ViewManager DEBUG initialisé avec succès');
});