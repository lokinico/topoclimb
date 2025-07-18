<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Models\EquipmentCategory;
use TopoclimbCH\Models\EquipmentType;
use TopoclimbCH\Models\EquipmentKit;
use TopoclimbCH\Models\EquipmentKitItem;
use TopoclimbCH\Models\EquipmentRecommendation;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class EquipmentController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        ?Database $db = null,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * Page d'accueil de la gestion d'équipement
     */
    public function index(Request $request): Response
    {
        try {
            // Données de test pour commencer
            $categories = [];
            $publicKits = [];
            $userKits = [];
            
            // Test simple avec template
            return $this->render('equipment/index', [
                'page_title' => 'Gestion d\'équipement',
                'categories' => $categories,
                'public_kits' => $publicKits,
                'user_kits' => $userKits
            ]);
        } catch (\Exception $e) {
            // Fallback en cas d'erreur
            $response = new \TopoclimbCH\Core\Response();
            $response->setContent('<html><body><h1>Gestion d\'équipement</h1><p>Erreur: ' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
            $response->headers->set('Content-Type', 'text/html');
            return $response;
        }
    }

    /**
     * Liste des catégories d'équipement
     */
    public function categories(Request $request): Response
    {
        if (!$this->auth || !$this->auth->check() || !in_array($this->auth->role(), [0, 1])) {
            $this->flash('error', 'Accès non autorisé');
            return $this->redirect('/equipment');
        }
        
        $categories = EquipmentCategory::getAllSorted();
        
        return $this->render('equipment/categories', [
            'page_title' => 'Catégories d\'équipement',
            'categories' => $categories
        ]);
    }

    /**
     * Formulaire de création de catégorie
     */
    public function createCategory(Request $request): Response
    {
        if (!$this->auth || !$this->auth->check() || !in_array($this->auth->role(), [0, 1])) {
            $this->flash('error', 'Accès non autorisé');
            return $this->redirect('/equipment');
        }
        
        if ($request->isMethod('POST')) {
            $data = [
                'name' => $request->request->get('name'),
                'description' => $request->request->get('description'),
                'sort_order' => $request->request->get('sort_order')
            ];
            
            try {
                $category = EquipmentCategory::create($data);
                if ($category) {
                    return $this->redirect('/equipment/categories?success=category_created');
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        return $this->render('equipment/category_form', [
            'page_title' => 'Nouvelle catégorie',
            'error' => $error ?? null
        ]);
    }

    /**
     * Liste des types d'équipement
     */
    public function types(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator', 'editor'])) {
            return $this->unauthorized('Accès non autorisé');
        }
        
        $categoryId = $request->query->get('category');
        $types = $categoryId ? EquipmentType::getByCategory($categoryId) : EquipmentType::getAllSorted();
        $categories = EquipmentCategory::getAllSorted();
        
        return $this->render('equipment/types.twig', [
            'page_title' => 'Types d\'équipement',
            'types' => $types,
            'categories' => $categories,
            'selected_category' => $categoryId
        ]);
    }

    /**
     * Formulaire de création de type d'équipement
     */
    public function createType(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator', 'editor'])) {
            return $this->unauthorized('Accès non autorisé');
        }
        
        $categories = EquipmentCategory::getAllSorted();
        
        if ($request->isMethod('POST')) {
            $data = [
                'category_id' => $request->request->get('category_id'),
                'name' => $request->request->get('name'),
                'description' => $request->request->get('description'),
                'icon' => $request->request->get('icon'),
                'sort_order' => $request->request->get('sort_order')
            ];
            
            try {
                $type = EquipmentType::create($data);
                if ($type) {
                    return $this->redirect('/equipment/types?success=type_created');
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        return $this->render('equipment/type_form.twig', [
            'page_title' => 'Nouveau type d\'équipement',
            'categories' => $categories,
            'error' => $error ?? null
        ]);
    }

    /**
     * Liste des kits d'équipement
     */
    public function kits(Request $request): Response
    {
        $publicKits = EquipmentKit::getPublicKits();
        
        $userKits = [];
        if ($this->auth && $this->auth->check()) {
            $userId = $this->auth->id();
            $userKits = EquipmentKit::getByUser($userId);
        }
        
        return $this->render('equipment/kits', [
            'page_title' => 'Kits d\'équipement',
            'public_kits' => $publicKits,
            'user_kits' => $userKits
        ]);
    }

    /**
     * Affichage d'un kit d'équipement
     */
    public function showKit(Request $request): Response
    {
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return $this->notFound('Kit non trouvé');
        }
        
        $items = $kit->getItemsByCategory();
        $canEdit = false;
        
        if ($this->auth && $this->auth->check()) {
            $userId = $this->auth->id();
            $canEdit = $kit->canEdit($userId) || in_array($this->auth->role(), [0, 1]);
        }
        
        return $this->render('equipment/kit_show', [
            'page_title' => $kit->name,
            'kit' => $kit,
            'items' => $items,
            'can_edit' => $canEdit
        ]);
    }

    /**
     * Formulaire de création/édition de kit
     */
    public function editKit(Request $request): Response
    {
        $this->requireAuth();
        
        $kitId = $request->attributes->get('id');
        $kit = null;
        $userId = $this->auth->id();
        
        if ($kitId) {
            $kit = EquipmentKit::find($kitId);
            if (!$kit || (!$kit->canEdit($userId) && !in_array($this->auth->role(), [0, 1]))) {
                $this->flash('error', 'Accès non autorisé');
                return $this->redirect('/equipment');
            }
        }
        
        if ($request->isMethod('POST')) {
            $data = [
                'name' => $request->request->get('name'),
                'description' => $request->request->get('description'),
                'is_public' => $request->request->get('is_public') ? 1 : 0
            ];
            
            try {
                if ($kit) {
                    $success = $kit->update($data);
                    $redirectUrl = "/equipment/kits/{$kit->id}?success=kit_updated";
                } else {
                    $data['created_by'] = $userId;
                    $kit = EquipmentKit::create($data);
                    $success = $kit !== null;
                    $redirectUrl = "/equipment/kits/{$kit->id}?success=kit_created";
                }
                
                if ($success) {
                    return $this->redirect($redirectUrl);
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        $categories = EquipmentCategory::getCategoriesWithTypes();
        
        return $this->render('equipment/kit_form', [
            'page_title' => $kit ? 'Modifier le kit' : 'Nouveau kit',
            'kit' => $kit,
            'categories' => $categories,
            'error' => $error ?? null
        ]);
    }

    /**
     * Ajouter un item à un kit (AJAX)
     */
    public function addKitItem(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return new JsonResponse(['error' => 'Kit non trouvé'], 404);
        }
        
        $userId = $this->auth->id();
        if (!$kit->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        try {
            $success = $kit->addItem(
                $data['equipment_type_id'],
                $data['quantity'],
                $data['notes'] ?? null
            );
            
            if ($success) {
                return new JsonResponse(['success' => true]);
            } else {
                return new JsonResponse(['error' => 'Erreur lors de l\'ajout'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Supprimer un item d'un kit (AJAX)
     */
    public function removeKitItem(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return new JsonResponse(['error' => 'Kit non trouvé'], 404);
        }
        
        $userId = $this->auth->id();
        if (!$kit->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        $success = $kit->removeItem($data['equipment_type_id']);
        
        if ($success) {
            return new JsonResponse(['success' => true]);
        } else {
            return new JsonResponse(['error' => 'Erreur lors de la suppression'], 500);
        }
    }

    /**
     * Dupliquer un kit
     */
    public function duplicateKit(Request $request): Response
    {
        $this->requireAuth();
        
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return $this->notFound('Kit non trouvé');
        }
        
        $userId = $this->auth->id();
        $newName = $request->request->get('name') ?: $kit->name . ' (copie)';
        
        try {
            $newKit = $kit->duplicate($userId, $newName);
            if ($newKit) {
                return $this->redirect("/equipment/kits/{$newKit->id}?success=kit_duplicated");
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        return $this->redirect("/equipment/kits/{$kitId}?error=" . urlencode($error ?? 'Erreur lors de la duplication'));
    }

    /**
     * Recommandations d'équipement pour une entité
     */
    public function recommendations(Request $request): Response
    {
        $entityType = $request->query->get('entity_type');
        $entityId = $request->query->get('entity_id');
        
        if (!$entityType || !$entityId || !EquipmentRecommendation::validateEntityType($entityType)) {
            return $this->badRequest('Paramètres invalides');
        }
        
        $recommendations = EquipmentRecommendation::getForEntityGroupedByCategory($entityType, $entityId);
        
        return $this->render('equipment/recommendations.twig', [
            'page_title' => 'Recommandations d\'équipement',
            'recommendations' => $recommendations,
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
    }

    /**
     * API: Recherche de types d'équipement
     */
    public function apiSearchTypes(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $categoryId = $request->query->get('category_id');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }
        
        if ($categoryId) {
            $types = EquipmentType::getByCategory($categoryId);
            $types = array_filter($types, function($type) use ($query) {
                return stripos($type['name'], $query) !== false || 
                       stripos($type['description'], $query) !== false;
            });
        } else {
            $types = EquipmentType::search($query);
        }
        
        return new JsonResponse($types);
    }

    /**
     * API: Obtenir les types d'équipement pour sélection
     */
    public function apiTypesForSelect(Request $request): JsonResponse
    {
        $categoryId = $request->query->get('category_id');
        $types = EquipmentType::getForSelect($categoryId);
        
        return new JsonResponse($types);
    }

    /**
     * API: Statistiques d'équipement
     */
    public function apiStats(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check() || !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $stats = [
            'categories_count' => count(EquipmentCategory::getAllSorted()),
            'types_count' => count(EquipmentType::getAllSorted()),
            'public_kits_count' => count(EquipmentKit::getPublicKits()),
            'recommendations_stats' => EquipmentRecommendation::getStats()
        ];
        
        return new JsonResponse($stats);
    }

    /**
     * Recherche globale d'équipement
     */
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $results = [];
        
        if (strlen($query) >= 2) {
            $userId = $this->auth && $this->auth->check() ? $this->auth->id() : null;
            
            $results = [
                'types' => EquipmentType::search($query),
                'kits' => EquipmentKit::search($query, $userId)
            ];
        }
        
        return $this->render('equipment/search', [
            'page_title' => 'Recherche d\'équipement',
            'query' => $query,
            'results' => $results
        ]);
    }

    /**
     * Export d'un kit en PDF/JSON
     */
    public function exportKit(Request $request): Response
    {
        $kitId = $request->attributes->get('id');
        $format = $request->query->get('format', 'json');
        
        $kit = EquipmentKit::find($kitId);
        if (!$kit) {
            return $this->notFound('Kit non trouvé');
        }
        
        $items = $kit->getItemsByCategory();
        
        if ($format === 'json') {
            $data = [
                'kit' => [
                    'name' => $kit->name,
                    'description' => $kit->description,
                    'created_at' => $kit->created_at
                ],
                'items' => $items
            ];
            
            $response = new JsonResponse($data);
            $response->headers->set('Content-Disposition', 'attachment; filename="kit_' . $kit->id . '.json"');
            return $response;
        }
        
        // TODO: Implémenter l'export PDF
        return $this->badRequest('Format non supporté');
    }
}