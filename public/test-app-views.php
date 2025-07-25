<?php
// Test ViewManager sur l'application réelle
require_once dirname(__DIR__) . '/bootstrap.php';

use TopoclimbCH\Core\Container;

// Simuler quelques données de test
$books = [
    (object)['id' => 1, 'name' => 'Guide Valais Central', 'code' => 'VAL-001', 'publisher' => 'SAC', 'year' => 2023],
    (object)['id' => 2, 'name' => 'Escalade Jura', 'code' => 'JUR-002', 'publisher' => 'FFME', 'year' => 2022],
    (object)['id' => 3, 'name' => 'Grimpe Tessinoise', 'code' => 'TES-003', 'publisher' => 'CAS', 'year' => 2024],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test ViewManager - Application Réelle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/view-modes.css">
    <style>
        .debug-panel {
            position: fixed; top: 10px; right: 10px; width: 300px; background: #000; color: #0f0;
            padding: 10px; font-family: monospace; font-size: 11px; border-radius: 5px; z-index: 9999;
        }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .status.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body class="books-index-page">
    <div class="debug-panel" id="debug-panel">
        <strong>🔍 Test Application Réelle</strong><br>
        <div id="debug-logs"></div>
        <button onclick="testViews()" class="btn btn-sm btn-light mt-2">Test Views</button>
    </div>

    <div class="container mt-4">
        <h1>🧪 Test ViewManager - Application TopoclimbCH</h1>
        
        <div class="status ok">
            <strong>✅ Application Chargée</strong><br>
            Bootstrap: <?= dirname(__DIR__) . '/bootstrap.php' ?><br>
            Books de test: <?= count($books) ?> éléments
        </div>

        <!-- Structure identique aux vraies pages -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1>Guides d'escalade</h1>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
                <div class="results-info">
                    <span class="text-muted"><?= count($books) ?> guide(s) trouvé(s)</span>
                </div>
                
                <!-- Controls d'affichage -->
                <div class="view-controls">
                    <div class="btn-group" role="group" aria-label="Mode d'affichage">
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
            </div>
        </div>

        <!-- Conteneur principal -->
        <div class="books-container entities-container" id="books-container">
            <!-- Vue grille (cartes) -->
            <div class="books-grid entities-grid view-grid active" id="books-grid">
                <?php foreach($books as $book): ?>
                    <div class="book-card entity-card card h-100" data-book-id="<?= $book->id ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-1">
                                    <a href="/books/<?= $book->id ?>" class="text-decoration-none"><?= $book->name ?></a>
                                </h5>
                                <div class="book-actions">
                                    <button class="btn btn-sm btn-outline-secondary quick-favorite" 
                                            title="Favoris" data-action="favorite" data-id="<?= $book->id ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary quick-share" 
                                            title="Partager" data-action="share" data-id="<?= $book->id ?>">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="book-meta text-muted small mb-2">
                                <span class="me-2">📝 <?= $book->code ?></span>
                                <span class="me-2">🏢 <?= $book->publisher ?></span>
                                <span class="me-2">📅 <?= $book->year ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Vue Liste -->
            <div class="books-list entities-list view-list" id="books-list">
                <?php foreach($books as $book): ?>
                    <div class="list-item d-flex align-items-center p-3 mb-2 bg-white rounded shadow-sm" data-book-id="<?= $book->id ?>">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <a href="/books/<?= $book->id ?>" class="text-decoration-none"><?= $book->name ?></a>
                            </h5>
                            <div class="text-muted small mb-1">
                                📝 <?= $book->code ?> - 🏢 <?= $book->publisher ?> (📅 <?= $book->year ?>)
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="btn-group-vertical">
                                <button class="btn btn-sm btn-outline-secondary mb-1" title="Favoris" data-action="favorite" data-id="<?= $book->id ?>">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" title="Partager" data-action="share" data-id="<?= $book->id ?>">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Vue Compacte -->
            <div class="books-compact entities-compact view-compact" id="books-compact">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Code</th>
                                <th>Éditeur</th>
                                <th>Année</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($books as $book): ?>
                                <tr data-book-id="<?= $book->id ?>">
                                    <td>
                                        <a href="/books/<?= $book->id ?>" class="text-decoration-none fw-medium"><?= $book->name ?></a>
                                    </td>
                                    <td class="text-muted"><?= $book->code ?></td>
                                    <td class="text-muted"><?= $book->publisher ?></td>
                                    <td class="text-muted"><?= $book->year ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" title="Favoris" data-action="favorite" data-id="<?= $book->id ?>">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" title="Partager" data-action="share" data-id="<?= $book->id ?>">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ViewManager avec application réelle -->
    <script src="js/view-manager.js"></script>
    
    <script>
        let debugLogs = [];
        
        // Capturer logs
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            debugLogs.push('[LOG] ' + args.join(' '));
            updateDebugPanel();
        };
        
        function updateDebugPanel() {
            const panel = document.getElementById('debug-logs');
            panel.innerHTML = debugLogs.slice(-10).map(log => 
                '<div style="margin:1px 0; word-break: break-all; font-size: 10px;">' + log + '</div>'
            ).join('');
        }
        
        function testViews() {
            debugLogs.push('[TEST] === TEST VIEWMANAGER ===');
            const container = document.querySelector('.entities-container');
            const views = container.querySelectorAll('.view-grid, .view-list, .view-compact');
            
            debugLogs.push('[TEST] Container: ' + (container ? 'OK' : 'MANQUANT'));
            debugLogs.push('[TEST] Vues trouvées: ' + views.length);
            
            views.forEach((view, i) => {
                const computed = window.getComputedStyle(view);
                debugLogs.push(`[TEST] Vue ${i}: ${view.classList.contains('active') ? 'ACTIVE' : 'CACHÉE'} (${computed.display})`);
            });
            
            const buttons = document.querySelectorAll('[data-view]');
            debugLogs.push('[TEST] Boutons: ' + buttons.length);
            
            updateDebugPanel();
        }
        
        // Init ViewManager
        document.addEventListener('DOMContentLoaded', () => {
            debugLogs.push('[SYSTEM] DOM chargé, init ViewManager...');
            updateDebugPanel();
            
            setTimeout(() => {
                try {
                    window.appViewManager = new ViewManager('.books-container');
                    debugLogs.push('[SYSTEM] ViewManager: ' + (window.appViewManager ? 'OK' : 'FAILED'));
                    updateDebugPanel();
                    
                    // Test automatique
                    setTimeout(testViews, 500);
                    
                } catch (error) {
                    debugLogs.push('[ERROR] Init failed: ' + error.message);
                    updateDebugPanel();
                }
            }, 100);
        });
    </script>
</body>
</html>