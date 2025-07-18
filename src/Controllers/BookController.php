<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Models\Book;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Core\Security\CsrfManager;

class BookController extends BaseController
{

    private MediaService $mediaService;

    public function __construct(
        View $view,
        Session $session,
        Database $db,
        MediaService $mediaService,
        CsrfManager $csrfManager,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->db = $db;
        $this->mediaService = $mediaService;
    }

    /**
     * Liste tous les guides/topos
     */
    public function index(Request $request): Response
    {
        try {
            $page = (int) $request->query->get('page', 1);
            $perPage = (int) $request->query->get('per_page', 20);
            $search = $request->query->get('search');
            $regionId = $request->query->get('region_id');

            // Construction de la requête
            $whereConditions = ['b.active = 1'];
            $params = [];

            if ($search) {
                $whereConditions[] = '(b.name LIKE ? OR b.code LIKE ? OR b.publisher LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if ($regionId) {
                $whereConditions[] = 'b.region_id = ?';
                $params[] = (int)$regionId;
            }

            $whereClause = implode(' AND ', $whereConditions);
            $offset = ($page - 1) * $perPage;

            // Compter le total
            $countSql = "SELECT COUNT(*) as total FROM climbing_books b WHERE {$whereClause}";
            $totalResult = $this->db->fetchOne($countSql, $params);
            $total = $totalResult['total'];

            // Récupérer les books avec statistiques
            $sql = "SELECT 
                        b.*,
                        r.name as region_name,
                        COUNT(DISTINCT bs.sector_id) as sectors_count,
                        COUNT(DISTINCT sect.site_id) as sites_count,
                        COUNT(DISTINCT rt.id) as routes_count
                    FROM climbing_books b
                    LEFT JOIN climbing_regions r ON b.region_id = r.id
                    LEFT JOIN climbing_book_sectors bs ON b.id = bs.book_id
                    LEFT JOIN climbing_sectors sect ON bs.sector_id = sect.id AND sect.active = 1
                    LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                    WHERE {$whereClause}
                    GROUP BY b.id
                    ORDER BY b.name ASC
                    LIMIT {$perPage} OFFSET {$offset}";

            $books = $this->db->fetchAll($sql, $params);

            // Pagination
            $pagination = [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ];

            // Données pour les filtres
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");

            return $this->render('books/index', [
                'title' => 'Guides d\'escalade',
                'books' => $books,
                'pagination' => $pagination,
                'regions' => $regions,
                'currentFilters' => [
                    'search' => $search,
                    'region_id' => $regionId
                ]
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue lors du chargement des guides: ' . $e->getMessage());
            return $this->render('books/index', [
                'books' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Affiche un guide spécifique avec ses secteurs
     */
    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du guide non spécifié');
            return Response::redirect('/books');
        }

        try {
            // Récupérer le book avec sa région
            $book = $this->db->fetchOne(
                "SELECT b.*, r.name as region_name 
                 FROM climbing_books b
                 LEFT JOIN climbing_regions r ON b.region_id = r.id
                 WHERE b.id = ? AND b.active = 1",
                [(int)$id]
            );

            if (!$book) {
                $this->session->flash('error', 'Guide non trouvé');
                return Response::redirect('/books');
            }

            // Récupérer les secteurs du guide avec leurs informations
            $sectors = $this->db->fetchAll(
                "SELECT 
                    sect.*,
                    si.name as site_name,
                    si.code as site_code,
                    r.name as region_name,
                    bs.page_number,
                    bs.sort_order,
                    bs.notes as book_notes,
                    COUNT(rt.id) as routes_count
                 FROM climbing_sectors sect
                 JOIN climbing_book_sectors bs ON sect.id = bs.sector_id
                 LEFT JOIN climbing_sites si ON sect.site_id = si.id
                 LEFT JOIN climbing_regions reg ON si.region_id = reg.id
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE bs.book_id = ? AND sect.active = 1
                 GROUP BY sect.id
                 ORDER BY bs.sort_order ASC, sect.name ASC",
                [(int)$id]
            );

            // Récupérer toutes les régions couvertes
            $coveredRegions = $this->db->fetchAll(
                "SELECT DISTINCT r.id, r.name
                 FROM climbing_regions r
                 JOIN climbing_sites si ON r.id = si.region_id
                 JOIN climbing_sectors sect ON si.id = sect.site_id
                 JOIN climbing_book_sectors bs ON sect.id = bs.sector_id
                 WHERE bs.book_id = ?
                 ORDER BY r.name",
                [(int)$id]
            );

            // Calculer les statistiques
            $stats = [
                'sectors_count' => count($sectors),
                'sites_count' => count(array_unique(array_column($sectors, 'site_id'))),
                'regions_count' => count($coveredRegions),
                'routes_count' => array_sum(array_column($sectors, 'routes_count')),
                'difficulties' => [],
                'page_range' => null
            ];

            // Plage de pages
            $pageNumbers = array_filter(array_column($sectors, 'page_number'));
            if (!empty($pageNumbers)) {
                $stats['page_range'] = [
                    'min' => min($pageNumbers),
                    'max' => max($pageNumbers)
                ];
            }

            // Récupérer les difficultés
            if ($stats['routes_count'] > 0) {
                $difficulties = $this->db->fetchAll(
                    "SELECT DISTINCT rt.difficulty, COUNT(*) as count
                     FROM climbing_routes rt
                     JOIN climbing_sectors sect ON rt.sector_id = sect.id
                     JOIN climbing_book_sectors bs ON sect.id = bs.sector_id
                     WHERE bs.book_id = ? AND rt.active = 1 AND rt.difficulty IS NOT NULL
                     GROUP BY rt.difficulty
                     ORDER BY rt.difficulty",
                    [(int)$id]
                );
                $stats['difficulties'] = $difficulties;
            }

            return $this->render('books/show', [
                'title' => $book['name'],
                'book' => $book,
                'sectors' => $sectors,
                'coveredRegions' => $coveredRegions,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/books');
        }
    }

    /**
     * Formulaire de création/édition d'un guide
     */
    public function form(Request $request): Response
    {
        $id = $request->attributes->get('id');
        $isEdit = !is_null($id);

        try {
            $book = null;
            if ($isEdit) {
                $book = $this->db->fetchOne(
                    "SELECT * FROM climbing_books WHERE id = ? AND active = 1",
                    [(int)$id]
                );

                if (!$book) {
                    $this->session->flash('error', 'Guide non trouvé');
                    return Response::redirect('/books');
                }
            }

            // Données pour le formulaire
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");

            return $this->render('books/form', [
                'title' => $isEdit ? 'Modifier le guide ' . $book['name'] : 'Créer un nouveau guide',
                'book' => $book,
                'regions' => $regions,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/books');
        }
    }

    /**
     * Formulaire de création de guide (version test sans authentification)
     */
    public function testCreate(Request $request): Response
    {
        error_log("BookController::testCreate called");
        // Version fallback avec formulaire HTML simple (forcé pour le test)
        $html = '<form method="post">
            <input type="hidden" name="csrf_token" value="test">
            <input type="text" name="name" placeholder="Nom du guide" required>
            <textarea name="description" placeholder="Description"></textarea>
            <input type="text" name="author" placeholder="Auteur">
            <input type="number" name="year" placeholder="Année" min="1900" max="2099">
            <input type="text" name="publisher" placeholder="Éditeur">
            <input type="text" name="isbn" placeholder="ISBN">
            <input type="text" name="language" placeholder="Langue">
            <input type="checkbox" name="active" value="1" checked>
            <button type="submit">Créer</button>
        </form>';
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Création d'un nouveau guide
     */
    public function store(Request $request): Response
    {
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/books/create');
        }

        $data = $request->request->all();

        // Validation basique
        if (empty($data['name'])) {
            $this->session->flash('error', 'Le nom du guide est obligatoire');
            return Response::redirect('/books/create');
        }

        try {
            $data['created_by'] = $_SESSION['auth_user_id'] ?? 1;

            // Vérifier l'unicité du code si fourni
            if (!empty($data['code'])) {
                $existingCode = $this->db->fetchOne(
                    "SELECT id FROM climbing_books WHERE code = ? AND active = 1",
                    [$data['code']]
                );

                if ($existingCode) {
                    $this->session->flash('error', 'Le code "' . $data['code'] . '" est déjà utilisé');
                    return Response::redirect('/books/create');
                }
            }

            // Préparer les données
            $bookData = [
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'region_id' => !empty($data['region_id']) ? (int)$data['region_id'] : null,
                'year' => !empty($data['year']) ? (int)$data['year'] : null,
                'publisher' => $data['publisher'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $bookId = $this->db->insert('climbing_books', $bookData);

            if (!$bookId) {
                $this->session->flash('error', 'Erreur lors de la création du guide');
                return Response::redirect('/books/create');
            }

            $this->session->flash('success', 'Guide créé avec succès');
            return Response::redirect('/books/' . $bookId);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors de la création du guide: ' . $e->getMessage());
            return Response::redirect('/books/create');
        }
    }

    /**
     * Mise à jour d'un guide existant
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du guide non spécifié');
            return Response::redirect('/books');
        }

        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/books/' . $id . '/edit');
        }

        $data = $request->request->all();

        // Validation basique
        if (empty($data['name'])) {
            $this->session->flash('error', 'Le nom du guide est obligatoire');
            return Response::redirect('/books/' . $id . '/edit');
        }

        try {
            // Vérifier l'unicité du code si fourni (excluant le guide actuel)
            if (!empty($data['code'])) {
                $existingCode = $this->db->fetchOne(
                    "SELECT id FROM climbing_books WHERE code = ? AND active = 1 AND id != ?",
                    [$data['code'], (int)$id]
                );

                if ($existingCode) {
                    $this->session->flash('error', 'Le code "' . $data['code'] . '" est déjà utilisé');
                    return Response::redirect('/books/' . $id . '/edit');
                }
            }

            // Préparer les données de mise à jour
            $updateData = [
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'region_id' => !empty($data['region_id']) ? (int)$data['region_id'] : null,
                'year' => !empty($data['year']) ? (int)$data['year'] : null,
                'publisher' => $data['publisher'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'active' => isset($data['active']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $success = $this->db->update('climbing_books', $updateData, 'id = ?', [(int)$id]);

            if (!$success) {
                throw new \Exception("Échec de la mise à jour du guide");
            }

            $this->session->flash('success', 'Guide mis à jour avec succès');
            return Response::redirect('/books/' . $id);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            return Response::redirect('/books/' . $id . '/edit');
        }
    }

    /**
     * Ajouter un secteur au guide
     */
    public function addSector(Request $request): Response
    {
        $bookId = $request->attributes->get('id');

        if (!$bookId) {
            return Response::json([
                'success' => false,
                'error' => 'ID du guide non spécifié'
            ], 400);
        }

        if (!$this->validateCsrfToken($request)) {
            return Response::json([
                'success' => false,
                'error' => 'Token de sécurité invalide'
            ], 403);
        }

        $data = $request->request->all();

        if (empty($data['sector_id'])) {
            return Response::json([
                'success' => false,
                'error' => 'ID du secteur requis'
            ], 400);
        }

        try {
            // Vérifier que le secteur existe
            $sector = $this->db->fetchOne(
                "SELECT id, name FROM climbing_sectors WHERE id = ? AND active = 1",
                [(int)$data['sector_id']]
            );

            if (!$sector) {
                return Response::json([
                    'success' => false,
                    'error' => 'Secteur non trouvé'
                ], 404);
            }

            // Vérifier que la relation n'existe pas déjà
            $existing = $this->db->fetchOne(
                "SELECT id FROM climbing_book_sectors WHERE book_id = ? AND sector_id = ?",
                [(int)$bookId, (int)$data['sector_id']]
            );

            if ($existing) {
                return Response::json([
                    'success' => false,
                    'error' => 'Ce secteur est déjà dans le guide'
                ], 409);
            }

            // Ajouter la relation
            $relationData = [
                'book_id' => (int)$bookId,
                'sector_id' => (int)$data['sector_id'],
                'page_number' => !empty($data['page_number']) ? (int)$data['page_number'] : null,
                'sort_order' => !empty($data['sort_order']) ? (int)$data['sort_order'] : 0,
                'notes' => $data['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $relationId = $this->db->insert('climbing_book_sectors', $relationData);

            if (!$relationId) {
                return Response::json([
                    'success' => false,
                    'error' => 'Erreur lors de l\'ajout du secteur'
                ], 500);
            }

            return Response::json([
                'success' => true,
                'message' => 'Secteur ajouté au guide avec succès',
                'data' => [
                    'relation_id' => $relationId,
                    'sector_name' => $sector['name']
                ]
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de l\'ajout du secteur'
            ], 500);
        }
    }

    /**
     * Supprimer un secteur du guide
     */
    public function removeSector(Request $request): Response
    {
        $bookId = $request->attributes->get('id');
        $sectorId = $request->request->get('sector_id');

        if (!$bookId || !$sectorId) {
            return Response::json([
                'success' => false,
                'error' => 'IDs manquants'
            ], 400);
        }

        if (!$this->validateCsrfToken($request)) {
            return Response::json([
                'success' => false,
                'error' => 'Token de sécurité invalide'
            ], 403);
        }

        try {
            $success = $this->db->delete(
                'climbing_book_sectors',
                'book_id = ? AND sector_id = ?',
                [(int)$bookId, (int)$sectorId]
            );

            if ($success) {
                return Response::json([
                    'success' => true,
                    'message' => 'Secteur retiré du guide'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'error' => 'Relation non trouvée'
                ], 404);
            }
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Sélecteur de secteurs pour ajouter au guide
     */
    public function sectorSelector(Request $request): Response
    {
        $bookId = $request->attributes->get('id');
        $search = $request->query->get('search', '');
        $regionId = $request->query->get('region_id');

        if (!$bookId) {
            $this->session->flash('error', 'ID du guide non spécifié');
            return Response::redirect('/books');
        }

        try {
            // Récupérer le guide
            $book = $this->db->fetchOne(
                "SELECT * FROM climbing_books WHERE id = ? AND active = 1",
                [(int)$bookId]
            );

            if (!$book) {
                $this->session->flash('error', 'Guide non trouvé');
                return Response::redirect('/books');
            }

            // Construire la requête pour les secteurs disponibles
            $whereConditions = ['sect.active = 1'];
            $params = [];

            // Exclure les secteurs déjà dans le guide
            $whereConditions[] = 'sect.id NOT IN (
                SELECT sector_id FROM climbing_book_sectors WHERE book_id = ?
            )';
            $params[] = (int)$bookId;

            if ($search) {
                $whereConditions[] = '(sect.name LIKE ? OR sect.code LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if ($regionId) {
                $whereConditions[] = 'si.region_id = ?';
                $params[] = (int)$regionId;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Récupérer les secteurs disponibles
            $availableSectors = $this->db->fetchAll(
                "SELECT 
                    sect.*,
                    si.name as site_name,
                    si.code as site_code,
                    r.name as region_name,
                    COUNT(rt.id) as routes_count
                 FROM climbing_sectors sect
                 LEFT JOIN climbing_sites si ON sect.site_id = si.id
                 LEFT JOIN climbing_regions r ON si.region_id = r.id
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE {$whereClause}
                 GROUP BY sect.id
                 ORDER BY r.name, si.name, sect.name
                 LIMIT 50",
                $params
            );

            // Récupérer les régions pour le filtre
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");

            return $this->render('books/sector-selector', [
                'title' => 'Ajouter secteurs au guide ' . $book['name'],
                'book' => $book,
                'availableSectors' => $availableSectors,
                'regions' => $regions,
                'currentFilters' => [
                    'search' => $search,
                    'region_id' => $regionId
                ],
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/books/' . $bookId);
        }
    }

    /**
     * API: Liste des guides avec pagination
     */
    public function apiIndex(Request $request): Response
    {
        try {
            $limit = min((int)($request->query->get('limit') ?? 50), 200);
            $offset = (int)($request->query->get('offset') ?? 0);
            $regionId = $request->query->get('region_id');
            
            $sql = "SELECT b.id, b.name, b.code, b.year, b.publisher, b.isbn, 
                           b.created_at, r.name as region_name, r.id as region_id,
                           COUNT(DISTINCT bs.sector_id) as sectors_count,
                           COUNT(DISTINCT rt.id) as routes_count
                    FROM climbing_books b
                    LEFT JOIN climbing_regions r ON b.region_id = r.id
                    LEFT JOIN climbing_book_sectors bs ON b.id = bs.book_id
                    LEFT JOIN climbing_sectors sect ON bs.sector_id = sect.id AND sect.active = 1
                    LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                    WHERE b.active = 1";
            
            $params = [];
            
            if ($regionId) {
                $sql .= " AND b.region_id = ?";
                $params[] = (int)$regionId;
            }
            
            $sql .= " GROUP BY b.id ORDER BY b.name ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $books = $this->db->fetchAll($sql, $params);
            
            // Compter le total pour la pagination
            $countSql = "SELECT COUNT(*) as total FROM climbing_books b WHERE b.active = 1";
            $countParams = [];
            
            if ($regionId) {
                $countSql .= " AND b.region_id = ?";
                $countParams[] = (int)$regionId;
            }
            
            $total = $this->db->fetchOne($countSql, $countParams)['total'] ?? 0;
            
            return Response::json([
                'success' => true,
                'data' => $books,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
        } catch (\Exception $e) {
            error_log('BookController::apiIndex error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors du chargement des guides'
            ], 500);
        }
    }

    /**
     * API: Recherche de guides
     */
    public function apiSearch(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $limit = min((int)$request->query->get('limit', 10), 50);

        if (strlen($query) < 2) {
            return Response::json([
                'success' => true,
                'data' => []
            ]);
        }

        try {
            $searchTerm = '%' . $query . '%';
            $books = $this->db->fetchAll(
                "SELECT b.*, r.name as region_name
                 FROM climbing_books b
                 LEFT JOIN climbing_regions r ON b.region_id = r.id
                 WHERE b.active = 1 
                 AND (b.name LIKE ? OR b.code LIKE ? OR b.publisher LIKE ?)
                 ORDER BY b.name ASC
                 LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $limit]
            );

            return Response::json([
                'success' => true,
                'data' => $books
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * API: Récupère les secteurs d'un guide
     */
    public function apiSectors(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            if (!$id) {
                return Response::json([
                    'success' => false,
                    'error' => 'ID du guide non spécifié'
                ], 400);
            }

            // Vérifier que le guide existe
            $book = $this->db->fetchOne(
                "SELECT id, name FROM climbing_books WHERE id = ? AND active = 1",
                [(int)$id]
            );

            if (!$book) {
                return Response::json([
                    'success' => false,
                    'error' => 'Guide non trouvé'
                ], 404);
            }

            // Récupérer les secteurs du guide
            $sectors = $this->db->fetchAll(
                "SELECT 
                    sect.id,
                    sect.name,
                    sect.code,
                    sect.altitude,
                    sect.coordinates_lat,
                    sect.coordinates_lng,
                    si.name as site_name,
                    r.name as region_name,
                    bs.page_number,
                    bs.sort_order,
                    bs.notes as book_notes,
                    COUNT(rt.id) as routes_count
                 FROM climbing_sectors sect
                 JOIN climbing_book_sectors bs ON sect.id = bs.sector_id
                 LEFT JOIN climbing_sites si ON sect.site_id = si.id
                 LEFT JOIN climbing_regions r ON si.region_id = r.id
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE bs.book_id = ? AND sect.active = 1
                 GROUP BY sect.id
                 ORDER BY bs.sort_order ASC, sect.name ASC",
                [(int)$id]
            );

            return Response::json([
                'success' => true,
                'data' => $sectors,
                'book' => [
                    'id' => (int)$book['id'],
                    'name' => $book['name']
                ],
                'count' => count($sectors)
            ]);
        } catch (\Exception $e) {
            error_log('BookController::apiSectors error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des secteurs'
            ], 500);
        }
    }
}
