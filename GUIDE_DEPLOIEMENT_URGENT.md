# 🔥 GUIDE DE DÉPLOIEMENT URGENT - TopoclimbCH

## ❌ NOUVEAU PROBLÈME CRITIQUE IDENTIFIÉ

**LOGS DE PRODUCTION (30 juillet 2025):**
```
Erreur lors de la tentative de connexion: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email' in 'where clause'
Response status code: 500
```

**CAUSES IDENTIFIÉES:**
1. 🗄️ **Structure DB différente:** La base de production utilise `mail` au lieu de `email`
2. 🔥 **Erreur 500:** Problème lors de la connexion même avec les bons identifiants
3. 📊 **Base incomplète:** Tables manquantes ou structure différente

---

## ✅ SOLUTION IMMÉDIATE - OPTION A: RÉPARATION AUTOMATIQUE

### 1️⃣ **SCRIPT DE RÉPARATION AUTOMATIQUE** (RECOMMANDÉ)

```bash
# Sur le serveur de production
# 1. Uploader le script de réparation
scp fix_production_database.php user@serveur:/path/to/topoclimb/

# 2. Exécuter la réparation automatique
php fix_production_database.php

# 3. Tester le diagnostic
php debug_500_error.php
```

**Ce script va automatiquement :**
- ✅ Détecter si la colonne est `mail` ou `email`
- ✅ Ajouter la colonne `email` si manquante
- ✅ Ajouter la colonne `password_hash` si manquante
- ✅ Créer l'utilisateur admin avec les bons identifiants
- ✅ Hasher les mots de passe existants

### 2️⃣ **OPTION B: REMPLACEMENT COMPLET**

```bash
# Sur votre serveur de production
# Sauvegarder l'ancienne DB (au cas où)
cp climbing_sqlite.db climbing_sqlite.db.backup-$(date +%Y%m%d)

# Copier la nouvelle DB depuis votre environnement local
scp climbing_sqlite.db user@serveur:/path/to/topoclimb/

# OU uploader via FTP/SFTP la nouvelle climbing_sqlite.db
```

### 2️⃣ **VÉRIFIER LES PERMISSIONS**

```bash
# Sur le serveur
chmod 666 climbing_sqlite.db
chown www-data:www-data climbing_sqlite.db  # Si nécessaire
```

### 3️⃣ **TESTER LA CONNEXION**

🔑 **IDENTIFIANTS ADMIN CRÉÉS:**
- **Email:** `admin@topoclimb.ch`
- **Password:** `admin123`
- **Rôle:** `0` (administrateur complet)

---

## 🧪 TESTS DE VALIDATION

### Test 1: Base de données
```bash
# Sur le serveur, exécuter :
php -r "
\$db = new PDO('sqlite:climbing_sqlite.db');
\$count = \$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo \"Utilisateurs: \$count\n\";
"
```
**Résultat attendu:** `Utilisateurs: 1`

### Test 2: Connexion admin
```bash
# Tester la connexion sur : https://votre-domaine.com/login
# Email: admin@topoclimb.ch
# Password: admin123
```

### Test 3: Pages protégées
Après connexion, vérifier l'accès à :
- `/sectors` ✅
- `/routes` ✅  
- `/regions` ✅
- `/sites` ✅

---

## 📊 STRUCTURE DE LA NOUVELLE BASE

### Tables recréées :
- ✅ `users` (1 admin créé)
- ✅ `climbing_regions` (1 région test)
- ✅ `climbing_sites` (1 site test)
- ✅ `climbing_sectors` (1 secteur test)
- ✅ `climbing_routes` (5 routes test)
- ✅ `climbing_books` (vide)
- ✅ `view_analytics` (conservée)

### Données de test incluses :
- **Région:** Valais
- **Site:** Saillon  
- **Secteur:** Secteur Sud
- **Routes:** Voie Test 1-5 (difficulté 6a)

---

## ⚠️ DÉPANNAGE

### Problème : "Aucune connexion ne fonctionne"

1. **Vérifier les permissions fichiers :**
```bash
ls -la climbing_sqlite.db
# Doit afficher : -rw-rw-rw- ou similaire
```

2. **Vérifier les logs d'erreur PHP :**
```bash
tail -f /var/log/php/error.log
# OU
tail -f /var/log/apache2/error.log
```

3. **Tester la DB directement :**
```bash
php -r "
try {
    \$db = new PDO('sqlite:climbing_sqlite.db');
    echo 'DB OK\n';
} catch(Exception \$e) {
    echo 'DB ERROR: ' . \$e->getMessage() . '\n';
}
"
```

### Problème : "Page de connexion ne s'affiche pas"

1. **Vérifier les routes :**
   - Controller `AuthController` présent
   - Route `/login` configurée

2. **Vérifier les dépendances PHP :**
```bash
php -m | grep -E "(pdo|sqlite)"
```

### Problème : "Erreur 500 après connexion"

1. **Erreur Analytics Controller :**
   - Vérifier que le commit `bb8d0ec` est déployé
   - Controller corrigé avec `protected ?Database $db`

---

## 🚀 ÉTAPES DE DÉPLOIEMENT COMPLÈTES

### Étape 1: Backup
```bash
# Sauvegarder l'état actuel
tar -czf backup-topoclimb-$(date +%Y%m%d).tar.gz .
```

### Étape 2: Mise à jour du code
```bash
git pull origin main
# Doit inclure le commit bb8d0ec ou plus récent
```

### Étape 3: Base de données
```bash
# Remplacer la DB
cp climbing_sqlite.db climbing_sqlite.db.old
# Copier la nouvelle DB depuis votre local
chmod 666 climbing_sqlite.db
```

### Étape 4: Test final
1. Ouvrir `https://votre-domaine.com/login`
2. Se connecter avec `admin@topoclimb.ch` / `admin123`
3. Vérifier l'accès à `/sectors`
4. Tester le changement de vue (grille/liste/compact)

---

## 📞 SUPPORT

Si le problème persiste après ces étapes :

1. **Vérifier la version du commit :**
```bash
git log --oneline -5
# Doit contenir : bb8d0ec fix: 🔥 RÉSOLUTION CRITIQUE
```

2. **Envoyer les logs d'erreur**

3. **Confirmer la structure de la DB :**
```bash
php -r "
\$db = new PDO('sqlite:climbing_sqlite.db');
\$tables = \$db->query(\"SELECT name FROM sqlite_master WHERE type='table'\");
foreach(\$tables as \$t) echo \$t['name'] . \"\n\";
"
```

---

## ✅ VALIDATION FINALE

Après déploiement, ces éléments DOIVENT fonctionner :

- [ ] Connexion admin réussie
- [ ] Page `/sectors` accessible  
- [ ] Système de vues fonctionne (grille/liste/compact)
- [ ] 26 secteurs affichés (ou données de test)
- [ ] Aucune erreur 500 dans les logs

**🎉 Une fois ces points validés, le système est complètement fonctionnel !**

---

*Document créé le 30 juillet 2025 - Version d'urgence*
*Commit de référence: bb8d0ec*