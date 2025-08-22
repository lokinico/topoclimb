#\!/bin/bash

# Script de déploiement TopoclimbCH
# Usage: ./deploy.sh [environment]

set -e  # Exit on error

# Configuration
ENVIRONMENT=${1:-production}
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
DATE=$(date +%Y%m%d_%H%M%S)

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Banner
echo -e "${BLUE}"
echo "======================================"
echo "🚀 DÉPLOIEMENT TOPOCLIMBCH"
echo "======================================"
echo -e "${NC}"
echo "Environnement: $ENVIRONMENT"
echo "Date: $(date)"
echo "Commit: $(git rev-parse --short HEAD)"
echo ""

# 1. Vérifications pré-déploiement
log_info "1. Vérifications pré-déploiement..."

# Vérifier que nous sommes dans le bon répertoire
if [ \! -f "composer.json" ]; then
    log_error "composer.json non trouvé. Êtes-vous dans le bon répertoire?"
    exit 1
fi

# Vérifier le statut git
if [ -n "$(git status --porcelain)" ]; then
    log_warning "Il y a des changements non committés:"
    git status --short
    read -p "Continuer? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Déploiement annulé."
        exit 1
    fi
fi

# 2. Tests locaux
log_info "2. Exécution des tests locaux..."
if php test_local.php; then
    log_success "Tests locaux passés avec succès"
else
    log_error "Tests locaux échoués"
    exit 1
fi

# 3. Backup (si en production)
if [ "$ENVIRONMENT" = "production" ]; then
    log_info "3. Création d'un backup..."
    BACKUP_DIR="backups/backup_$DATE"
    mkdir -p $BACKUP_DIR
    
    # Backup des fichiers critiques
    cp -r resources/views $BACKUP_DIR/
    cp -r public/css $BACKUP_DIR/
    cp -r src/Controllers $BACKUP_DIR/
    
    log_success "Backup créé dans $BACKUP_DIR"
fi

# 4. Mise à jour des dépendances
log_info "4. Mise à jour des dépendances..."
if [ "$ENVIRONMENT" = "production" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    composer install --optimize-autoloader --no-interaction
fi

# 5. Optimisations
log_info "5. Optimisations..."
composer dump-autoload --optimize

# 6. Permissions
log_info "6. Configuration des permissions..."
chmod -R 755 public/
chmod -R 755 resources/views/
chmod -R 644 public/css/

# 7. Tests post-déploiement
log_info "7. Tests post-déploiement..."
if [ "$ENVIRONMENT" = "production" ]; then
    log_warning "Exécutez manuellement: php test_deployment.php"
    log_warning "N'oubliez pas de modifier BASE_URL dans test_deployment.php"
else
    if php test_local.php; then
        log_success "Tests post-déploiement passés"
    else
        log_warning "Tests post-déploiement échoués"
    fi
fi

# 8. Résumé
echo ""
log_success "🎉 Déploiement terminé avec succès\!"
echo ""
echo "Résumé:"
echo "- Environnement: $ENVIRONMENT"
echo "- Commit: $(git rev-parse --short HEAD) - $(git log -1 --pretty=%s)"
echo "- Date: $(date)"
echo ""

# 9. Prochaines étapes
log_info "Prochaines étapes:"
echo "1. Vérifier les routes critiques manuellement:"
echo "   - /checklists"
echo "   - /equipment"
echo "   - /map"
echo ""
echo "2. Tester la fonctionnalité de la carte:"
echo "   - Basculement entre couches"
echo "   - Recherche et filtres"
echo "   - Marqueurs et popups"
echo ""
echo "3. Surveiller les logs pour les erreurs"
echo ""

log_success "Déploiement terminé\! 🚀"
EOF < /dev/null
