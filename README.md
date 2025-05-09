# topoclimb

Prompt Claude.ai

2. Étapes de développement et prompts pour Claude.ai

Étape 1: Configuration du projet
Prompt pour Claude:
Je développe une application PHP moderne pour la gestion de sites d'escalade appelée TopoclimbCH. Aide-moi à créer:

1. Un fichier composer.json complet avec les dépendances appropriées (PHP 8.1+)
2. Un fichier .htaccess pour Apache qui dirige tout le trafic vers index.php
3. Un fichier index.php dans le répertoire public/ qui initialise l'application
4. Un script de chargement automatique des classes (autoload)
5. Un fichier .env.example pour les variables d'environnement

Je souhaite une structure moderne et robuste, conforme aux standards PSR.

Étape 2: Classes core et système de routage
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin des classes core suivantes:

1. Une classe App qui initialise l'application et gère le cycle de requête/réponse
2. Une classe Router qui gère les routes avec support pour les méthodes HTTP et les paramètres dynamiques
3. Une classe Request qui encapsule les données de requête HTTP
4. Une classe Response pour générer des réponses HTTP standardisées
5. Une classe Session pour la gestion des sessions
6. Une classe Database qui implémente le pattern Singleton avec PDO

Chaque classe doit être dans un fichier séparé dans src/Core/, avec des méthodes bien documentées et des interfaces claires.

Étape 3: Système de base de données et modèles
Prompt pour Claude:
Je développe le système de modèles pour mon application d'escalade TopoclimbCH. Je souhaite:

1. Une classe Model abstraite de base qui implémente un ORM simple avec les méthodes principales (find, create, update, delete)
2. Le support pour les relations (hasMany, belongsTo, belongsToMany) avec lazy loading
3. Un système de validation des données intégré
4. Des méthodes pour la gestion des attributs avec accesseurs/mutateurs
5. Support pour les événements avant/après sauvegarde
6. Exemple d'implémentation pour les modèles:
   - Sector (secteur d'escalade)
   - Route (voie d'escalade)
   - User (utilisateur)

Utilise PHP 8.1+ avec les types de retour, les propriétés typées et les autres fonctionnalités modernes.

Étape 4: Contrôleurs et services
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin d'implémenter:

1. Une classe BaseController abstraite avec méthodes utilitaires communes
2. Un système d'injection de dépendances simple pour les contrôleurs
3. Un contrôleur SectorController complet qui gère:
   - Affichage de la liste des secteurs (index)
   - Affichage d'un secteur spécifique (show)
   - Création, édition et suppression de secteurs (create, store, edit, update, delete)
4. Une couche de services pour encapsuler la logique métier:
   - SectorService pour la gestion des secteurs
   - MediaService pour la gestion des médias/images

Sépare bien la logique métier des contrôleurs et utilise l'injection de dépendances.

Étape 5: Système de templates et vues
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin d'un système de templates:

1. Une classe View pour le rendu des templates
2. Un système d'héritage de templates avec layouts et partials
3. Un helper pour l'échappement automatique des variables
4. Support pour les sections et les composants
5. Exemples de templates pour:
   - Layout principal (avec header, footer, navigation)
   - Liste des secteurs (index)
   - Détail d'un secteur (show)
   - Formulaire de création/édition

J'utilise PHP natif pour les templates (pas de moteur externe comme Twig).

Étape 6: Authentication et autorisation
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin d'un système d'authentification et d'autorisation:

1. Une classe Auth pour gérer l'authentification
2. Un système de middleware pour protéger les routes
3. Gestion des différents rôles (admin, éditeur, utilisateur standard)
4. Pages de login/logout/inscription
5. Protection CSRF pour les formulaires
6. Récupération de mot de passe
7. Verification d'email

Privilégiez la sécurité et suivez les bonnes pratiques OWASP.

Étape 7: Fonctionnalités spécifiques à l'escalade
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin d'implémenter les fonctionnalités métier suivantes:

1. Système de gestion des expositions (points cardinaux) pour les secteurs
2. Système de périodes recommandées (mois de l'année) pour les secteurs
3. Système de tags pour catégoriser les secteurs et voies
4. Système de conversion des niveaux de difficulté entre différents systèmes (français, américain, etc.)
5. Système de rapports de condition (état actuel des secteurs/voies)
6. Système d'alertes pour les secteurs/voies (travaux, dangers, etc.)

Pour chaque système, j'ai besoin des modèles, contrôleurs, services et vues associés.

Étape 8: Gestion des médias
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin d'un système de gestion des médias:

1. Une classe MediaManager pour gérer l'upload, le redimensionnement et la suppression des images
2. Support pour différents types de médias (image, PDF, etc.)
3. Stockage des métadonnées dans la base de données
4. Association des médias à différentes entités (secteurs, voies)
5. Différents types de relations (image principale, galerie, topo)
6. Gestion des miniatures
7. Interface utilisateur pour l'upload et la gestion des médias

Utilise la bibliothèque Intervention/Image pour la manipulation d'images.

Étape 9: API et interactivité AJAX
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin d'une API REST et de fonctionnalités AJAX:

1. Une classe ApiController de base pour gérer les réponses JSON
2. Points d'API RESTful pour les principales entités (secteurs, voies)
3. Authentification API avec tokens
4. Validation des données d'entrée
5. Gestion des erreurs standardisée
6. Exemples d'interactions AJAX:
   - Filtrage dynamique des secteurs
   - Soumission de formulaires sans rechargement
   - Chargement progressif des données (lazy loading)
   - Système de suggestions de recherche

Utilise Fetch API côté client et JSON pour les échanges de données.

Étape 10: Tests et assurance qualité
Prompt pour Claude:
Pour mon application d'escalade TopoclimbCH, j'ai besoin de mettre en place des tests:

1. Configuration de PHPUnit pour les tests unitaires
2. Classe de test de base avec fonctions utilitaires
3. Tests unitaires pour les modèles principaux
4. Tests fonctionnels pour les contrôleurs
5. Tests d'intégration pour l'API
6. Mocks et stubs pour simuler les dépendances
7. Configuration pour l'intégration continue

Fournir des exemples de tests pour les classes Sector et Route.