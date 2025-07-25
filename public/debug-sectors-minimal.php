<?php
// Debug minimal pour identifier pourquoi ViewManager ne trouve qu'1 vue
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Sectors Minimal</title>
    <link rel="stylesheet" href="css/view-modes.css">
</head>
<body>
    <h1>🔍 Debug Sectors - Version Minimale</h1>
    
    <!-- EXACTEMENT la structure du template sectors -->
    <div class="sectors-container entities-container" id="sectors-container">
        
        <!-- Vue GRID (toujours créée) -->
        <div class="sectors-grid entities-grid view-grid active" id="sectors-grid">
            <div class="empty-state">GRID VIEW (toujours créée)</div>
        </div>
        
        <!-- Vue LIST (problématique ?) -->
        <div class="sectors-list entities-list view-list" id="sectors-list">
            <div class="empty-state">LIST VIEW (doit être créée)</div>
        </div>
        
        <!-- Vue COMPACT (problématique ?) -->
        <div class="sectors-compact entities-compact view-compact" id="sectors-compact">
            <div class="empty-state">COMPACT VIEW (doit être créée)</div>
        </div>
        
    </div>

    <script src="js/view-manager.js"></script>
    <script>
        console.log('🔍 DEBUG MINIMAL - DEBUT');
        
        // Vérifier immédiatement le DOM
        const container = document.querySelector('.entities-container');
        console.log('Container trouvé:', !!container);
        
        if (container) {
            const views = container.querySelectorAll('.view-grid, .view-list, .view-compact');
            console.log('🎯 VUES TROUVEES:', views.length);
            
            views.forEach((view, i) => {
                console.log(`Vue ${i}:`, view.className);
            });
            
            // Test spécifique des sélecteurs
            const grid = container.querySelector('.view-grid');
            const list = container.querySelector('.view-list');
            const compact = container.querySelector('.view-compact');
            
            console.log('Grid trouvée:', !!grid);
            console.log('List trouvée:', !!list);
            console.log('Compact trouvée:', !!compact);
        }
        
        // Initialiser ViewManager
        setTimeout(() => {
            console.log('🚀 Initialisation ViewManager...');
            window.debugManager = new ViewManager('.sectors-container');
        }, 100);
    </script>
</body>
</html>