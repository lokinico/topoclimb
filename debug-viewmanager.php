<?php
// Page de test ViewManager - Isolée et réelle
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEBUG ViewManager - TopoclimbCH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public/css/view-modes.css">
    <style>
        .debug-panel {
            position: fixed;
            top: 10px;
            right: 10px;
            width: 300px;
            background: #000;
            color: #0f0;
            padding: 10px;
            font-family: monospace;
            font-size: 11px;
            border-radius: 5px;
            z-index: 9999;
            max-height: 400px;
            overflow-y: auto;
        }
        .test-data { background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .view-indicator { 
            position: absolute; 
            top: 5px; 
            left: 5px; 
            background: rgba(255,0,0,0.8); 
            color: white; 
            padding: 5px; 
            border-radius: 3px; 
            font-weight: bold;
        }
    </style>
</head>
<body class="books-index-page">
    <div class="debug-panel" id="debug-panel">
        <strong>🔍 ViewManager Debug Panel</strong><br>
        <div id="debug-logs"></div>
        <hr>
        <button onclick="inspectViews()" class="btn btn-sm btn-light">Inspecter Vues</button>
        <button onclick="clearLogs()" class="btn btn-sm btn-light">Clear</button>
        <button onclick="clearStorage()" class="btn btn-sm btn-warning">Reset Storage</button>
    </div>

    <div class="container mt-4">
        <h1>🧪 TEST ViewManager - Page Books</h1>
        
        <div class="test-data">
            <strong>État attendu :</strong><br>
            • Vue GRID active par défaut (avec classe 'active')<br>
            • 3 vues présentes dans le DOM<br>
            • Boutons fonctionnels pour switching<br>
            • CSS cache les vues non-actives
        </div>

        <!-- Controls d'affichage -->
        <div class="view-controls mb-4">
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

        <!-- Conteneur principal avec données de test -->
        <div class="books-container entities-container" id="books-container">
            <!-- Vue grille (ACTIVE par défaut) -->
            <div class="books-grid entities-grid view-grid active" id="books-grid">
                <div class="view-indicator">VUE GRID - ACTIVE</div>
                <?php for($i = 1; $i <= 6; $i++): ?>
                <div class="entity-card card">
                    <div class="card-body">
                        <h5 class="card-title">Guide Test <?= $i ?></h5>
                        <p class="card-text">Ceci est un guide de test en mode GRILLE</p>
                        <div class="text-muted small">📝 CODE-<?= $i ?> | 🏢 Editeur Test</div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-secondary" data-action="favorite" data-id="<?= $i ?>">
                                <i class="far fa-heart"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" data-action="share" data-id="<?= $i ?>">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- Vue Liste (CACHÉE par défaut) -->
            <div class="books-list entities-list view-list" id="books-list">
                <div class="view-indicator">VUE LIST - CACHÉE</div>
                <?php for($i = 1; $i <= 6; $i++): ?>
                <div class="list-item d-flex align-items-center p-3 mb-2 bg-white rounded shadow-sm">
                    <div class="flex-grow-1">
                        <h5>Guide Test <?= $i ?> (MODE LISTE)</h5>
                        <p class="mb-0 text-muted">Description en mode liste pour le guide <?= $i ?></p>
                        <small class="text-muted">📝 CODE-<?= $i ?> | 🏢 Editeur Test | 📅 2024</small>
                    </div>
                    <div class="ms-3">
                        <button class="btn btn-sm btn-outline-secondary" data-action="favorite" data-id="<?= $i ?>">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- Vue Compacte (CACHÉE par défaut) -->
            <div class="books-compact entities-compact view-compact" id="books-compact">
                <div class="view-indicator">VUE COMPACT - CACHÉE</div>
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
                            <?php for($i = 1; $i <= 6; $i++): ?>
                            <tr>
                                <td><strong>Guide Test <?= $i ?></strong></td>
                                <td>CODE-<?= $i ?></td>
                                <td>Editeur Test</td>
                                <td>2024</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" data-action="favorite" data-id="<?= $i ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ViewManager -->
    <script src="public/js/view-manager.js"></script>
    
    <!-- Debug Scripts -->
    <script>
        let debugLogs = [];
        
        // Capturer tous les logs console
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            debugLogs.push('[LOG] ' + args.join(' '));
            updateDebugPanel();
        };
        
        const originalWarn = console.warn;
        console.warn = function(...args) {
            originalWarn.apply(console, args);
            debugLogs.push('[WARN] ' + args.join(' '));
            updateDebugPanel();
        };
        
        const originalError = console.error;
        console.error = function(...args) {
            originalError.apply(console, args);
            debugLogs.push('[ERROR] ' + args.join(' '));
            updateDebugPanel();
        };
        
        function updateDebugPanel() {
            const panel = document.getElementById('debug-logs');
            panel.innerHTML = debugLogs.slice(-15).map(log => 
                '<div style="margin:2px 0; word-break: break-all;">' + log + '</div>'
            ).join('');
            panel.scrollTop = panel.scrollHeight;
        }
        
        function clearLogs() {
            debugLogs = [];
            updateDebugPanel();
        }
        
        function clearStorage() {
            localStorage.removeItem('topoclimb_view_preference');
            debugLogs.push('[SYSTEM] LocalStorage cleared');
            updateDebugPanel();
            location.reload();
        }
        
        function inspectViews() {
            const container = document.querySelector('.entities-container');
            const views = container.querySelectorAll('.view-grid, .view-list, .view-compact');
            
            debugLogs.push('[INSPECT] === INSPECTION DES VUES ===');
            debugLogs.push('[INSPECT] Container: ' + (container ? 'TROUVÉ' : 'MANQUANT'));
            debugLogs.push('[INSPECT] Nombre de vues: ' + views.length);
            
            views.forEach((view, i) => {
                const computed = window.getComputedStyle(view);
                const classes = Array.from(view.classList).join(' ');
                debugLogs.push(`[INSPECT] Vue ${i}: ${classes}`);
                debugLogs.push(`[INSPECT] - Active: ${view.classList.contains('active')}`);
                debugLogs.push(`[INSPECT] - Display: ${computed.display}`);
                debugLogs.push(`[INSPECT] - Visibility: ${computed.visibility}`);
            });
            
            const buttons = document.querySelectorAll('[data-view]');
            debugLogs.push('[INSPECT] Boutons trouvés: ' + buttons.length);
            buttons.forEach((btn, i) => {
                debugLogs.push(`[INSPECT] Bouton ${i}: ${btn.dataset.view} - Active: ${btn.classList.contains('active')}`);
            });
            
            updateDebugPanel();
        }
        
        // Init ViewManager avec debug
        document.addEventListener('DOMContentLoaded', () => {
            debugLogs.push('[SYSTEM] DOM loaded, initializing ViewManager...');
            updateDebugPanel();
            
            setTimeout(() => {
                try {
                    window.testViewManager = new ViewManager('.books-container');
                    debugLogs.push('[SYSTEM] ViewManager initialisé: ' + (window.testViewManager ? 'OK' : 'FAILED'));
                    updateDebugPanel();
                    
                    // Auto-inspection après init
                    setTimeout(() => {
                        inspectViews();
                    }, 500);
                    
                } catch (error) {
                    debugLogs.push('[ERROR] ViewManager init failed: ' + error.message);
                    updateDebugPanel();
                }
            }, 100);
        });
        
        // Test automatique des boutons
        function autoTestButtons() {
            debugLogs.push('[TEST] === AUTO TEST DES BOUTONS ===');
            const buttons = ['grid', 'list', 'compact'];
            let index = 0;
            
            const testNext = () => {
                if (index < buttons.length) {
                    const btn = document.querySelector(`[data-view="${buttons[index]}"]`);
                    if (btn) {
                        debugLogs.push(`[TEST] Clique sur bouton: ${buttons[index]}`);
                        btn.click();
                        updateDebugPanel();
                        index++;
                        setTimeout(testNext, 1000);
                    }
                }
            };
            
            testNext();
        }
        
        // Bouton de test automatique
        setTimeout(() => {
            const debugPanel = document.getElementById('debug-panel');
            const autoTestBtn = document.createElement('button');
            autoTestBtn.innerHTML = 'Auto Test';
            autoTestBtn.className = 'btn btn-sm btn-success';
            autoTestBtn.onclick = autoTestButtons;
            debugPanel.appendChild(autoTestBtn);
        }, 1000);
    </script>
</body>
</html>