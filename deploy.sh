#!/bin/bash

# =======================================================
# 🚀 Script de déploiement TopoclimbCH
# =======================================================

set -e  # Arrêter sur la première erreur

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction de log
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

# =======================================================
# 🎯 DÉBUT DU DÉPLOIEMENT
# =======================================================

log "========================================="
log "🚀 DÉPLOIEMENT TOPOCLIMBCH"
log "========================================="

log "📁 Répertoire de travail : $(pwd)"
log "👤 Utilisateur : $(whoami)"
log "🐘 Version PHP : $(php --version | head -n1)"

# =======================================================
# 🧹 NETTOYAGE
# =======================================================

log "🧹 Nettoyage des anciennes installations..."
if [ -d "vendor" ]; then
    rm -rf vendor/
    success "Dossier vendor supprimé"
fi

if [ -f "composer.lock" ]; then
    rm -f composer.lock
    success "Fichier composer.lock supprimé"
fi

# =======================================================
# 🔧 DÉTECTION ET CONFIGURATION DE COMPOSER
# =======================================================

log "🔧 Détection de Composer..."

find_composer() {
    # Test différents chemins possibles
    local paths=(
        "/usr/local/bin/composer"
        "/usr/bin/composer"
        "/opt/plesk/php/8.3/bin/php /usr/local/bin/composer"
        "/opt/plesk/php/8.2/bin/php /usr/local/bin/composer"
        "/opt/plesk/php/8.1/bin/php /usr/local/bin/composer"
        "composer"
    )
    
    for path in "${paths[@]}"; do
        if eval "$path --version" >/dev/null 2>&1; then
            echo "$path"
            return 0
        fi
    done
    
    return 1
}

# Recherche de Composer
if COMPOSER_CMD=$(find_composer); then
    success "Composer trouvé : $COMPOSER_CMD"
else
    warning "Composer non trouvé, téléchargement..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --filename=composer
    COMPOSER_CMD="php /tmp/composer"
    
    if [ -f "/tmp/composer" ]; then
        success "Composer téléchargé avec succès"
    else
        error "Échec du téléchargement de Composer"
        exit 1
    fi
fi

# =======================================================
# 📦 INSTALLATION DES DÉPENDANCES
# =======================================================

log "📦 Installation des dépendances PHP..."

# Vérification de composer.json
if [ ! -f "composer.json" ]; then
    error "Fichier composer.json manquant"
    exit 1
fi

# Clear cache Composer
log "🧹 Nettoyage du cache Composer..."
$COMPOSER_CMD clear-cache || warning "Impossible de nettoyer le cache Composer"

# Installation des dépendances
log "⬇️ Installation des packages..."
if $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --verbose; then
    success "Dépendances installées avec succès"
else
    error "Échec de l'installation des dépendances"
    exit 1
fi

# =======================================================
# 🔍 VÉRIFICATIONS
# =======================================================

log "🔍 Vérification de l'installation..."

# Vérifier autoloader
if [ -f "vendor/autoload.php" ]; then
    success "Autoloader Composer créé"
    log "📊 Taille du vendor : $(du -sh vendor | cut -f1)"
else
    error "Autoloader manquant"
    if [ -d "vendor" ]; then
        log "📋 Contenu du vendor :"
        ls -la vendor/ | head -10
    fi
    exit 1
fi

# Vérifier les packages critiques
log "🔍 Vérification des packages critiques..."
critical_packages=(
    "vendor/symfony/http-foundation"
    "vendor/symfony/dependency-injection"
    "vendor/symfony/config"
    "vendor/twig/twig"
    "vendor/monolog/monolog"
)

for package in "${critical_packages[@]}"; do
    if [ -d "$package" ]; then
        success "$(basename $package) installé"
    else
        error "Package critique manquant : $(basename $package)"
    fi
done

# =======================================================
# 📁 CRÉATION DES RÉPERTOIRES
# =======================================================

log "📁 Création des répertoires requis..."

directories=(
    "storage/cache"
    "storage/logs"
    "storage/uploads"
    "public/uploads"
    "storage/sessions"
    "storage/framework/cache"
    "storage/framework/views"
)

