#!/bin/bash

# Script de validation pré-déploiement TopoclimbCH
# À exécuter avant chaque déploiement

echo "🚀 PRE-DEPLOYMENT VALIDATION"
echo "============================"
echo

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction d'affichage
success() {
    echo -e "${GREEN}✅ $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Variables
DEPLOYMENT_READY=true
WARNINGS=0

# 1. Vérification des prérequis
echo "1. Checking prerequisites..."
echo "----------------------------"

# PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2)
if php -v | grep -q "8\.1\|8\.2\|8\.3"; then
    success "PHP version: $PHP_VERSION"
else
    error "PHP version: $PHP_VERSION (8.1+ required)"
    DEPLOYMENT_READY=false
fi

# Composer
if [ -f "vendor/autoload.php" ]; then
    success "Composer dependencies installed"
else
    error "Composer dependencies missing - run 'composer install'"
    DEPLOYMENT_READY=false
fi

# 2. Nettoyage du cache
echo
echo "2. Cleaning cache..."
echo "-------------------"
rm -rf cache/container/* cache/routes/* 2>/dev/null || true
success "Cache cleaned"

# 3. Test type-hints
echo
echo "3. Running type-hints validation..."
echo "-----------------------------------"
if php test_type_hints.php > /dev/null 2>&1; then
    success "Type-hints validation passed"
else
    error "Type-hints validation failed"
    echo "Running detailed type-hints test..."
    php test_type_hints.php
    DEPLOYMENT_READY=false
fi

# 4. Test déploiement
echo
echo "4. Running deployment validation..."
echo "-----------------------------------"
if php test_deployment.php > /dev/null 2>&1; then
    success "Deployment validation passed"
else
    error "Deployment validation failed"
    echo "Running detailed deployment test..."
    php test_deployment.php
    DEPLOYMENT_READY=false
fi

# 5. Test pipeline
echo
echo "5. Running test pipeline..."
echo "---------------------------"
if php test_pipeline.php > /dev/null 2>&1; then
    success "Test pipeline passed"
else
    error "Test pipeline failed"
    echo "Running detailed pipeline test..."
    php test_pipeline.php
    DEPLOYMENT_READY=false
fi

# 6. Vérification des permissions
echo
echo "6. Checking permissions..."
echo "-------------------------"
for dir in cache logs public/upload; do
    if [ -d "$dir" ]; then
        if [ -w "$dir" ]; then
            success "Directory writable: $dir"
        else
            error "Directory not writable: $dir"
            DEPLOYMENT_READY=false
        fi
    else
        warning "Directory missing: $dir (will be created)"
        mkdir -p "$dir" 2>/dev/null || true
        WARNINGS=$((WARNINGS + 1))
    fi
done

# 7. Vérification de la configuration
echo
echo "7. Checking configuration..."
echo "----------------------------"
if [ -f ".env" ]; then
    success "Environment file exists"
    
    # Vérification des variables essentielles
    if grep -q "DB_HOST" .env && grep -q "DB_DATABASE" .env; then
        success "Database configuration present"
    else
        error "Database configuration incomplete"
        DEPLOYMENT_READY=false
    fi
else
    error "Environment file missing"
    DEPLOYMENT_READY=false
fi

# 8. Test spécifique pour l'erreur corrigée
echo
echo "8. Testing HomeController fix..."
echo "-------------------------------"
php -r "
require_once 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->load();
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
try {
    \$containerBuilder = new TopoclimbCH\Core\ContainerBuilder();
    \$container = \$containerBuilder->build();
    \$homeController = \$container->get('TopoclimbCH\\Controllers\\HomeController');
    echo 'HomeController instantiation: SUCCESS\n';
} catch (Exception \$e) {
    echo 'HomeController instantiation: FAILED - ' . \$e->getMessage() . '\n';
    exit(1);
}
"

if [ $? -eq 0 ]; then
    success "HomeController instantiation test passed"
else
    error "HomeController instantiation test failed"
    DEPLOYMENT_READY=false
fi

# 9. Génération du rapport
echo
echo "9. Generating deployment report..."
echo "---------------------------------"
REPORT_FILE="deployment_report_$(date +%Y%m%d_%H%M%S).txt"
{
    echo "TopoclimbCH Deployment Report"
    echo "Generated: $(date)"
    echo "PHP Version: $PHP_VERSION"
    echo "Deployment Ready: $DEPLOYMENT_READY"
    echo "Warnings: $WARNINGS"
    echo ""
    echo "Files checked:"
    find . -name "*.php" -path "./src/*" | wc -l
    echo ""
    echo "Cache status:"
    ls -la cache/ 2>/dev/null || echo "Cache directory not found"
} > "$REPORT_FILE"
success "Report generated: $REPORT_FILE"

# 10. Verdict final
echo
echo "=============================="
echo "    DEPLOYMENT VERDICT"
echo "=============================="

if [ "$DEPLOYMENT_READY" = true ]; then
    echo -e "${GREEN}🎉 DEPLOYMENT READY!${NC}"
    echo -e "${GREEN}✅ All critical checks passed${NC}"
    echo -e "${GREEN}🚀 Safe to deploy to production${NC}"
    
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠️  $WARNINGS warnings detected${NC}"
    fi
    
    echo
    echo "Next steps:"
    echo "1. Deploy to production"
    echo "2. Monitor application logs"
    echo "3. Test critical functionality"
    echo "4. Verify performance"
    
    exit 0
else
    echo -e "${RED}❌ DEPLOYMENT NOT READY!${NC}"
    echo -e "${RED}🔧 Fix the errors above before deployment${NC}"
    echo -e "${RED}⚠️  Deployment will likely fail${NC}"
    
    echo
    echo "Required actions:"
    echo "1. Fix all reported errors"
    echo "2. Re-run this script"
    echo "3. Verify all tests pass"
    echo "4. Then deploy"
    
    exit 1
fi