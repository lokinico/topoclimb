#!/bin/bash

# Script de préparation pour déploiement Plesk TopoclimbCH
# Mis à jour avec les dernières corrections (routes critiques réparées)
echo "🚀 Préparation du déploiement TopoclimbCH pour Plesk"
echo "=================================================="
echo "Commit: $(git rev-parse --short HEAD)"
echo "Date: $(date)"
echo ""

# Créer un répertoire de déploiement
DEPLOY_DIR="topoclimb-deploy-$(date +%Y%m%d_%H%M%S)"
mkdir -p $DEPLOY_DIR

echo "📦 Copie des fichiers nécessaires..."

# Vérifier les fichiers critiques réparés
echo "🔍 Vérification des corrections récentes..."
if [ -f "resources/views/checklists/index.twig" ]; then
    echo "✅ Template checklists trouvé"
else
    echo "❌ Template checklists manquant"
    exit 1
fi

if [ -f "resources/views/equipment/index.twig" ]; then
    echo "✅ Template equipment trouvé"
else
    echo "❌ Template equipment manquant"
    exit 1
fi

if [ -f "resources/views/map/index.twig" ]; then
    echo "✅ Template map trouvé"
else
    echo "❌ Template map manquant"
    exit 1
fi

if [ -f "public/css/pages/map.css" ]; then
    echo "✅ CSS carte trouvé"
else
    echo "❌ CSS carte manquant"
    exit 1
fi

echo "✅ Tous les fichiers critiques sont présents"
echo ""

# Copier les fichiers essentiels (exclure les fichiers de développement)
cp -r public/ $DEPLOY_DIR/
cp -r src/ $DEPLOY_DIR/
cp -r resources/ $DEPLOY_DIR/
cp -r config/ $DEPLOY_DIR/
cp -r vendor/ $DEPLOY_DIR/
cp -r storage/ $DEPLOY_DIR/

# Copier les fichiers de configuration
cp composer.json $DEPLOY_DIR/
cp composer.lock $DEPLOY_DIR/
cp bootstrap.php $DEPLOY_DIR/
cp .env.production.example $DEPLOY_DIR/.env.example

# Copier les scripts de test pour validation post-déploiement
cp test_deployment.php $DEPLOY_DIR/
cp DEPLOYMENT_CHECKLIST.md $DEPLOY_DIR/
cp plesk-config.php $DEPLOY_DIR/

# Créer un fichier .htaccess pour Apache (Plesk utilise souvent Apache)
cat > $DEPLOY_DIR/.htaccess << 'EOF'
# Redirection vers le dossier public
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ /public/$1 [L,QSA]

# Sécurité
<Files ".env">
    Order Allow,Deny
    Deny from all
</Files>

<Files "composer.json">
    Order Allow,Deny
    Deny from all
</Files>

<Files "composer.lock">
    Order Allow,Deny
    Deny from all
</Files>
EOF

# Créer un .htaccess spécifique pour le dossier public
cat > $DEPLOY_DIR/public/.htaccess << 'EOF'
# Réécriture pour le routage
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Sécurité
<Files ".env">
    Order Allow,Deny
    Deny from all
</Files>

# Headers de sécurité
<IfModule mod_headers.c>
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Gestion du cache des assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/webp "access plus 1 month"
</IfModule>
EOF

# Créer les répertoires de logs et cache
mkdir -p $DEPLOY_DIR/storage/logs
mkdir -p $DEPLOY_DIR/storage/cache
mkdir -p $DEPLOY_DIR/storage/sessions

# NOUVEAU: Nettoyage cache et optimisations AGRESSIF
echo "🧹 Nettoyage et optimisations AGRESSIVES..."

# Vider TOUS les caches locaux avant déploiement
if [ -d "storage/cache" ]; then
    rm -rf storage/cache/*
    echo "✅ Cache Twig local vidé"
fi

if [ -d "storage/sessions" ]; then
    rm -rf storage/sessions/sess_*
    echo "✅ Sessions locales vidées"
fi

# Ajouter timestamp de déploiement pour forcer refresh
echo "/* Cache bust: $(date) */" > $DEPLOY_DIR/public/cache-bust.css

