# 🚀 Guide de Déploiement TopoclimbCH

## 🔍 Diagnostic de l'Erreur 500

D'après les logs, l'erreur est causée par des **dépendances Composer manquantes** :

```
symfony/deprecation-contracts/function.php: Failed to open stream: No such file or directory
```

## 🛠️ Solution Immédiate

### 1. Commandes à exécuter sur le serveur

```bash
# Aller dans le répertoire du projet
cd /home/httpd/vhosts/topoclimb.ch/topoclimb

# Nettoyer et réinstaller Composer
composer clear-cache
rm -rf vendor/
composer install --no-dev --optimize-autoloader

# Vérifier l'installation
ls -la vendor/symfony/deprecation-contracts/function.php
```

### 2. Utiliser le script de déploiement

```bash
# Rendre le script exécutable
chmod +x deploy.sh

# Exécuter le déploiement
./deploy.sh
```

## 📋 Checklist de Déploiement

### Avant le déploiement

- [ ] `composer.json` est présent
- [ ] Code est sur la branche `staging`
- [ ] Fichier `.env.production.example` existe

### Pendant le déploiement

- [ ] Exécuter `composer install --no-dev --optimize-autoloader`
- [ ] Vérifier que `vendor/autoload.php` existe
- [ ] Vérifier que `vendor/symfony/deprecation-contracts/function.php` existe
- [ ] Créer le fichier `.env` depuis `.env.production.example`
- [ ] Configurer les permissions (755 pour public/, resources/, src/)

### Après le déploiement

- [ ] Configurer le fichier `.env` avec vos paramètres
- [ ] Créer la base de données
- [ ] Tester l'application
- [ ] Vérifier les logs d'erreur

## 🔧 Configuration .env

Créez le fichier `.env` avec cette configuration minimale :

```env
# Mode production
DEBUG=false
APP_ENV=production

# Base de données (à adapter selon votre configuration)
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=topoclimb_production
DB_USER=topoclimb_user
DB_PASSWORD=votre_mot_de_passe

# Sécurité
SECRET_KEY=votre_cle_secrete_longue_et_complexe
CSRF_SECRET=votre_cle_csrf_secrete

# API météo (optionnel)
OPENWEATHER_API_KEY=votre_cle_openweather
```

## 🧪 Tests Post-Déploiement

1. **Test de base** : Accéder à `https://topoclimb.ch`
2. **Test API** : Accéder à `https://topoclimb.ch/api/regions`
3. **Test pages** : Vérifier `/login`, `/register`, `/about`

## 🚨 Dépannage

### Erreur 500 persistante

```bash
# Vérifier les logs PHP
tail -f /var/log/php_errors.log

# Vérifier les permissions
ls -la public/
ls -la vendor/

# Tester la syntaxe PHP
php -l public/index.php
```

### Base de données

```bash
# Vérifier la connexion DB
mysql -u topoclimb_user -p topoclimb_production

# Importer le schéma (si nécessaire)
mysql -u topoclimb_user -p topoclimb_production < database/schema.sql
```

## 📞 Support

En cas de problème persistant :

1. Vérifiez les logs d'erreur du serveur
2. Exécutez `php debug_production.php` pour un diagnostic complet
3. Contactez l'équipe de développement avec les logs d'erreur

---

**Dernière mise à jour** : 2025-07-15  
**Version** : 1.0 (Staging Ready)  
**Statut** : 71.8% des tests réussis