for dir in "${directories[@]}"; do
    if mkdir -p "$dir"; then
        success "Répertoire créé : $dir"
    else
        warning "Impossible de créer : $dir"
    fi
done

# =======================================================
# 🧹 NETTOYAGE DU CACHE APPLICATIF
# =======================================================

log "🧹 Nettoyage du cache applicatif..."

# Nettoyer les caches
if [ -d "storage/cache" ]; then
    rm -rf storage/cache/*
    success "Cache storage nettoyé"
fi

# Nettoyer spécifiquement le cache du container Symfony
log "🧹 Nettoyage du cache container Symfony..."
find . -name "*CachedContainer*" -delete 2>/dev/null || true
find . -name "*cached_container*" -delete 2>/dev/null || true
rm -rf storage/framework/cache/* 2>/dev/null || true
success "Cache container nettoyé"

# Exécuter les scripts de nettoyage de l'app
if [ -f "clear_cache.php" ]; then
    if php clear_cache.php; then
        success "Script clear_cache.php exécuté"
    else
        warning "Erreur dans clear_cache.php"
    fi
else
    warning "Script clear_cache.php non trouvé"
fi

# Exécuter le nettoyage spécifique du container
if [ -f "clear_container_cache.php" ]; then
    if php clear_container_cache.php; then
        success "Cache container Symfony nettoyé"
    else
        warning "Erreur dans clear_container_cache.php"
    fi
fi

# =======================================================
# 🔐 CONFIGURATION DES PERMISSIONS
# =======================================================

log "🔐 Configuration des permissions..."

# Permissions des répertoires de stockage
chmod -R 755 storage/ 2>/dev/null && success "Permissions storage configurées" || warning "Impossible de configurer storage"
chmod -R 755 public/uploads/ 2>/dev/null && success "Permissions uploads configurées" || warning "Impossible de configurer uploads"

# Permissions du fichier .env
if [ -f ".env" ]; then
    chmod 644 .env && success "Permissions .env configurées"
else
    warning "Fichier .env non trouvé"
fi

# =======================================================
# 🔍 VÉRIFICATIONS FINALES
# =======================================================

log "🔍 Vérifications finales..."

# Fichiers critiques
critical_files=(
    "public/index.php"
    "vendor/autoload.php"
    "composer.json"
)

for file in "${critical_files[@]}"; do
    if [ -f "$file" ]; then
        success "$(basename $file) présent"
    else
        error "Fichier critique manquant : $file"
    fi
done

# Vérification .env
if [ -f ".env" ]; then
    success "Configuration .env présente"
    
    # Vérifier les variables critiques
    if grep -q "APP_KEY=" .env && grep -q "DB_DATABASE=" .env; then
        success "Variables .env configurées"
    else
        warning "Variables .env incomplètes"
    fi
else
    warning "Fichier .env manquant - créez-le avec vos paramètres"
fi

# Test autoloader
log "🧪 Test de l'autoloader..."
if php -r "require 'vendor/autoload.php'; echo 'Autoloader OK';" >/dev/null 2>&1; then
    success "Autoloader fonctionnel"
else
    error "Problème avec l'autoloader"
fi

# =======================================================
# 📊 RAPPORT FINAL
# =======================================================

log "📊 Rapport final :"
log "   • Répertoire : $(pwd)"
log "   • PHP : $(php --version | head -n1)"
log "   • Composer : $COMPOSER_CMD"
log "   • Packages : $(ls vendor/ | wc -l) installés"
log "   • Taille vendor : $(du -sh vendor | cut -f1)"

# =======================================================
# ✅ FIN DU DÉPLOIEMENT
# =======================================================

log "========================================="
success "🎉 DÉPLOIEMENT TERMINÉ AVEC SUCCÈS"
log "========================================="

log "🚀 Votre application TopoclimbCH est prête !"
log "💡 N'oubliez pas de :"
log "   • Vérifier votre fichier .env"
log "   • Tester la connexion à la base de données"
log "   • Configurer le Document Root vers /public/"

exit 0