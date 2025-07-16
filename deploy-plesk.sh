#!/bin/bash

# Script de préparation pour déploiement Plesk
echo "🚀 Préparation du déploiement pour Plesk"
echo "======================================"

# Créer un répertoire de déploiement
DEPLOY_DIR="topoclimb-deploy-$(date +%Y%m%d_%H%M%S)"
mkdir -p $DEPLOY_DIR

echo "📦 Copie des fichiers nécessaires..."

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
# Instructions de déploiement Plesk

## 1. Upload des fichiers
- Uploadez tout le contenu de ce dossier vers la racine de votre domaine sur Plesk
- Ou utilisez le gestionnaire de fichiers Plesk

## 2. Configuration PHP
Dans Plesk, allez dans PHP Settings et assurez-vous que :
- Version PHP : 8.0 ou supérieur
- Extensions activées : pdo, pdo_mysql, json, mbstring, curl, zip, gd

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

## 5. Composer (si disponible)
Si Composer est disponible sur votre serveur :
```bash
composer install --no-dev --optimize-autoloader
```

## 6. Permissions
Assurez-vous que les dossiers storage/ sont en écriture (777)

## 7. Test
Visitez votre site : https://votre-domaine.com
EOF

# Créer une archive
echo "📦 Création de l'archive de déploiement..."
tar -czf "$DEPLOY_DIR.tar.gz" $DEPLOY_DIR/

echo ""
echo "✅ Déploiement préparé avec succès !"
echo "================================="
echo ""
echo "📁 Dossier créé : $DEPLOY_DIR/"
echo "📦 Archive créée : $DEPLOY_DIR.tar.gz"
echo ""
echo "🚀 Prochaines étapes :"
echo "1. Téléchargez l'archive : $DEPLOY_DIR.tar.gz"
echo "2. Uploadez et extractez sur votre serveur Plesk"
echo "3. Suivez les instructions dans PLESK_DEPLOYMENT.md"
echo ""
echo "💡 Ou utilisez directement le dossier : $DEPLOY_DIR/"