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
- ✅ Carte interactive avec tuiles simplifiées (OSM par défaut)
- ✅ Templates Twig corrigés (layouts/app.twig)
- ✅ Contrôleurs avec injection de dépendances fixes
- ✅ Gestion d'erreurs améliorée

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

## 7. Test des routes critiques
Après déploiement, testez ces routes :
- https://votre-domaine.com/checklists (doit afficher "Checklists de sécurité")
- https://votre-domaine.com/equipment (doit afficher "Types d'équipement")
- https://votre-domaine.com/map (doit afficher la carte interactive)

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