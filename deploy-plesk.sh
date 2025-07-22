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

# NOUVEAU: Nettoyage cache et optimisations
echo "🧹 Nettoyage et optimisations..."

# Vider le cache local avant déploiement
if [ -d "storage/cache" ]; then
    rm -rf storage/cache/*
    echo "✅ Cache local vidé"
fi

# Créer script de vidage cache pour production
cat > $DEPLOY_DIR/clear-cache.php << 'EOF'
<?php
/**
 * Script de vidage du cache TopoclimbCH
 * À exécuter après chaque déploiement
 */

echo "🧹 NETTOYAGE CACHE TopoclimbCH\n";
echo "================================\n";

// Vider le cache des vues Twig
$cacheDir = __DIR__ . '/storage/cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "✅ Cache Twig vidé\n";
} else {
    echo "ℹ️ Répertoire cache introuvable\n";
}

// Vider le cache des sessions
$sessionDir = __DIR__ . '/storage/sessions';
if (is_dir($sessionDir)) {
    $files = glob($sessionDir . '/sess_*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "✅ Sessions vidées\n";
}

// Vider les logs anciens (> 7 jours)
$logDir = __DIR__ . '/storage/logs';
if (is_dir($logDir)) {
    $files = glob($logDir . '/*.log');
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < time() - (7 * 24 * 60 * 60)) {
            unlink($file);
            $deleted++;
        }
    }
    if ($deleted > 0) {
        echo "✅ $deleted anciens logs supprimés\n";
    }
}

// Vider le cache navigateur (headers)
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPCache PHP vidé\n";
}

echo "\n🎯 CACHE COMPLÈTEMENT VIDÉ !\n";
echo "Testez maintenant votre site.\n";
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
echo "   1. Exécutez: php clear-cache.php\n";
echo "   2. Testez /test-carte.html d'abord\n";
echo "   3. Puis testez /map\n";
echo "   4. Consultez les logs du navigateur (F12)\n";
EOF

# Configurer les permissions
chmod -R 755 $DEPLOY_DIR/public/
chmod -R 755 $DEPLOY_DIR/resources/
chmod -R 755 $DEPLOY_DIR/src/
chmod -R 755 $DEPLOY_DIR/storage/
chmod -R 777 $DEPLOY_DIR/storage/logs/
chmod -R 777 $DEPLOY_DIR/storage/cache/
chmod -R 777 $DEPLOY_DIR/storage/sessions/

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
**⚠️ IMPORTANT**: Exécutez TOUJOURS après chaque upload :
```bash
php clear-cache.php
```
Ceci vide :
- Cache Twig (templates)
- Sessions utilisateur  
- OPCache PHP
- Anciens logs

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
echo "📦 Création de l'archive de déploiement..."
tar -czf "$DEPLOY_DIR.tar.gz" $DEPLOY_DIR/

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
echo ""
echo "📁 Dossier créé : $DEPLOY_DIR/"
echo "📦 Archive créée : $DEPLOY_DIR.tar.gz"
echo "📋 Instructions : $DEPLOY_DIR/PLESK_DEPLOYMENT.md"
echo ""
echo "🚀 Prochaines étapes :"
echo "1. Téléchargez l'archive : $DEPLOY_DIR.tar.gz"
echo "2. Uploadez et extractez sur votre serveur Plesk"
echo "3. Suivez les instructions dans PLESK_DEPLOYMENT.md"
echo "4. Testez les routes critiques :"
echo "   - /checklists"
echo "   - /equipment"
echo "   - /map"
echo ""
echo "🧪 Pour tester après déploiement :"
echo "   php test_deployment.php"
echo ""
echo "💡 Support : consultez DEPLOYMENT_CHECKLIST.md"