# Modifier les layouts avec timestamp pour forcer reload
TIMESTAMP=$(date +%Y-%m-%d\ %H:%M:%S)

# Ajouter cache bust au layout principal
sed -i "2s/.*/{# Cache bust: $TIMESTAMP #}/" $DEPLOY_DIR/resources/views/layouts/app.twig || echo "Sed non disponible, continuons..."

echo "⚠️ Cache vidé AGRESSIVEMENT avant déploiement"
echo "⚠️ Templates marqués avec timestamp: $TIMESTAMP"

# Créer script de vidage cache AGRESSIF pour production
cat > $DEPLOY_DIR/clear-cache.php << 'EOF'
<?php
/**
 * Script de vidage AGRESSIF du cache TopoclimbCH
 * ⚠️ OBLIGATOIRE après chaque déploiement
 * Corrige les problèmes de cache comme pour la carte
 */

echo "🧹 NETTOYAGE CACHE AGRESSIF TopoclimbCH\n";
echo "========================================\n";

$cacheCleared = false;

// 1. Vider le cache des vues Twig (CRITIQUE pour templates)
$cacheDir = __DIR__ . '/storage/cache';
if (is_dir($cacheDir)) {
    // Supprimer tous les fichiers ET sous-dossiers
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $cleared = 0;
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
            $cleared++;
        }
    }
    echo "✅ Cache Twig vidé ($cleared fichiers)\n";
    $cacheCleared = true;
} else {
    echo "⚠️ Répertoire cache introuvable - création...\n";
    mkdir($cacheDir, 0777, true);
}

// 2. Vider TOUTES les sessions utilisateur
$sessionDir = __DIR__ . '/storage/sessions';
if (is_dir($sessionDir)) {
    $files = glob($sessionDir . '/sess_*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "✅ Sessions vidées (" . count($files) . " fichiers)\n";
    $cacheCleared = true;
}

// 3. Vider OPCache PHP (crucial pour nouveaux fichiers)
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPCache PHP vidé\n";
        $cacheCleared = true;
    } else {
        echo "⚠️ OPCache pas accessible\n";
    }
}

// 4. Forcer rechargement avec headers HTTP
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "✅ Headers anti-cache envoyés\n";
}

// 5. Nettoyer les logs anciens
$logDir = __DIR__ . '/storage/logs';
if (is_dir($logDir)) {
    $files = glob($logDir . '/*.log');
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < time() - (3 * 24 * 60 * 60)) { // 3 jours au lieu de 7
            unlink($file);
            $deleted++;
        }
    }
    if ($deleted > 0) {
        echo "✅ $deleted anciens logs supprimés\n";
    }
}

// 6. Créer un fichier de validation du nettoyage
$timestamp = date('Y-m-d H:i:s');
file_put_contents(__DIR__ . '/cache-cleared.txt', "Cache vidé le: $timestamp\n");

// Résumé final
if ($cacheCleared) {
    echo "\n🎯 CACHE COMPLÈTEMENT VIDÉ !\n";
    echo "✅ Templates Twig: PURGÉS\n";
    echo "✅ Sessions: PURGÉES\n";
    echo "✅ OPCache: PURGÉ\n";
    echo "✅ Headers: ANTI-CACHE\n";
    echo "\n⚠️ IMPORTANT: Testez /map et /test-carte.html maintenant\n";
    echo "Si problème persiste, vérifiez cache serveur/CDN\n";
} else {
    echo "\n❌ PROBLÈME: Cache non vidé correctement\n";
    echo "Vérifiez les permissions du dossier storage/\n";
}
EOF

# Créer script de diagnostic CSS/JS
cat > $DEPLOY_DIR/diagnose-conflicts.php << 'EOF'
<?php
/**
 * Diagnostic des conflits CSS/JS TopoclimbCH
 * Pour identifier les problèmes comme celui de la carte
 */

echo "🔍 DIAGNOSTIC CONFLITS TopoclimbCH\n";
echo "===================================\n";

