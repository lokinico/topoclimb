<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Models\ChecklistTemplate;
use TopoclimbCH\Models\ChecklistItem;
use TopoclimbCH\Models\UserChecklist;
use TopoclimbCH\Models\UserChecklistItem;
use TopoclimbCH\Models\EquipmentType;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Response as CoreResponse;

class ChecklistController extends BaseController
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
     * Page d'accueil des checklists
     */
    public function index(Request $request): Response
    {
        try {
            // Données de test pour commencer
            $publicTemplates = [];
            $userChecklists = [];
            
            // Test simple avec template
            return $this->render('checklists/index', [
                'page_title' => 'Checklists de sécurité',
                'public_templates' => $publicTemplates,
                'user_checklists' => $userChecklists
            ]);
        } catch (\Exception $e) {
            // Fallback en cas d'erreur de template
            $response = new CoreResponse();
            $response->setContent('<html><body><h1>Checklists de sécurité</h1><p>Erreur: ' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
            $response->headers->set('Content-Type', 'text/html');
            return $response;
        }
    }

    /**
     * Liste des templates de checklists
     */
    public function templates(Request $request): Response
    {
        $category = $request->query->get('category');
        $climbingType = $request->query->get('climbing_type');
        
        if ($category) {
            $templates = ChecklistTemplate::getByCategory($category);
        } elseif ($climbingType) {
            $templates = ChecklistTemplate::getByClimbingType($climbingType);
        } else {
            $templates = ChecklistTemplate::getPublicTemplates();
        }
        
        $userTemplates = [];
        if ($this->auth && $this->auth->check()) {
            $userId = $this->auth->id();
            $userTemplates = ChecklistTemplate::getByUser($userId);
        }
        
        return $this->render('checklists/templates', [
            'page_title' => 'Templates de checklists',
            'templates' => $templates,
            'user_templates' => $userTemplates,
            'selected_category' => $category,
            'selected_climbing_type' => $climbingType,
            'categories' => ChecklistTemplate::getValidCategories(),
            'climbing_types' => ChecklistTemplate::getValidClimbingTypes()
        ]);
    }

    /**
     * Afficher un template de checklist
     */
    public function showTemplate(Request $request): Response
    {
        $templateId = $request->attributes->get('id');
        $template = ChecklistTemplate::find($templateId);
        
        if (!$template) {
            $this->flash('error', 'Template non trouvé');
            return $this->redirect('/checklists');
        }
        
        $items = ChecklistItem::getByTemplateGrouped($templateId);
        $canEdit = false;
        
        if ($this->auth && $this->auth->check()) {
            $userId = $this->auth->id();
            $userRole = $this->auth->role();
            $canEdit = $template->canEdit($userId) || in_array($userRole, [0, 1]); // admin, moderator
        }
        
        return $this->render('checklists/template_show', [
            'page_title' => $template->name,
            'template' => $template,
            'items' => $items,
            'can_edit' => $canEdit
        ]);
    }

    /**
     * Formulaire de création/édition de template
     */
    public function editTemplate(Request $request): Response
    {
        $this->requireAuth();
        
        $templateId = $request->attributes->get('id');
        $template = null;
        $userId = $this->auth->id();
        
        if ($templateId) {
            $template = ChecklistTemplate::find($templateId);
            if (!$template || (!$template->canEdit($userId) && !in_array($this->auth->role(), [0, 1]))) {
                $this->flash('error', 'Accès non autorisé');
                return $this->redirect('/checklists');
            }
        }
        
        if ($request->isMethod('POST')) {
            $data = [
                'name' => $request->request->get('name'),
                'description' => $request->request->get('description'),
                'category' => $request->request->get('category'),
                'climbing_type' => $request->request->get('climbing_type'),
                'is_public' => $request->request->get('is_public') ? 1 : 0
            ];
            
            try {
                if ($template) {
                    $success = $template->update($data);
                    $redirectUrl = "/checklists/templates/{$template->id}?success=template_updated";
                } else {
                    $data['created_by'] = $userId;
                    $template = ChecklistTemplate::create($data);
                    $success = $template !== null;
                    $redirectUrl = "/checklists/templates/{$template->id}?success=template_created";
                }
                
                if ($success) {
                    return $this->redirect($redirectUrl);
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        return $this->render('checklists/template_form', [
            'page_title' => $template ? 'Modifier le template' : 'Nouveau template',
            'template' => $template,
            'categories' => ChecklistTemplate::getValidCategories(),
            'climbing_types' => ChecklistTemplate::getValidClimbingTypes(),
            'error' => $error ?? null
        ]);
    }

    /**
     * Créer une checklist à partir d'un template
     */
    public function createFromTemplate(Request $request): Response
    {
        $this->requireAuth();
        
        $templateId = $request->attributes->get('id');
        $template = ChecklistTemplate::find($templateId);
        
        if (!$template) {
            $this->flash('error', 'Template non trouvé');
            return $this->redirect('/checklists');
        }
        
        $userId = $this->auth->id();
        
        if ($request->isMethod('POST')) {
            $options = [
                'name' => $request->request->get('name') ?: $template->name,
                'description' => $request->request->get('description'),
                'event_id' => $request->request->get('event_id') ?: null,
                'entity_type' => $request->request->get('entity_type') ?: null,
                'entity_id' => $request->request->get('entity_id') ?: null
            ];
            
            try {
                $checklist = UserChecklist::createFromTemplate($templateId, $userId, $options);
                if ($checklist) {
                    return $this->redirect("/checklists/my/{$checklist->id}?success=checklist_created");
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        return $this->render('checklists/create_from_template', [
            'page_title' => 'Créer une checklist',
            'template' => $template,
            'error' => $error ?? null
        ]);
    }

    /**
     * Liste des checklists utilisateur
     */
    public function myChecklists(Request $request): Response
    {
        $this->requireAuth();
        
        $userId = $this->auth->id();
        $checklists = UserChecklist::getByUser($userId);
        
        return $this->render('checklists/my_checklists', [
            'page_title' => 'Mes checklists',
            'checklists' => $checklists
        ]);
    }

    /**
     * Afficher une checklist utilisateur
     */
    public function showChecklist(Request $request): Response
    {
        $this->requireAuth();
        
        $checklistId = $request->attributes->get('id');
        $checklist = UserChecklist::find($checklistId);
        
        if (!$checklist) {
            $this->flash('error', 'Checklist non trouvée');
            return $this->redirect('/checklists/my');
        }
        
        $userId = $this->auth->id();
        if (!$checklist->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            $this->flash('error', 'Accès non autorisé');
            return $this->redirect('/checklists/my');
        }
        
        $items = $checklist->getItemsByCategory();
        $progress = $checklist->getProgress();
        
        return $this->render('checklists/checklist_show', [
            'page_title' => $checklist->name,
            'checklist' => $checklist,
            'items' => $items,
            'progress' => $progress
        ]);
    }

    /**
     * Cocher/décocher un item de checklist (AJAX)
     */
    public function toggleChecklistItem(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $itemId = $request->attributes->get('id');
        $item = UserChecklistItem::find($itemId);
        
        if (!$item) {
            return new JsonResponse(['error' => 'Item non trouvé'], 404);
        }
        
        // Vérifier les permissions sur la checklist
        $checklist = UserChecklist::find($item->checklist_id);
        $userId = $this->auth->id();
        
        if (!$checklist->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        $notes = $data['notes'] ?? null;
        
        try {
            $success = $item->toggleCheck($notes);
            
            if ($success) {
                $progress = $checklist->getProgress();
                
                return new JsonResponse([
                    'success' => true,
                    'is_checked' => $item->is_checked,
                    'checked_at' => $item->checked_at,
                    'progress' => $progress
                ]);
            } else {
                return new JsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Mettre à jour les notes d'un item (AJAX)
     */
    public function updateItemNotes(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $itemId = $request->attributes->get('id');
        $item = UserChecklistItem::find($itemId);
        
        if (!$item) {
            return new JsonResponse(['error' => 'Item non trouvé'], 404);
        }
        
        // Vérifier les permissions
        $checklist = UserChecklist::find($item->checklist_id);
        $userId = $this->auth->id();
        
        if (!$checklist->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        $notes = $data['notes'] ?? '';
        
        $success = $item->updateNotes($notes);
        
        if ($success) {
            return new JsonResponse(['success' => true]);
        } else {
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    /**
     * Ajouter un item à une checklist (AJAX)
     */
    public function addChecklistItem(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $checklistId = $request->attributes->get('id');
        $checklist = UserChecklist::find($checklistId);
        
        if (!$checklist) {
            return new JsonResponse(['error' => 'Checklist non trouvée'], 404);
        }
        
        $userId = $this->auth->id();
        if (!$checklist->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        try {
            $item = UserChecklistItem::addToChecklist($checklistId, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'is_mandatory' => $data['is_mandatory'] ?? 0,
                'equipment_type_id' => $data['equipment_type_id'] ?? null
            ]);
            
            if ($item) {
                return new JsonResponse([
                    'success' => true,
                    'item' => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'category' => $item->category,
                        'is_mandatory' => $item->is_mandatory,
                        'is_checked' => $item->is_checked
                    ]
                ]);
            } else {
                return new JsonResponse(['error' => 'Erreur lors de la création'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Supprimer un item de checklist (AJAX)
     */
    public function removeChecklistItem(Request $request): JsonResponse
    {
        if (!$this->auth || !$this->auth->check()) {
            return new JsonResponse(['error' => 'Connexion requise'], 401);
        }
        
        $itemId = $request->attributes->get('id');
        $item = UserChecklistItem::find($itemId);
        
        if (!$item) {
            return new JsonResponse(['error' => 'Item non trouvé'], 404);
        }
        
        // Vérifier les permissions
        $checklist = UserChecklist::find($item->checklist_id);
        $userId = $this->auth->id();
        
        if (!$checklist->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $success = $item->delete();
        
        if ($success) {
            $progress = $checklist->getProgress();
            return new JsonResponse([
                'success' => true,
                'progress' => $progress
            ]);
        } else {
            return new JsonResponse(['error' => 'Erreur lors de la suppression'], 500);
        }
    }

    /**
     * Marquer une checklist comme complète
     */
    public function completeChecklist(Request $request): Response
    {
        $this->requireAuth();
        
        $checklistId = $request->attributes->get('id');
        $checklist = UserChecklist::find($checklistId);
        
        if (!$checklist) {
            $this->flash('error', 'Checklist non trouvée');
            return $this->redirect('/checklists/my');
        }
        
        $userId = $this->auth->id();
        if (!$checklist->canEdit($userId)) {
            $this->flash('error', 'Accès non autorisé');
            return $this->redirect('/checklists/my');
        }
        
        try {
            $success = $checklist->markAsCompleted();
            if ($success) {
                return $this->redirect("/checklists/my/{$checklistId}?success=checklist_completed");
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        return $this->redirect("/checklists/my/{$checklistId}?error=" . urlencode($error ?? 'Erreur lors de la validation'));
    }

    /**
     * Réinitialiser une checklist
     */
    public function resetChecklist(Request $request): Response
    {
        $this->requireAuth();
        
        $checklistId = $request->attributes->get('id');
        $checklist = UserChecklist::find($checklistId);
        
        if (!$checklist) {
            $this->flash('error', 'Checklist non trouvée');
            return $this->redirect('/checklists/my');
        }
        
        $userId = $this->auth->id();
        if (!$checklist->canEdit($userId)) {
            $this->flash('error', 'Accès non autorisé');
            return $this->redirect('/checklists/my');
        }
        
        $success = $checklist->reset();
        
        if ($success) {
            return $this->redirect("/checklists/my/{$checklistId}?success=checklist_reset");
        } else {
            return $this->redirect("/checklists/my/{$checklistId}?error=reset_failed");
        }
    }

    /**
     * Dupliquer une checklist
     */
    public function duplicateChecklist(Request $request): Response
    {
        $this->requireAuth();
        
        $checklistId = $request->attributes->get('id');
        $checklist = UserChecklist::find($checklistId);
        
        if (!$checklist) {
            $this->flash('error', 'Checklist non trouvée');
            return $this->redirect('/checklists/my');
        }
        
        $userId = $this->auth->id();
        $newName = $request->request->get('name') ?: $checklist->name . ' (copie)';
        
        try {
            $newChecklist = $checklist->duplicate($userId, ['name' => $newName]);
            if ($newChecklist) {
                return $this->redirect("/checklists/my/{$newChecklist->id}?success=checklist_duplicated");
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        return $this->redirect("/checklists/my/{$checklistId}?error=" . urlencode($error ?? 'Erreur lors de la duplication'));
    }

    /**
     * Recherche de checklists et templates
     */
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $results = [];
        
        if (strlen($query) >= 2) {
            $results = [
                'templates' => ChecklistTemplate::search($query),
                'items' => ChecklistItem::search($query)
            ];
        }
        
        return $this->render('checklists/search', [
            'page_title' => 'Recherche de checklists',
            'query' => $query,
            'results' => $results
        ]);
    }

    /**
     * API: Obtenir les types d'équipement pour sélection
     */
    public function apiEquipmentTypes(Request $request): JsonResponse
    {
        $categoryId = $request->query->get('category_id');
        $types = EquipmentType::getForSelect($categoryId);
        
        return new JsonResponse($types);
    }

    /**
     * API: Statistiques des checklists
     */
    public function apiStats(Request $request): JsonResponse
    {
        if (!$this->auth || !in_array($this->auth->role(), [0, 1])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }
        
        $stats = [
            'templates' => ChecklistTemplate::getStats(),
            'user_checklists' => UserChecklist::getStats(),
            'item_usage' => UserChecklistItem::getUsageStats()
        ];
        
        return new JsonResponse($stats);
    }

    /**
     * Export d'une checklist en JSON
     */
    public function exportChecklist(Request $request): Response
    {
        $this->requireAuth();
        
        $checklistId = $request->attributes->get('id');
        $checklist = UserChecklist::find($checklistId);
        
        if (!$checklist) {
            $this->flash('error', 'Checklist non trouvée');
            return $this->redirect('/checklists/my');
        }
        
        $userId = $this->auth->id();
        if (!$checklist->canEdit($userId) && !in_array($this->auth->role(), [0, 1])) {
            $this->flash('error', 'Accès non autorisé');
            return $this->redirect('/checklists/my');
        }
        
        $items = $checklist->getItemsByCategory();
        $progress = $checklist->getProgress();
        
        $data = [
            'checklist' => [
                'name' => $checklist->name,
                'description' => $checklist->description,
                'is_completed' => $checklist->is_completed,
                'created_at' => $checklist->created_at
            ],
            'progress' => $progress,
            'items' => $items
        ];
        
        $response = new JsonResponse($data);
        $response->headers->set('Content-Disposition', 'attachment; filename="checklist_' . $checklist->id . '.json"');
        
        return $response;
    }
}