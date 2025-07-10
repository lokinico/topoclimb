# Solution à l'erreur 500 sur la route /map

## Problème identifié

La route `/map` générait une erreur 500 à cause d'un **conflit d'importation de classes Request**.

### Détail de l'erreur

1. **MapController.php** (ligne 5) importait :
   ```php
   use TopoclimbCH\Core\Request;
   ```

2. **BaseController.php** (ligne 6) importait :
   ```php
   use Symfony\Component\HttpFoundation\Request;
   ```

3. **MapController étend BaseController** mais utilisait des méthodes incompatibles :
   - `$request->getQuery()` (n'existe pas dans Symfony Request)
   - `$request->getParam()` (n'existe pas dans Symfony Request)

### Corrections apportées

#### 1. Changement d'import dans MapController
```php
// AVANT
use TopoclimbCH\Core\Request;

// APRÈS  
use Symfony\Component\HttpFoundation\Request;
```

#### 2. Remplacement des méthodes incompatibles

**getQuery() → query->get()**
```php
// AVANT
$request->getQuery('region')

// APRÈS
$request->query->get('region')
```

**getParam() → attributes->get()**
```php
// AVANT
$request->getParam('id')

// APRÈS
$request->attributes->get('id')
```

### Fichiers modifiés

- `/src/Controllers/MapController.php` : 
  - Import Request changé
  - 8 occurrences de méthodes corrigées

### Résultat

La route `/map` devrait maintenant fonctionner correctement sans erreur 500.

### Test de la correction

Pour vérifier que la correction fonctionne, vous pouvez :
1. Accéder à `http://localhost/topoclimb/map`
2. Vérifier que la page se charge sans erreur 500
3. Consulter les logs pour confirmer l'absence d'erreurs PHP

### Cause racine

Cette erreur était due à une incohérence dans l'architecture :
- Le projet utilise Symfony Request dans le contrôleur de base
- Mais certains contrôleurs utilisaient une classe Request personnalisée
- Les APIs des deux classes sont incompatibles

### Recommandations

1. **Uniformiser l'utilisation des classes Request** dans tout le projet
2. **Vérifier les autres contrôleurs** pour des problèmes similaires
3. **Documenter les classes utilisées** pour éviter les confusions futures