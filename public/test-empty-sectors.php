<?php
// Test avec données vides pour voir le HTML généré
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Sectors Vides</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/view-modes.css">
</head>
<body class="sectors-index-page">
    <div class="container mt-4">
        <h1>🧪 Test Sectors avec données VIDES</h1>
        
        <!-- Controls d'affichage -->
        <div class="view-controls mb-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" data-view="grid">
                    <i class="fas fa-th"></i> Cartes
                </button>
                <button type="button" class="btn btn-outline-primary" data-view="list">
                    <i class="fas fa-list"></i> Liste
                </button>
                <button type="button" class="btn btn-outline-primary" data-view="compact">
                    <i class="fas fa-bars"></i> Compact
                </button>
            </div>
        </div>

        <!-- Simulation EXACTE du template sectors avec données VIDES -->
        <div class="sectors-container entities-container" id="sectors-container">
            <?php 
            // Simuler sectorItems vide
            $sectorItems = []; // VIDE comme en production
            ?>
            
            <!-- Vue grille (EXACTEMENT comme dans le template) -->
            <div class="sectors-grid entities-grid view-grid active" id="sectors-grid">
                <?php if (count($sectorItems) > 0): ?>
                    <?php foreach($sectorItems as $sector): ?>
                        <div>Sector content</div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <div class="empty-icon mb-3">
                            <i class="fas fa-mountain fa-3x text-muted"></i>
                        </div>
                        <h4 class="text-muted">Aucun secteur trouvé (GRID)</h4>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Vue Liste (EXACTEMENT comme dans le template) -->
            <div class="sectors-list entities-list view-list" id="sectors-list">
                <?php if (count($sectorItems) > 0): ?>
                    <?php foreach($sectorItems as $sector): ?>
                        <div>List content</div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <div class="empty-icon mb-3">
                            <i class="fas fa-map-signs fa-3x text-muted"></i>
                        </div>
                        <h4 class="text-muted">Aucun secteur trouvé (LIST)</h4>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Vue Compacte (EXACTEMENT comme dans le template) -->
            <div class="sectors-compact entities-compact view-compact" id="sectors-compact">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sectorItems) > 0): ?>
                                <?php foreach($sectorItems as $sector): ?>
                                    <tr><td>Sector</td></tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-map-signs fa-2x text-muted mb-2"></i>
                                            <div class="text-muted">Aucun secteur trouvé (COMPACT)</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/view-manager.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🧪 Test avec données VIDES');
            
            // Vérifier combien de vues existent
            const container = document.querySelector('.entities-container');
            const views = container.querySelectorAll('.view-grid, .view-list, .view-compact');
            
            console.log('📊 Nombre de vues trouvées:', views.length);
            views.forEach((view, i) => {
                const computed = window.getComputedStyle(view);
                console.log(`Vue ${i}:`, view.className, 'Display:', computed.display);
            });
            
            // Initialiser ViewManager
            window.testManager = new ViewManager('.sectors-container');
        });
    </script>
</body>
</html>