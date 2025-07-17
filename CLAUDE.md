# Guide d'utilisation de Claude Code AI et Gemini CLI pour TopoclimbCH

Ce guide explique comment utiliser Claude Code AI et Gemini CLI efficacement avec le projet TopoclimbCH, une application web moderne de gestion de sites d'escalade en Suisse.

## Choix entre Claude Code AI et Gemini CLI

### Utilisez **Gemini CLI** quand :
- Vous analysez l'ensemble du projet (> 100KB de code)
- Vous avez besoin d'une vue d'ensemble architecturale
- Vous voulez comparer plusieurs gros fichiers
- Vous vérifiez si une fonctionnalité est implémentée dans tout le projet
- Le contexte Claude est insuffisant pour la tâche

### Utilisez **Claude Code AI** quand :
- Vous modifiez des fichiers spécifiques
- Vous créez de nouvelles fonctionnalités
- Vous déboguez des problèmes précis
- Vous voulez des modifications directes dans le code

## Structure du projet TopoclimbCH

```
/
├── config/                 # Configuration routes et application
├── public/                 # Assets publics (CSS, JS, images)
├── resources/              # Templates Twig, langues, vues
├── src/                    # Code source principal PHP
│   ├── Core/               # Framework MVC personnalisé
│   ├── Models/             # Modèles de données (Region, Sector, Route, User, etc.)
│   ├── Controllers/        # Contrôleurs MVC
│   ├── Services/           # Services métier (Auth, Media, Weather, etc.)
│   ├── Middleware/         # Middleware d'authentification et sécurité
│   └── Helpers/            # Fonctions utilitaires
├── tests/                  # Tests unitaires et fonctionnels
└── composer.json           # Dépendances PHP
```

## 📋 ROADMAP ET PROCHAINES ÉTAPES - TopoclimbCH

