# 🗄️ STRUCTURE BASE DE DONNÉES PRODUCTION - TopoclimbCH

**Date:** 30 juillet 2025  
**Objectif:** Documenter la structure exacte de votre base MySQL de production  

---

## 🚨 PROBLÈME IDENTIFIÉ PAR GEMINI

**Analyse des modifications récentes (2 semaines) :**

### ❌ **CAUSE RACINE : Incohérence champs base de données**

1. **Code AuthService.php (ligne 79) cherche :**
   ```php
   SELECT * FROM users WHERE mail = ? AND actif = 1 LIMIT 1
   ```

2. **Mais script de recréation database créé :**
   ```sql
   email VARCHAR(255) UNIQUE NOT NULL,
   ```

3. **RÉSULTAT :** Le code cherche `mail` mais la base a `email` !

### 📋 **MODIFICATIONS QUI ONT CASSÉ LE SYSTÈME**

- **Commit 99b79e6** : "correction urgente email → mail"  
- **Commit 969d7c0** : "AuthService.php avec requête EXACTE"  
- **Problème** : Ces commits ont changé le code pour utiliser `mail` sans adapter la base

---

## 📊 POUR OBTENIR VOTRE STRUCTURE EXACTE

**Exécutez sur votre serveur MySQL :**

### 1️⃣ **Structure table users**
```sql
DESCRIBE users;
```

### 2️⃣ **Création table users**
```sql
SHOW CREATE TABLE users;
```

### 3️⃣ **Export complet structure**
```sql
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;
```

---

## 📝 COLLEZ ICI VOTRE RÉSULTAT

**Résultat de `DESCRIBE users;` :**
```
[COLLEZ ICI LA STRUCTURE DE VOTRE TABLE USERS]
```

**Résultat de `SHOW CREATE TABLE users;` :**
```sql
[COLLEZ ICI LA REQUÊTE CREATE TABLE DE VOTRE TABLE USERS]
```

---

## 🔧 SOLUTION SELON VOTRE STRUCTURE

Une fois que vous aurez collé votre structure, je pourrai :

1. ✅ **Identifier le nom exact** de la colonne email (email vs mail)
2. ✅ **Corriger AuthService.php** avec le bon nom de colonne  
3. ✅ **Restaurer** le système d'authentification qui fonctionnait avant

---

## 🔗 LIEN AVEC CLAUDE.MD

Cette structure sera ajoutée à `/home/nibaechl/topoclimb/CLAUDE.md` section :

```markdown
## 🗄️ STRUCTURE BASE DE DONNÉES DE PRODUCTION

### Table users (structure exacte)
[Structure à compléter selon votre export]

### Configuration AuthService
- Colonne email: [à déterminer]  
- Colonne password: password_hash
- Colonne actif: actif

### Requête authentification correcte
```php
$result = $this->db->fetchOne("SELECT * FROM users WHERE [COLONNE_EMAIL] = ? AND actif = 1 LIMIT 1", [$email]);
```

---

**🎯 OBJECTIF :** Réparer le système qui fonctionnait avant en utilisant VOS champs exacts, sans plus de complications.