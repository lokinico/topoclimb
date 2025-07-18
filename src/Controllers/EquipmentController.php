<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;
use TopoclimbCH\Models\EquipmentCategory;
use TopoclimbCH\Models\EquipmentType;
use TopoclimbCH\Models\EquipmentKit;
use TopoclimbCH\Models\EquipmentKitItem;
use TopoclimbCH\Models\EquipmentRecommendation;
use TopoclimbCH\Services\AuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class EquipmentController extends BaseController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Page d'accueil de la gestion d'équipement
     */
    public function index(Request $request): Response
    {
        $categories = EquipmentCategory::getCategoriesWithTypes();
        $publicKits = EquipmentKit::getPublicKits();
        
        $userKits = [];
        if ($this->authService->isAuthenticated()) {
            $userId = $this->authService->getCurrentUserId();
            $userKits = EquipmentKit::getByUser($userId);
        }
        
        return $this->render('equipment/index.twig', [
            'page_title' => 'Gestion d\'équipement',
            'categories' => $categories,
            'public_kits' => $publicKits,
            'user_kits' => $userKits
        ]);
    }

    /**
     * Liste des catégories d'équipement
     */
    public function categories(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator'])) {
            return $this->unauthorized('Accès non autorisé');
        }
        
        $categories = EquipmentCategory::getAllSorted();
        
        return $this->render('equipment/categories.twig', [
            'page_title' => 'Catégories d\'équipement',
            'categories' => $categories
        ]);
    }

    /**
     * Formulaire de création de catégorie
     */
    public function createCategory(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator'])) {
            return $this->unauthorized('Accès non autorisé');
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
        
        return $this->render('equipment/category_form.twig', [
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
        if ($this->authService->isAuthenticated()) {
            $userId = $this->authService->getCurrentUserId();
            $userKits = EquipmentKit::getByUser($userId);
        }
        
        return $this->render('equipment/kits.twig', [
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
        
        if ($this->authService->isAuthenticated()) {
            $userId = $this->authService->getCurrentUserId();
            $canEdit = $kit->canEdit($userId) || $this->authService->hasRole(['admin', 'moderator']);
        }
        
        return $this->render('equipment/kit_show.twig', [
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
        if (!$this->authService->isAuthenticated()) {
            return $this->unauthorized('Connexion requise');
        }
        
        $kitId = $request->attributes->get('id');
        $kit = null;
        $userId = $this->authService->getCurrentUserId();
        
        if ($kitId) {
            $kit = EquipmentKit::find($kitId);
            if (!$kit || (!$kit->canEdit($userId) && !$this->authService->hasRole(['admin', 'moderator']))) {
                return $this->unauthorized('Accès non autorisé');
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
        
        return $this->render('equipment/kit_form.twig', [
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
        if (!$this->authService->isAuthenticated()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return new JsonResponse(['error' => 'Kit non trouvé'], 404);
        }
        
        $userId = $this->authService->getCurrentUserId();
        if (!$kit->canEdit($userId) && !$this->authService->hasRole(['admin', 'moderator'])) {
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
        if (!$this->authService->isAuthenticated()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return new JsonResponse(['error' => 'Kit non trouvé'], 404);
        }
        
        $userId = $this->authService->getCurrentUserId();
        if (!$kit->canEdit($userId) && !$this->authService->hasRole(['admin', 'moderator'])) {
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
        if (!$this->authService->isAuthenticated()) {
            return $this->unauthorized('Connexion requise');
        }
        
        $kitId = $request->attributes->get('id');
        $kit = EquipmentKit::find($kitId);
        
        if (!$kit) {
            return $this->notFound('Kit non trouvé');
        }
        
        $userId = $this->authService->getCurrentUserId();
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
        if (!$this->authService->hasRole(['admin', 'moderator'])) {
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
            $userId = $this->authService->isAuthenticated() ? $this->authService->getCurrentUserId() : null;
            
            $results = [
                'types' => EquipmentType::search($query),
                'kits' => EquipmentKit::search($query, $userId)
            ];
        }
        
        return $this->render('equipment/search.twig', [
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