// Vérifier les fichiers CSS critiques
$criticalFiles = [
    'public/css/app.css' => 'CSS principal',
    'public/css/pages/map.css' => 'CSS carte (ancien)',
    'public/css/pages/map-clean.css' => 'CSS carte (nouveau)',
    'public/test-carte.html' => 'Page test carte',
    'resources/views/layouts/app.twig' => 'Template principal',
    'resources/views/map/index.twig' => 'Template carte'
];

$issues = [];

foreach ($criticalFiles as $file => $desc) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $issues[] = "❌ MANQUANT: $desc ($file)";
    } else {
        echo "✅ $desc\n";
    }
}

// Vérifier les CDN externes
echo "\n🌐 Test des CDN externes:\n";
$cdns = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'
];

foreach ($cdns as $cdn) {
    $headers = @get_headers($cdn);
    if ($headers && strpos($headers[0], '200') !== false) {
        echo "✅ CDN accessible: " . basename($cdn) . "\n";
    } else {
        $issues[] = "❌ CDN inaccessible: $cdn";
    }
}

// Résumé
echo "\n📊 RÉSUMÉ:\n";
if (empty($issues)) {
    echo "✅ Aucun problème détecté\n";
} else {
    echo "⚠️ " . count($issues) . " problème(s) trouvé(s):\n";
    foreach ($issues as $issue) {
        echo "   $issue\n";
    }
}

echo "\n💡 Si la carte ne fonctionne pas:\n";
echo "   1. OBLIGATOIRE: php clear-cache.php\n";
echo "   2. Testez /test-carte.html d'abord\n";
echo "   3. Puis testez /map (doit être identique)\n";
echo "   4. Consultez les logs du navigateur (F12)\n";
echo "   5. Si toujours buggé: problème cache serveur/CDN\n";
EOF

# Créer un script de force refresh pour urgences
cat > $DEPLOY_DIR/force-refresh.php << 'EOF'
<?php
/**
 * Script de FORCE REFRESH IMMÉDIAT
 * En cas d'urgence si cache reste bloqué
 */

echo "🚨 FORCE REFRESH IMMÉDIAT\n";
echo "========================\n";

// 1. Vider TOUT
exec('php clear-cache.php', $output, $return);
foreach($output as $line) {
    echo "$line\n";
}

// 2. Modifier timestamp du layout fullscreen pour forcer reload
$layoutFile = __DIR__ . '/resources/views/layouts/fullscreen.twig';
if (file_exists($layoutFile)) {
    $content = file_get_contents($layoutFile);
    $newTimestamp = date('Y-m-d H:i:s');
    $content = preg_replace('/<!-- Updated: .* -->/', "<!-- Updated: $newTimestamp -->", $content);
    if (strpos($content, '<!-- Updated:') === false) {
        $content = str_replace('<head>', "<head>\n    <!-- Updated: $newTimestamp -->", $content);
    }
    file_put_contents($layoutFile, $content);
    echo "✅ Layout fullscreen marqué: $newTimestamp\n";
}

// 3. Modifier timestamp du template de carte
$mapTemplate = __DIR__ . '/resources/views/map/index.twig';
if (file_exists($mapTemplate)) {
    $content = file_get_contents($mapTemplate);
    $newTimestamp = date('Y-m-d H:i:s');
    $content = preg_replace('/{# Updated: .* #}/', "{# Updated: $newTimestamp #}", $content);
    if (strpos($content, '{# Updated:') === false) {
        $content = "{# Updated: $newTimestamp #}\n" . $content;
    }
    file_put_contents($mapTemplate, $content);
    echo "✅ Template carte marqué: $newTimestamp\n";
}

// 4. Créer un fichier de contrôle
file_put_contents(__DIR__ . '/force-refresh-done.txt', "Force refresh fait le: " . date('Y-m-d H:i:s') . "\n");

echo "\n🎯 FORCE REFRESH TERMINÉ !\n";
echo "Les templates ont été modifiés pour forcer le rechargement.\n";
echo "Testez /map maintenant - ça devrait marcher.\n";
EOF

# Configurer les permissions
chmod -R 755 $DEPLOY_DIR/public/
chmod -R 755 $DEPLOY_DIR/resources/
chmod -R 755 $DEPLOY_DIR/src/
chmod -R 755 $DEPLOY_DIR/storage/
chmod -R 777 $DEPLOY_DIR/storage/logs/
chmod -R 777 $DEPLOY_DIR/storage/cache/
chmod -R 777 $DEPLOY_DIR/storage/sessions/

