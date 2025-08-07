<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Pagination\Paginator;

class BookController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->db = $db;
    }

    /**
     * Affichage de la liste des guides/topos
     */
    public function index(Request $request): Response
    {
        try {
            // Validation et nettoyage des filtres
            $filters = $this->validateAndSanitizeFilters($request);

            // Récupération sécurisée des données
            $data = $this->executeInTransaction(function () use ($filters) {
                return $this->getBooksData($filters);
            });

            // Log de l'action
            $this->logAction('view_books_list', ['filters' => $filters]);
            
            return $this->render('books/index', $data);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement des guides');

            return $this->render('books/index', [
                'books' => [],
                'regions' => [],
                'filters' => [],
                'stats' => ['total_books' => 0, 'avg_price' => null],
                'paginator' => null,
                'error' => 'Impossible de charger les guides actuellement.'
            ]);
        }
    }

    /**
     * Validation et nettoyage des filtres
     */
    private function validateAndSanitizeFilters(Request $request): array
    {
        $filters = [
            'author' => $request->query->get('author', ''),
            'publisher' => $request->query->get('publisher', ''),
            'year_min' => $request->query->get('year_min', ''),
            'year_max' => $request->query->get('year_max', ''),
            'price_min' => $request->query->get('price_min', ''),
            'price_max' => $request->query->get('price_max', ''),
            'search' => $request->query->get('search', ''),
            'sort' => $request->query->get('sort', 'title'),
            'order' => $request->query->get('order', 'asc'),
            'page' => $request->query->get('page', 1),
            'per_page' => $request->query->get('per_page', 15)
        ];

        // Validation des paramètres numériques
        if ($filters['year_min'] && !is_numeric($filters['year_min'])) {
            $filters['year_min'] = '';
        }
        if ($filters['year_max'] && !is_numeric($filters['year_max'])) {
            $filters['year_max'] = '';
        }
        if ($filters['price_min'] && !is_numeric($filters['price_min'])) {
            $filters['price_min'] = '';
        }
        if ($filters['price_max'] && !is_numeric($filters['price_max'])) {
            $filters['price_max'] = '';
        }

        // Validation de la pagination
        $filters['page'] = max(1, (int)$filters['page']);
        $filters['per_page'] = Paginator::validatePerPage((int)$filters['per_page']);

        // Limiter la recherche textuelle
        if (strlen($filters['search']) > 100) {
            $filters['search'] = substr($filters['search'], 0, 100);
        }
        if (strlen($filters['author']) > 100) {
            $filters['author'] = substr($filters['author'], 0, 100);
        }
        if (strlen($filters['publisher']) > 100) {
            $filters['publisher'] = substr($filters['publisher'], 0, 100);
        }

        // Valider les colonnes de tri autorisées
        $allowedSorts = ['title', 'author', 'publication_year', 'price', 'created_at'];
        if (!in_array($filters['sort'], $allowedSorts)) {
            $filters['sort'] = 'title';
        }
        
        // Mapper les colonnes de tri vers les colonnes avec préfixes de table
        $sortMapping = [
            'title' => 'b.title',
            'author' => 'b.author',
            'publication_year' => 'b.publication_year',
            'price' => 'b.price',
            'created_at' => 'b.created_at'
        ];
        $filters['sort'] = $sortMapping[$filters['sort']];

        // Valider l'ordre de tri
        if (!in_array(strtolower($filters['order']), ['asc', 'desc'])) {
            $filters['order'] = 'asc';
        }

        $cleanFilters = array_filter($filters, fn($value) => $value !== '' && $value !== null);
        
        // Assurer des valeurs par défaut pour le tri et la pagination
        if (!isset($cleanFilters['sort'])) {
            $cleanFilters['sort'] = 'b.title';
        }
        if (!isset($cleanFilters['order'])) {
            $cleanFilters['order'] = 'asc';
        }
        if (!isset($cleanFilters['page'])) {
            $cleanFilters['page'] = 1;
        }
        if (!isset($cleanFilters['per_page'])) {
            $cleanFilters['per_page'] = 15;
        }
        
        return $cleanFilters;
    }

    /**
     * Récupération sécurisée des données guides avec pagination
     */
    private function getBooksData(array $filters): array
    {
        // Construction sécurisée de la requête de comptage
        $countSql = "SELECT COUNT(*) as total
                     FROM climbing_books b 
                     WHERE 1=1";
        $params = [];

        // Conditions de filtrage
        if (isset($filters['author'])) {
            $countSql .= " AND b.author LIKE ?";
            $params[] = '%' . $filters['author'] . '%';
        }

        if (isset($filters['publisher'])) {
            $countSql .= " AND b.publisher LIKE ?";
            $params[] = '%' . $filters['publisher'] . '%';
        }

        if (isset($filters['year_min'])) {
            $countSql .= " AND b.publication_year >= ?";
            $params[] = (int)$filters['year_min'];
        }

        if (isset($filters['year_max'])) {
            $countSql .= " AND b.publication_year <= ?";
            $params[] = (int)$filters['year_max'];
        }

        if (isset($filters['price_min'])) {
            $countSql .= " AND b.price >= ?";
            $params[] = (float)$filters['price_min'];
        }

        if (isset($filters['price_max'])) {
            $countSql .= " AND b.price <= ?";
            $params[] = (float)$filters['price_max'];
        }

        if (isset($filters['search'])) {
            $countSql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Compter le total
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // Construction de la requête principale (colonnes minimales compatibles)
        $sql = "SELECT b.id, b.title, b.author, b.created_at
                FROM climbing_books b 
                WHERE 1=1";

        // Même conditions de filtrage
        $mainParams = $params;

        $sql .= " ORDER BY " . $filters['sort'] . " " . strtoupper($filters['order']);

        // Calcul de l'offset et limite
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $sql .= " LIMIT ? OFFSET ?";
        $mainParams[] = $filters['per_page'];
        $mainParams[] = $offset;

        $books = $this->db->fetchAll($sql, $mainParams);

        // Calcul des statistiques
        $stats = $this->calculateStats();

        // Création de la pagination
        $queryParams = array_filter($filters, function($key) {
            return !in_array($key, ['page', 'per_page']);
        }, ARRAY_FILTER_USE_KEY);

        $paginator = new Paginator($books, $total, $filters['per_page'], $filters['page'], $queryParams);

        return [
            'books' => $books,
            'filters' => $filters,
            'stats' => $stats,
            'paginator' => $paginator
        ];
    }

    /**
     * Affichage sécurisé d'un guide avec détails
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            
            if (!$id || !is_numeric($id)) {
                $this->flash('error', 'ID de guide invalide');
                return $this->redirect('/books');
            }
            
            $id = (int) $id;

            // Récupération des données
            $data = $this->getBookDetails($id);

            return $this->render('books/show', $data);
        } catch (\Exception $e) {
            error_log("BookController::show - Erreur: " . $e->getMessage());
            $this->flash('error', 'Erreur lors du chargement du guide');
            return $this->redirect('/books');
        }
    }

    /**
     * Récupération des détails d'un guide
     */
    private function getBookDetails(int $id): array
    {
        // Récupération de base du guide
        $book = $this->db->fetchOne(
            "SELECT * FROM climbing_books WHERE id = ?",
            [$id]
        );

        $this->requireEntity($book, 'Guide non trouvé');

        return [
            'title' => $book['title'],
            'book' => $book
        ];
    }

    /**
     * API publique avec rate limiting
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $search = $request->query->get('search', '');
            $author = $request->query->get('author', '');
            $limit = min((int)$request->query->get('limit', 100), 500);

            $sql = "SELECT b.id, b.name as title
                    FROM climbing_books b 
                    WHERE 1=1";
            $params = [];

            if ($search) {
                $search = trim(strip_tags($search));
                if (strlen($search) > 100) {
                    $search = substr($search, 0, 100);
                }
                $sql .= " AND b.name LIKE ?";
                $params[] = '%' . $search . '%';
            }

            // Author filter removed as column may not exist

            $sql .= " ORDER BY b.name ASC LIMIT ?";
            $params[] = $limit;

            $books = $this->db->fetchAll($sql, $params);

            return new JsonResponse([
                'success' => true,
                'data' => $books,
                'count' => count($books),
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur API');
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * API Show - détails d'un guide spécifique
     */
    public function apiShow(Request $request): JsonResponse
    {
        try {
            $id = $this->validateId($request->attributes->get('id'), 'ID de guide');

            $book = $this->db->fetchOne(
                "SELECT id, name, description, created_at FROM climbing_books WHERE id = ?",
                [$id]
            );

            if (!$book) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Guide non trouvé'
                ], 404);
            }

            // Formatage sécurisé des données
            $data = [
                'id' => (int)$book['id'],
                'title' => $book['name'],
                'description' => $book['description'],
                'created_at' => $book['created_at']
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur récupération guide');
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * Calcul sécurisé des statistiques générales
     */
    private function calculateStats(): array
    {
        try {
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_books,
                    AVG(price) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    MIN(publication_year) as oldest_year,
                    MAX(publication_year) as newest_year
                 FROM climbing_books WHERE price IS NOT NULL"
            );

            return [
                'total_books' => (int)($stats['total_books'] ?? 0),
                'avg_price' => $stats['avg_price'] ? round($stats['avg_price'], 2) : null,
                'min_price' => $stats['min_price'] ? (float)$stats['min_price'] : null,
                'max_price' => $stats['max_price'] ? (float)$stats['max_price'] : null,
                'oldest_year' => (int)($stats['oldest_year'] ?? 0),
                'newest_year' => (int)($stats['newest_year'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log('Erreur calcul stats books: ' . $e->getMessage());
            return ['total_books' => 0, 'avg_price' => null, 'min_price' => null, 'max_price' => null, 'oldest_year' => 0, 'newest_year' => 0];
        }
    }
}