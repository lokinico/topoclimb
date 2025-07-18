# 🚀 Checklist de Déploiement TopoclimbCH

## ✅ Pré-déploiement

### 1. Tests locaux
```bash
# Tester les routes critiques
php test_local.php

# Vérifier les assets
ls -la public/css/pages/map.css
ls -la resources/views/checklists/index.twig
ls -la resources/views/equipment/index.twig
```

### 2. Validation du code
```bash
# Vérifier les derniers commits
git log --oneline -5

# Statut git propre
git status
```

### 3. Configuration
- [ ] Variables d'environnement configurées
- [ ] Base de données accessible
- [ ] Permissions fichiers correctes
- [ ] Certificats SSL valides

## 🔧 Déploiement

### 1. Synchronisation du code
```bash
# Pull du code sur le serveur
git pull origin main

# Vérifier le commit actuel
git rev-parse HEAD
```

### 2. Mise à jour des dépendances
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Permissions
```bash
chmod -R 755 public/
chmod -R 755 resources/views/
chmod -R 644 public/css/
```

## 🧪 Tests post-déploiement

### 1. Test automatique
```bash
# Modifier BASE_URL dans test_deployment.php
php test_deployment.php
```

### 2. Tests manuels critiques
- [ ] ✅ `/checklists` - Checklists de sécurité
- [ ] ✅ `/equipment` - Gestion d'équipement  
- [ ] ✅ `/map` - Carte interactive (tuiles OSM)
- [ ] ✅ `/` - Page d'accueil
- [ ] ✅ `/regions` - Liste des régions
- [ ] ✅ `/sites` - Sites d'escalade
- [ ] ✅ `/sectors` - Secteurs
- [ ] ✅ `/routes` - Voies d'escalade

### 3. Tests fonctionnels
- [ ] Basculement entre couches de carte (OSM → Swiss Topo → Satellite)
- [ ] Recherche sur la carte
- [ ] Filtres par région
- [ ] Navigation mobile responsive
- [ ] Chargement des templates sans erreur 500

## 🐛 Résolution des problèmes

### Erreurs 500 sur les nouvelles routes
```bash
# Vérifier les logs
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

**Solution**: Vérifier que les templates existent dans `resources/views/`

### Problèmes de carte
- **Tuiles mélangées**: Vérifier la console JavaScript
- **Marqueurs manquants**: Vérifier les données JSON dans `/api/map/sites`
- **Erreur de couche**: La carte revient automatiquement à OSM

### Problèmes de performance
```bash
# Optimiser l'autoloader
composer dump-autoload --optimize

# Vérifier les logs de performance
grep "slow" /var/log/apache2/access.log
```

## 📊 Monitoring

### Métriques à surveiller
- **Temps de réponse**: < 2000ms pour les pages principales
- **Taux d'erreur**: < 5% sur les routes critiques
- **Disponibilité**: > 99% uptime

### Alertes
- [ ] Monitoring des erreurs 500
- [ ] Alertes de performance
- [ ] Surveillance des logs

## 🔄 Rollback

En cas de problème critique:

```bash
# Retour au commit précédent
git log --oneline -5
git reset --hard <previous-commit>

# Ou revenir au commit stable
git reset --hard HEAD~1
```

## 📝 Notes de version

**Commit actuel**: `ad29395 - fix: Repair critical routes and improve map interface`

**Corrections apportées**:
- ✅ Routes `/checklists` et `/equipment` fonctionnelles
- ✅ Carte interactive avec gestion d'erreurs améliorée
- ✅ Templates Twig corrigés (`layouts/app.twig`)
- ✅ Contrôleurs avec injection de dépendances fixes

**Prochaines étapes**:
- Standardiser l'interface `/regions`
- Réparer les routes individuelles `/regions/{id}`
- Optimiser les performances

---

## 🚨 Contacts d'urgence

- **Développeur**: [Votre contact]
- **Serveur**: [Contact hébergement]
- **Monitoring**: [Lien vers dashboard]

---

*Dernière mise à jour: 2025-01-18*