### 🎯 STATUT ACTUEL (Juillet 2025)
- ✅ **100% de tests réussis** (40/40 tests)
- ✅ **Système de base fonctionnel** (CRUD, Auth, API, Météo)
- ✅ **Intégration météo MeteoSwiss** complète
- ✅ **APIs REST** opérationnelles
- ✅ **Gestion des médias** fonctionnelle
- ✅ **Carte interactive** avec tuiles suisses
- ✅ **Erreurs critiques 500 résolues** (SQL, validation, méthodes manquantes)
- ✅ **Fonctionnalités manquantes ajoutées** (Events, Forum, Log d'ascensions)

### 🆕 **CORRECTIONS RÉCENTES (Juillet 2025)**

#### ✅ **Erreurs Critiques Résolues**
- **SQL Error**: Corrigé `Column 'r.difficulty_value' not found` dans RegionController:260
- **Validation Error**: Supprimé les règles de validation 'string' invalides
- **Missing Methods**: Ajouté `logAscent()` et `apiSectors()` manquantes
- **Route Mapping**: Corrigé le mapping des routes `/routes/{id}/log-ascent`

#### ✅ **Nouvelles Fonctionnalités Ajoutées**
- **EventController**: Contrôleur complet pour la gestion d'événements
- **ForumController**: Système de forum avec catégories et discussions
- **Commentaires et Favoris**: Système sécurisé avec protection CSRF
- **Log d'Ascensions**: Formulaire et traitement des ascensions complètés
- **API Books**: Endpoint `/api/books/{id}/sectors` fonctionnel

#### ✅ **Routes Ajoutées (15+ nouvelles routes)**
```php
// Events
GET/POST /events, /events/create, /events/{id}, /events/{id}/register

// Forum  
GET/POST /forum, /forum/category/{id}, /forum/topic/{id}

// Commentaires et Favoris
GET/POST /routes/{id}/comments
POST/DELETE /routes/{id}/favorite

// Log d'ascensions
GET/POST /routes/{id}/log-ascent
```

#### 🔧 **Commit: 71818e5**
- **6 fichiers modifiés**: +1216 insertions, -7 suppressions
- **Nouveaux contrôleurs**: EventController.php, ForumController.php
- **Controllers mis à jour**: RouteController, BookController, RegionController
- **Routes étendues**: 15+ nouvelles routes ajoutées

### 🔴 PRIORITÉ HAUTE (À développer immédiatement)

#### 🗺️ Géolocalisation et Navigation GPS
- [ ] Implémentation GPS pour localiser les utilisateurs
- [ ] Navigation vers les sites d'escalade
- [ ] Intégration avancée avec les cartes suisses Swisstopo
- [ ] Calcul d'itinéraires d'accès aux sites

#### 📱 Synchronisation Mobile et Mode Hors-ligne
- [ ] Mode hors-ligne pour l'application mobile
- [ ] Synchronisation des données entre appareils
- [ ] Cache local pour les informations essentielles
- [ ] Téléchargement préventif des données de régions

#### 🔒 Sécurité et Maintenance Système
- [ ] Système de backup automatique des données
- [ ] Monitoring et métriques de performance (logs, erreurs)
- [ ] Surveillance des erreurs et alertes
- [ ] Optimisation des performances base de données

### 🟡 PRIORITÉ MOYENNE (Développement continu)

#### 👥 Fonctionnalités Communautaires
- [ ] Système de notifications en temps réel
- [ ] Commentaires et évaluations sur les voies
- [ ] Favoris et listes personnalisées
- [ ] Événements et sorties communautaires
- [ ] Système de modération et signalement

#### 🎨 Interface et Expérience Utilisateur
- [ ] Amélioration de l'interface utilisateur (UI/UX)
- [ ] Traduction multilingue (DE, EN, IT)
- [ ] Système de photos et galeries pour les voies
- [ ] Responsive design mobile avancé

#### 🔌 Intégration et Performance
- [ ] API REST complète pour développeurs tiers
- [ ] Cache Redis pour optimiser les performances
- [ ] Optimisation des requêtes SQL
- [ ] CDN pour les médias et assets

### 🟢 PRIORITÉ BASSE (Fonctionnalités avancées)

#### 📊 Analyse et Statistiques
- [ ] Statistiques et analyse des ascensions
- [ ] Système de badges et récompenses
- [ ] Recommandations personnalisées
- [ ] Tableaux de bord personnalisés

#### 📄 Export et Intégration Externe
- [ ] Export des données (GPX, PDF, etc.)
- [ ] Génération automatique de topos PDF
- [ ] Intégration avec les réseaux sociaux
- [ ] Import de données depuis autres plateformes

### 🔧 DÉTAILS TECHNIQUES PAR FONCTIONNALITÉ

#### Géolocalisation (Priorité 1)
```php
// Service à créer
class GeolocationService {
    public function getCurrentPosition(): array
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    public function findNearestSites(float $lat, float $lng, int $radius = 10): array
    public function generateDirections(int $siteId): array
}
```

#### Mode Hors-ligne (Priorité 2)
```javascript
// Service Worker pour cache
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open('topoclimb-v1').then(cache => {
            return cache.addAll([
                '/offline.html',
                '/css/app.css',
                '/js/app.js',
                '/api/regions',
                '/api/sites'
            ]);
        })
    );
});
```

#### Système de Notifications (Priorité 3)
```php
// Service à créer
class NotificationService {
    public function sendNotification(int $userId, string $type, array $data): void
    public function getUnreadNotifications(int $userId): array
    public function markAsRead(int $notificationId): void
    public function subscribeToUpdates(int $userId, string $type): void
}
```

### 🎯 PLAN DE DÉVELOPPEMENT RECOMMANDÉ

#### Phase 1 (1-2 mois) - Base Mobile
1. Géolocalisation GPS
2. Mode hors-ligne basique
3. Monitoring système

#### Phase 2 (2-3 mois) - Communauté
1. Commentaires et évaluations
2. Système de favoris
3. Notifications en temps réel

#### Phase 3 (3-4 mois) - Avancé
1. Statistiques et analytics
2. Export de données
3. API publique

### 🔧 COMMANDES DÉVELOPPEMENT UTILES

```bash
# Tester les nouvelles fonctionnalités
php test_comprehensive_fixed.php

# Analyser l'architecture avec Gemini CLI
gemini -p "@./ Analyze current TopoclimbCH architecture for [FEATURE] implementation"

# Vérifier les performances
php -S localhost:8000 -t public/
ab -n 100 -c 10 http://localhost:8000/

# Monitoring des logs
tail -f storage/logs/debug-$(date +%Y-%m-%d).log
```

### 📱 SPÉCIFICATIONS TECHNIQUES

#### Stack Technologique Actuel
- **Backend**: PHP 8.4, Framework MVC personnalisé
- **Frontend**: Twig, Bootstrap 5, JavaScript ES6
- **Base de données**: SQLite/MySQL avec support dual
- **APIs**: REST JSON, MeteoSwiss, Swisstopo
- **Cache**: File-based (à migrer vers Redis)

#### Prochaines Technologies à Intégrer
- **PWA**: Service Workers, Cache API
- **WebRTC**: Notifications push
- **WebGL**: Cartes 3D avancées
- **WebAssembly**: Calculs géographiques performants

---

**Note importante**: Cette roadmap est mise à jour automatiquement. Consultez ce fichier pour connaître les priorités actuelles du développement TopoclimbCH.

## Commandes Gemini CLI pour TopoclimbCH

### Analyse globale du projet avec Gemini CLI

```bash
# Vue d'ensemble complète du projet
gemini -p "@./ Analyze the TopoclimbCH project structure, architecture, and main functionalities"

# Analyse de l'architecture backend PHP
gemini -p "@src/ @config/ Analyze the PHP MVC architecture and backend structure of TopoclimbCH"

# Analyse du frontend (Twig, CSS, JS)
gemini -p "@resources/ @public/ Analyze the frontend structure (Twig templates, CSS, JS) of TopoclimbCH"

# Analyse complète avec tous les fichiers
gemini --all_files -p "Give me a comprehensive overview of the TopoclimbCH climbing application"
```

### Vérification des fonctionnalités implémentées avec Gemini CLI

```bash
# Vérifier le système d'authentification
gemini -p "@src/Controllers/AuthController.php @src/Services/AuthService.php @src/Middleware/ Is authentication and authorization fully implemented? Show user roles and permissions system"

# Vérifier le système de gestion des régions d'escalade
gemini -p "@src/Controllers/RegionController.php @src/Services/RegionService.php @src/Models/Region.php @resources/views/regions/ Is the regions management system complete? What's missing for modern region pages with maps and weather?"

# Vérifier l'intégration météo (priorité haute)
gemini -p "@src/Services/WeatherService.php @src/Controllers/RegionController.php Is weather integration implemented? Show weather API calls and caching for climbing conditions"

# Vérifier le système de médias et galeries
gemini -p "@src/Services/MediaService.php @src/Controllers/MediaController.php @src/Models/Media.php Is media management (upload, galleries) fully implemented for climbing photos?"

# Vérifier les APIs REST pour mobile
gemini -p "@src/Controllers/ @config/routes.php Are REST APIs implemented for mobile app? List all API endpoints with authentication"

# Vérifier le système d'administration
gemini -p "@src/Controllers/AdminController.php @resources/views/admin/ Is the admin panel complete? What admin features are missing for climbing site management?"

# Vérifier l'intégration des cartes suisses
gemini -p "@public/js/components/map-manager.js @resources/views/ Are Swiss maps (Swisstopo) integrated? Show map functionality for climbing sectors"

# Vérifier les APIs externes (météo, géocodage, cartes)
gemini -p "@src/Services/ @config/ Are external APIs (OpenWeatherMap, Swisstopo, Nominatim) configured and implemented?"

# Vérifier les tests et couverture
gemini -p "@tests/ What test coverage exists for the TopoclimbCH application?"
```

### Analyse des composants spécifiques avec Gemini CLI

```bash
# Analyse des modèles de données d'escalade
gemini -p "@src/Models/ Analyze the climbing database models and relationships (Region, Sector, Route, User, etc.)"

# Analyse des services métier
gemini -p "@src/Services/ What services are implemented and what's missing? Focus on WeatherService and GeocodingService for Swiss climbing"

# Analyse du système de permissions et rôles
gemini -p "@src/Middleware/ @src/Services/AuthService.php Analyze the permission system (admin, moderator, editor, contributor, user) and middleware implementation"

# Analyse des contrôleurs MVC
gemini -p "@src/Controllers/ What controllers are implemented and what endpoints are missing for complete climbing site management?"

# Analyse de la base de données
gemini -p "@COLUMNS.sql @kcu.sql @src/Models/ Analyze the climbing database structure and model relationships"

# Analyse des interconnexions
gemini -p "@src/ Map all the interconnections between controllers, services, and models in TopoclimbCH"
```

### Analyse comparative avec les TODOs (Gemini CLI)

```bash
# Comparer avec les TODOs pour les régions
gemini -p "@access_system.txt @regions_todo.txt @src/Controllers/RegionController.php @src/Services/RegionService.php @resources/views/regions/ Compare the regions TODO list with current implementation. What needs to be done for modern region pages?"

# Analyser le système d'accès et permissions manquant
gemini -p "@access_system.txt @src/Controllers/AdminController.php @src/Middleware/ Compare the access system requirements with current implementation. What's missing for role-based access?"

# Vérifier les APIs et configurations
gemini -p "@apis_config.txt @src/Services/ Are the required APIs and configurations implemented? Show missing integrations"

# Analyser l'architecture frontend moderne
gemini -p "@frontend_architecture.txt @resources/views/ @public/ Compare frontend architecture requirements with current implementation"

# Vérifier les migrations manquantes
gemini -p "@access_system.txt @COLUMNS.sql What database migrations are needed for the new permission system and features?"

# Analyser le modèle économique
gemini -p "@MODÈLE\ ÉCONOMIQUE\ COMMUNAUTAIRE @src/Controllers/ @resources/views/ Is the subscription and payment system implemented? What's missing for the community economic model?"
```

### Vérification de sécurité avec Gemini CLI

```bash
# Vérifier les protections contre les injections SQL
gemini -p "@src/ @config/ Are SQL injection protections implemented? Show how user inputs are sanitized"

# Vérifier la gestion des erreurs
gemini -p "@src/ @resources/views/errors/ Is proper error handling implemented for all endpoints? Show examples of try-catch blocks"

# Vérifier l'authentification CSRF
gemini -p "@src/Middleware/ @src/Core/ Is CSRF protection implemented throughout the application?"

# Vérifier la validation des données
gemini -p "@src/Services/ValidationService.php @src/Controllers/ Are input validations properly implemented for all forms?"
```

### Analyse de performance avec Gemini CLI

```bash
# Analyser les requêtes de base de données
gemini -p "@src/Models/ @src/Services/ Are database queries optimized? Show potential N+1 problems and caching strategies"

# Analyser la gestion des médias
gemini -p "@src/Services/MediaService.php @public/ Are image uploads and processing optimized for climbing photos?"

# Analyser le cache
gemini -p "@src/Services/ @config/ Is caching implemented for weather data, geocoding, and database queries?"
```

## Notes importantes sur Gemini CLI

### Syntaxe des chemins
- **Chemins relatifs** : Les chemins `@` sont relatifs au répertoire où vous exécutez la commande `gemini`
- **Inclusion de fichiers** : `@src/file.php` pour un fichier spécifique
- **Inclusion de dossiers** : `@src/` pour tout un dossier
- **Fichiers multiples** : `@src/Models/ @src/Controllers/ @config/` pour plusieurs dossiers

### Avantages de Gemini CLI pour TopoclimbCH
- **Contexte massif** : Peut analyser l'ensemble du projet sans limites
- **Vue d'ensemble** : Idéal pour comprendre l'architecture globale
- **Vérification d'implémentation** : Parfait pour vérifier si une fonctionnalité existe
- **Analyse comparative** : Compare facilement les TODOs avec le code existant

### Exemples d'utilisation optimale

```bash
# Avant de commencer à développer - Vue d'ensemble
gemini -p "@./ I'm about to work on TopoclimbCH. Give me a complete overview of the current state, what's implemented, and what's missing"

# Vérifier une fonctionnalité spécifique
gemini -p "@src/ @resources/ Is weather integration with OpenWeatherMap fully implemented in TopoclimbCH? Show all related code"

# Analyser avant modification
gemini -p "@src/Services/RegionService.php @src/Controllers/RegionController.php @resources/views/regions/ Show me the current region management implementation before I modify it"

# Vérifier les dépendances
gemini -p "@src/ Which files and classes depend on the RegionService? Show all interconnections"

# Analyser la cohérence du code
gemini -p "@src/ Check code consistency, naming conventions, and architectural patterns across TopoclimbCH"
```

### Commandes de génération de résumés pour Claude Code AI

```bash
# Générer un résumé pour Claude Code AI
gemini -p "@./ Generate a comprehensive summary of the TopoclimbCH project for Claude Code AI, including current implementation status, missing features, and next steps"

# Analyser un composant spécifique pour Claude
gemini -p "@src/Services/WeatherService.php @src/Controllers/RegionController.php Analyze the weather integration status and create a summary for Claude Code AI development"

# Préparer le contexte pour développement
gemini -p "@regions_todo.txt @src/Controllers/RegionController.php @src/Services/RegionService.php @resources/views/regions/ Prepare a development context for Claude Code AI to work on region modernization"
```

## Commandes Claude Code AI pour TopoclimbCH

*Utilisez Claude Code AI pour les modifications spécifiques après avoir fait l'analyse globale avec Gemini CLI*

## Commandes spécifiques pour le développement

### Développement de nouvelles fonctionnalités

```bash
# Créer le WeatherService manquant
claude create "@src/Services/ @apis_config.txt Create WeatherService.php with OpenWeatherMap integration for climbing conditions"

# Moderniser les pages de régions
claude create "@resources/views/regions/ @public/css/pages/regions/ @public/js/pages/regions/ Create modern region pages with maps, weather, and responsive design"

# Implémenter les APIs REST manquantes
claude create "@src/Controllers/ @config/routes.php Create REST API endpoints for mobile app with JWT authentication"

# Créer le système d'administration complet
claude create "@src/Controllers/AdminController.php @resources/views/admin/ Create complete admin panel with user management and statistics"
```

### Correction et amélioration du code existant

```bash
# Corriger un bug spécifique
claude fix "@src/Controllers/RegionController.php Fix the region display bug in the show method"

# Améliorer les performances
claude optimize "@src/Services/MediaService.php Optimize image upload and processing for climbing photos"

# Refactoriser du code
claude refactor "@src/Controllers/ Refactor controllers to use dependency injection consistently"
```

### Tests et validation

```bash
# Créer des tests manquants
claude test "@src/Services/WeatherService.php @tests/ Create comprehensive tests for WeatherService"

# Valider la sécurité
claude security "@src/Middleware/ @src/Controllers/ Validate security measures for climbing site management"

# Vérifier la conformité aux standards
claude validate "@src/ Check code compliance with PSR standards and best practices"
```

## Fonctionnalités prioritaires à implémenter

### Phase 1 - Services manquants (URGENT)
1. **WeatherService.php** - Intégration météo OpenWeatherMap
2. **GeocodingService.php** - Géocodage pour coordonnées suisses
3. **RegionService.php** - Enrichissement pour pages modernes

### Phase 2 - Frontend moderne
1. **Pages régions** - Design moderne avec cartes et météo
2. **APIs REST** - Endpoints pour application mobile
3. **Admin panel** - Interface d'administration complète

### Phase 3 - Intégrations avancées
1. **Cartes Swisstopo** - Intégration cartes officielles suisses
2. **Système permissions** - Rôles granulaires
3. **Monitoring** - Logs et métriques

## Workflow recommandé : Gemini CLI + Claude Code AI

### 1. Analyse globale avec Gemini CLI
```bash
# Comprendre l'état actuel
gemini -p "@./ Analyze the current state of TopoclimbCH. What's implemented and what's missing?"

# Vérifier une fonctionnalité spécifique
gemini -p "@src/Services/ @src/Controllers/ Is weather integration implemented? Show all related code"
```

### 2. Développement avec Claude Code AI
```bash
# Après l'analyse Gemini, créer/modifier avec Claude
claude create "Create WeatherService.php based on the analysis from Gemini"
claude fix "Fix the region display bug identified in the analysis"
```

### 3. Validation avec Gemini CLI
```bash
# Vérifier après modifications
gemini -p "@src/Services/WeatherService.php @src/Controllers/RegionController.php Is the weather integration now complete and properly integrated?"
```

## Bonnes pratiques avec Gemini CLI + Claude Code AI

### Workflow d'analyse avant modification
```bash
# 1. Analyse globale avec Gemini
gemini -p "@src/Services/RegionService.php @src/Controllers/RegionController.php Show me the current implementation before I modify it"

# 2. Modification avec Claude Code AI
claude modify "Update RegionService.php to add weather integration"

# 3. Vérification avec Gemini
gemini -p "@src/ Show all files that depend on RegionService.php and verify the changes are compatible"
```

### Vérification des interconnexions
```bash
# Avec Gemini CLI - Vue d'ensemble des dépendances
gemini -p "@src/ Show all interconnections between RegionService and other components"

# Avec Claude Code AI - Modification spécifique
claude update "Update all dependent files to use the new RegionService methods"
```

### Validation après modification
```bash
# Avec Gemini CLI - Test global
gemini -p "@src/Services/RegionService.php @tests/ Are all tests still passing after the RegionService changes?"

# Avec Claude Code AI - Correction spécifique
claude test "Create additional tests for the new weather integration methods"
```

## Structure des données spécifiques

### Modèles principaux
- **Region** - Régions d'escalade (Valais, Jura, etc.)
- **Sector** - Secteurs dans les régions
- **Route** - Voies d'escalade individuelles
- **User** - Utilisateurs avec rôles
- **Media** - Photos et documents

### APIs externes intégrées
- **OpenWeatherMap** - Météo pour conditions d'escalade
- **Swisstopo** - Cartes officielles suisses
- **Nominatim** - Géocodage OpenStreetMap

### Système de permissions
- **Admin (1)** - Gestion complète
- **Moderator (2)** - Modération contenu
- **Editor (4)** - Édition données escalade
- **Contributor (5)** - Contribution données
- **User (3)** - Utilisation standard

## Notes importantes

- **Projet Swiss-focused** - Spécifique à l'escalade en Suisse
- **Architecture MVC** - Framework PHP personnalisé
- **Base de données** - MySQL avec relations complexes
- **Frontend** - Twig + CSS/JS moderne
- **Mobile-ready** - APIs REST en développement

## Commandes utiles rapides

### Avec Gemini CLI (analyse)
```bash
# Statut général du projet
gemini -p "@./ What is the current implementation status of TopoclimbCH?"

# Prochaines étapes
gemini -p "@regions_todo.txt @access_system.txt What should be the next development priorities?"

# Problèmes courants
gemini -p "@src/ @resources/ What are the main issues or bugs in the current implementation?"

# Vérification d'une fonctionnalité
gemini -p "@src/ @resources/ Is the subscription and payment system implemented for the community economic model?"

# Analyse de sécurité
gemini -p "@src/ @config/ Are all security measures properly implemented (CSRF, SQL injection, XSS protection)?"
```

### Avec Claude Code AI (action)
```bash
# Création rapide
claude create "Create the missing WeatherService.php with OpenWeatherMap integration"

# Correction rapide
claude fix "Fix the authentication bug in AuthController.php"

# Optimisation
claude optimize "Optimize the MediaService.php for better performance"

# Tests
claude test "Create tests for the new weather integration features"
```

## Cas d'usage spécifiques TopoclimbCH

### Développement des pages régions modernes
```bash
# 1. Analyse avec Gemini CLI
gemini -p "@regions_todo.txt @src/Controllers/RegionController.php @resources/views/regions/ Compare TODO requirements with current implementation"

# 2. Développement avec Claude Code AI
claude create "Create modern region pages with weather integration and Swiss maps"

# 3. Validation avec Gemini CLI
gemini -p "@resources/views/regions/ @public/css/pages/regions/ @public/js/pages/regions/ Are the modern region pages complete and responsive?"
```

### Implémentation du système d'administration
```bash
# 1. Analyse avec Gemini CLI
gemini -p "@access_system.txt @src/Controllers/AdminController.php @src/Middleware/ What admin features are missing?"

# 2. Développement avec Claude Code AI
claude create "Create complete admin panel with user management and role-based permissions"

# 3. Validation avec Gemini CLI
gemini -p "@src/Controllers/AdminController.php @resources/views/admin/ @src/Middleware/ Is the admin system complete and secure?"
```

### Intégration des APIs externes
```bash
# 1. Analyse avec Gemini CLI
gemini -p "@apis_config.txt @src/Services/ Which external APIs are configured and which are missing?"

# 2. Développement avec Claude Code AI
claude create "Implement missing APIs: OpenWeatherMap, Swisstopo, Nominatim geocoding"

# 3. Validation avec Gemini CLI
gemini -p "@src/Services/ @config/ Are all external APIs properly integrated with error handling and caching?"
```

---

**Note importante** : Utilisez ces commandes depuis le répertoire racine du projet TopoclimbCH. Commencez toujours par une analyse Gemini CLI pour comprendre l'état actuel, puis utilisez Claude Code AI pour les modifications spécifiques, et finissez par une validation avec Gemini CLI.