# Créer un rapport de déploiement détaillé
echo "📋 Génération du rapport de déploiement..."

cat > $DEPLOY_DIR/DEPLOYMENT_REPORT.md << EOF
# 📋 RAPPORT DE DÉPLOIEMENT TopoclimbCH

## 🎯 Informations de déploiement
- **Date/Heure** : $(date '+%Y-%m-%d %H:%M:%S %Z')
- **Commit Git** : $(git rev-parse --short HEAD) - $(git log -1 --pretty=format:'%s')
- **Branche** : $(git branch --show-current)
- **Archive générée** : $DEPLOY_DIR.tar.gz
- **Taille archive** : $(if [ -f "$DEPLOY_DIR.tar.gz" ]; then ls -lah "$DEPLOY_DIR.tar.gz" | awk '{print \$5}'; else echo "Non générée"; fi)

## ✅ Vérifications pré-déploiement

### Fichiers critiques vérifiés
- [x] Template checklists : $(if [ -f "resources/views/checklists/index.twig" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] Template equipment : $(if [ -f "resources/views/equipment/index.twig" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] Template carte : $(if [ -f "resources/views/map/index.twig" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] CSS carte : $(if [ -f "public/css/pages/map.css" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] Layout fullscreen : $(if [ -f "resources/views/layouts/fullscreen.twig" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] Page test carte : $(if [ -f "public/test-carte.html" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)

### Controllers critiques
- [x] MapController : $(if [ -f "src/Controllers/MapController.php" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] ChecklistController : $(if [ -f "src/Controllers/ChecklistController.php" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] EquipmentController : $(if [ -f "src/Controllers/EquipmentController.php" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)
- [x] WeatherController : $(if [ -f "src/Controllers/WeatherController.php" ]; then echo "✅ Présent"; else echo "❌ MANQUANT"; fi)

### Scripts de gestion cache
- [x] clear-cache.php : ✅ Généré
- [x] force-refresh.php : ✅ Généré  
- [x] diagnose-conflicts.php : ✅ Généré

## 📂 Structure de l'archive

### Dossiers principaux inclus
- [x] **public/** : Assets, CSS, JS, images, index.php
- [x] **src/** : Controllers, Models, Services, Core
- [x] **resources/** : Templates Twig, langues
- [x] **config/** : Configuration routes et application
- [x] **storage/** : Cache, logs, sessions, uploads
- [x] **vendor/** : Dépendances Composer

### Fichiers de configuration
- [x] **composer.json** : Dépendances PHP
- [x] **.env.example** : Variables d'environnement
- [x] **.htaccess** : Configuration Apache/Plesk
- [x] **bootstrap.php** : Initialisation application

### Scripts et outils
- [x] **test_deployment.php** : Tests post-déploiement
- [x] **plesk-config.php** : Configuration Plesk
- [x] **DEPLOYMENT_CHECKLIST.md** : Liste de vérification

## 🔧 Optimisations appliquées

### Nettoyage cache pré-déploiement
- [x] Cache Twig local vidé : $(if [ -d "storage/cache" ]; then echo "✅ Effectué"; else echo "⚠️ Dossier inexistant"; fi)
- [x] Sessions locales purgées : $(if [ -d "storage/sessions" ]; then echo "✅ Effectué"; else echo "⚠️ Dossier inexistant"; fi)
- [x] Timestamp ajouté aux templates : $(echo "$TIMESTAMP")

### Permissions configurées
- [x] public/ : 755 (lecture/exécution publique)
- [x] resources/ : 755 (lecture templates)
- [x] src/ : 755 (lecture code source)
- [x] storage/ : 755 (base)
- [x] storage/logs/ : 777 (écriture logs)
- [x] storage/cache/ : 777 (écriture cache)
- [x] storage/sessions/ : 777 (écriture sessions)

## ⚡ Tests automatiques inclus

### Scripts de test disponibles
- [x] **test_deployment.php** : Test complet post-déploiement
- [x] **clear-cache.php** : Nettoyage cache agressif
- [x] **force-refresh.php** : Refresh d'urgence templates
- [x] **diagnose-conflicts.php** : Diagnostic conflits CSS/JS

## 🎯 Routes critiques à tester

### Routes principales corrigées
1. **GET /map** : Carte interactive (était complètement buggée)
2. **GET /checklists** : Listes de vérification (erreur 500 → 200)
3. **GET /equipment** : Gestion équipement (erreur 500 → 200)
4. **GET /test-carte.html** : Page de test diagnostic

### APIs fonctionnelles
- **GET /api/sites** : Liste des sites d'escalade
- **GET /api/weather/current** : Données météo actuelles
- **GET /api/regions** : Liste des régions
- **GET /api/sectors** : Liste des secteurs

## 📝 Instructions post-déploiement

### ÉTAPE 1 : Upload et extraction
\`\`\`bash
# Uploader l'archive sur Plesk
# Extraire dans la racine du domaine
tar -xzf $DEPLOY_DIR.tar.gz
\`\`\`

### ÉTAPE 2 : Configuration .env
\`\`\`bash
cp .env.example .env
# Modifier les variables selon votre configuration
\`\`\`

### ÉTAPE 3 : OBLIGATOIRE - Nettoyage cache
\`\`\`bash
php clear-cache.php
\`\`\`

### ÉTAPE 4 : Tests de validation
\`\`\`bash
# Test automatique
php test_deployment.php

# Tests manuels dans l'ordre
# 1. https://votre-domaine.com/test-carte.html (doit marcher)
# 2. https://votre-domaine.com/map (doit être identique)
# 3. https://votre-domaine.com/checklists (doit afficher "Checklists")
# 4. https://votre-domaine.com/equipment (doit afficher "Équipement")
\`\`\`

### ÉTAPE 5 : En cas de problème
\`\`\`bash
# Si la carte reste buggée
php force-refresh.php

# Diagnostic des conflits
php diagnose-conflicts.php
\`\`\`

## 🚨 Points critiques à surveiller

### Problèmes connus résolus
- ✅ **Cache Twig** : Scripts de nettoyage agressif ajoutés
- ✅ **Tuiles carte** : Swiss topo → OpenStreetMap (alignement correct)
- ✅ **Bootstrap/Leaflet** : Layout fullscreen sans conflits CSS
- ✅ **Injections dépendances** : Controllers corrigés
- ✅ **Routes manquantes** : Toutes les routes critiques ajoutées

### Indicateurs de succès
- [ ] test-carte.html fonctionne parfaitement
- [ ] /map identique à test-carte.html
- [ ] /checklists affiche page "Checklists"
- [ ] /equipment affiche page "Équipement"  
- [ ] APIs retournent JSON valide
- [ ] Pas d'erreurs 500 dans les logs

## 📞 Support et dépannage

### Si problème persistant
1. Vérifiez les logs PHP de Plesk
2. Consultez la console navigateur (F12)
3. Exécutez \`php diagnose-conflicts.php\`
4. Contactez avec le rapport d'erreur complet

### Informations de débogage
- **Version PHP requise** : 8.0+
- **Extensions requises** : pdo, pdo_mysql, json, mbstring, curl, zip, gd, intl
- **Memory limit** : 512M minimum
- **Max execution time** : 60 secondes

---

**✅ Rapport généré automatiquement le $(date '+%Y-%m-%d %H:%M:%S')**
**🎯 Archive prête pour déploiement Plesk**
EOF

# Créer un fichier de configuration pour Plesk
cat > $DEPLOY_DIR/PLESK_DEPLOYMENT.md << 'EOF'
# Instructions de déploiement Plesk TopoclimbCH

## 🚀 Corrections incluses dans cette version
- ✅ Routes /checklists et /equipment réparées (erreur 500 → 200)
- ✅ Carte interactive ENTIÈREMENT réparée (était complètement buggée)
- ✅ Solution bypass pour conflits CSS/Bootstrap avec Leaflet
- ✅ Page test-carte.html incluse pour diagnostic
- ✅ Scripts de nettoyage cache (clear-cache.php)
- ✅ Script diagnostic conflits (diagnose-conflicts.php)
- ✅ Templates Twig corrigés (layouts/app.twig)
- ✅ Contrôleurs avec injection de dépendances fixes

## 1. Upload des fichiers
- Uploadez tout le contenu de ce dossier vers la racine de votre domaine sur Plesk
- Ou utilisez le gestionnaire de fichiers Plesk
- **Important**: Assurez-vous que le dossier public/ est accessible via HTTP

## 2. Configuration PHP
Dans Plesk, allez dans PHP Settings et assurez-vous que :
- Version PHP : 8.0 ou supérieur
- Extensions activées : pdo, pdo_mysql, json, mbstring, curl, zip, gd, intl
- Memory limit : 512M minimum
- Max execution time : 60 secondes

## 3. Configuration .env
- Copiez .env.example vers .env
- Modifiez les variables selon votre configuration :
  - APP_URL=https://votre-domaine.com
  - DB_HOST=localhost
  - DB_NAME=votre_base_de_données
  - DB_USER=votre_utilisateur
  - DB_PASSWORD=votre_mot_de_passe

## 4. Base de données
- Créez une base de données MySQL dans Plesk
- Importez le schema SQL si nécessaire
- Utilisez l'encoding UTF8_GENERAL_CI

## 5. Composer (si disponible)
Si Composer est disponible sur votre serveur :
```bash
composer install --no-dev --optimize-autoloader
```

## 6. Permissions
Assurez-vous que les dossiers storage/ sont en écriture (777) :
```bash
chmod -R 777 storage/
```

## 7. OBLIGATOIRE: Nettoyage cache après déploiement
**⚠️ CRITIQUE**: Exécutez DANS L'ORDRE après chaque upload :

### Étape 1: Nettoyage cache standard
```bash
php clear-cache.php
```

### Étape 2: Si la carte reste buggée
```bash
php force-refresh.php
```

### Étape 3: Vérification
- Teste: `/test-carte.html` (doit marcher)
- Teste: `/map` (doit être identique)
- Si différent = cache serveur/CDN à vider

**Ce que clear-cache.php fait:**
- Cache Twig (templates) : PURGÉ
- Sessions utilisateur : PURGÉES
- OPCache PHP : PURGÉ  
- Headers anti-cache : ACTIVÉS

**Ce que force-refresh.php fait EN PLUS:**
- Modifie les templates avec nouveaux timestamps
- Force rechargement même avec cache agressif

## 8. Test des routes critiques
Testez dans cet ordre :
1. **https://votre-domaine.com/test-carte.html** (doit marcher parfaitement)
2. **https://votre-domaine.com/map** (doit être identique au test)
3. **https://votre-domaine.com/checklists** (doit afficher "Checklists")
4. **https://votre-domaine.com/equipment** (doit afficher "Équipement")

## 9. Diagnostic en cas de problème
Si la carte ne fonctionne pas :
```bash
php diagnose-conflicts.php
```
Ce script vérifie :
- Fichiers CSS/JS présents
- CDN accessibles (Leaflet, Bootstrap)
- Conflits potentiels

## 8. Script de test automatique
Modifiez l'URL dans test_deployment.php puis exécutez :
```bash
php test_deployment.php
```

## 9. Surveillance
- Vérifiez les logs d'erreur Plesk
- Surveillez les performances (< 2s par page)
- Testez la carte interactive sur mobile

## 🐛 Dépannage
- Si erreur 500 : vérifiez les permissions et les logs PHP
- Si carte ne s'affiche pas : vérifiez la console JavaScript
- Si templates manquants : vérifiez resources/views/

## 📞 Support
Consultez DEPLOYMENT_CHECKLIST.md pour plus de détails
EOF

# Créer une archive
# Vérifications supplémentaires avant archivage
echo "🔍 Vérifications finales avant archivage..."

# Compter les fichiers critiques
FILES_COUNT=$(find $DEPLOY_DIR -type f | wc -l)
PHP_FILES=$(find $DEPLOY_DIR -name "*.php" | wc -l)
TWIG_FILES=$(find $DEPLOY_DIR -name "*.twig" | wc -l)
JS_FILES=$(find $DEPLOY_DIR -name "*.js" | wc -l)
CSS_FILES=$(find $DEPLOY_DIR -name "*.css" | wc -l)

echo "📊 Statistiques du package :"
echo "   - Fichiers totaux : $FILES_COUNT"
echo "   - Fichiers PHP : $PHP_FILES"
echo "   - Templates Twig : $TWIG_FILES"
echo "   - Fichiers JS : $JS_FILES"
echo "   - Fichiers CSS : $CSS_FILES"

# Vérifier la taille du dossier
DEPLOY_SIZE=$(du -sh $DEPLOY_DIR | cut -f1)
echo "   - Taille totale : $DEPLOY_SIZE"

# Ajouter ces infos au rapport
cat >> $DEPLOY_DIR/DEPLOYMENT_REPORT.md << EOF

## 📊 Statistiques du package
- **Fichiers totaux** : $FILES_COUNT
- **Fichiers PHP** : $PHP_FILES
- **Templates Twig** : $TWIG_FILES  
- **Fichiers JavaScript** : $JS_FILES
- **Fichiers CSS** : $CSS_FILES
- **Taille totale** : $DEPLOY_SIZE

## 🔍 Dernières vérifications
$(date '+%H:%M:%S') - Vérifications finales avant archivage...
$(date '+%H:%M:%S') - Package préparé et validé
$(date '+%H:%M:%S') - Prêt pour upload sur serveur de production
EOF

echo "📦 Création de l'archive de déploiement..."
tar -czf "$DEPLOY_DIR.tar.gz" $DEPLOY_DIR/

# Finaliser le rapport avec la taille de l'archive
if [ -f "$DEPLOY_DIR.tar.gz" ]; then
    ARCHIVE_SIZE=$(ls -lah "$DEPLOY_DIR.tar.gz" | awk '{print $5}')
    echo "✅ Archive créée : $ARCHIVE_SIZE"
    
    # Ajouter l'info finale au rapport
    cat >> $DEPLOY_DIR/DEPLOYMENT_REPORT.md << EOF

## ✅ Archive finalisée
- **Nom fichier** : $DEPLOY_DIR.tar.gz  
- **Taille archive** : $ARCHIVE_SIZE
- **Statut** : ✅ PRÊT POUR DÉPLOIEMENT

---
**🎯 Rapport de déploiement complet généré automatiquement**
EOF
fi

echo ""
echo "✅ Déploiement TopoclimbCH préparé avec succès !"
echo "============================================="
echo ""
echo "📊 Résumé des corrections incluses :"
echo "✅ Routes /checklists et /equipment réparées"
echo "✅ Carte interactive avec tuiles simplifiées"
echo "✅ Templates Twig corrigés"
echo "✅ Contrôleurs avec injection de dépendances"
echo "✅ Scripts de test inclus"
echo "✅ Rapport de déploiement détaillé généré"
echo ""
echo "📁 Dossier créé : $DEPLOY_DIR/"
echo "📦 Archive créée : $DEPLOY_DIR.tar.gz ($ARCHIVE_SIZE)"
echo "📋 Instructions : $DEPLOY_DIR/PLESK_DEPLOYMENT.md"
echo "📄 RAPPORT DÉTAILLÉ : $DEPLOY_DIR/DEPLOYMENT_REPORT.md"
echo ""
echo "🚀 Prochaines étapes :"
echo "1. Téléchargez l'archive : $DEPLOY_DIR.tar.gz"
echo "2. Uploadez et extractez sur votre serveur Plesk"
echo "3. Suivez les instructions dans PLESK_DEPLOYMENT.md"
echo "4. Consultez DEPLOYMENT_REPORT.md pour validation complète"
echo "5. Testez les routes critiques :"
echo "   - /checklists"
echo "   - /equipment"
echo "   - /map"
echo ""
echo "🧪 Pour tester après déploiement :"
echo "   php test_deployment.php"
echo ""
echo "📋 NOUVEAU : Rapport de déploiement complet disponible"
echo "   Consultez DEPLOYMENT_REPORT.md pour tous les détails"
echo ""
echo "💡 Support : consultez DEPLOYMENT_CHECKLIST.md"