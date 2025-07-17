<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\BaseController;
use TopoclimbCH\Models\Alert;
use TopoclimbCH\Models\AlertType;
use TopoclimbCH\Models\AlertConfirmation;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\User;
use TopoclimbCH\Services\ValidationService;
use TopoclimbCH\Services\AuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class AlertController extends BaseController
{
    private ValidationService $validationService;
    private AuthService $authService;

    public function __construct(ValidationService $validationService, AuthService $authService)
    {
        $this->validationService = $validationService;
        $this->authService = $authService;
    }

    public function index(Request $request): Response
    {
        $page = (int)$request->query->get('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        $filters = [
            'region_id' => $request->query->get('region_id'),
            'site_id' => $request->query->get('site_id'),
            'alert_type_id' => $request->query->get('alert_type_id'),
            'active' => $request->query->get('active', 1),
            'search' => $request->query->get('search')
        ];

        $alerts = Alert::getFilteredAlerts($filters, $offset, $perPage);
        $totalAlerts = Alert::countFilteredAlerts($filters);
        $totalPages = ceil($totalAlerts / $perPage);

        $regions = Region::getAllActive();
        $sites = Site::getAllActive();
        $alertTypes = AlertType::getAll();

        return $this->render('alerts/index.twig', [
            'alerts' => $alerts,
            'regions' => $regions,
            'sites' => $sites,
            'alertTypes' => $alertTypes,
            'filters' => $filters,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'per_page' => $perPage,
                'total_items' => $totalAlerts
            ]
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int)$request->attributes->get('id');
        $alert = Alert::getById($id);

        if (!$alert) {
            return $this->notFound('Alerte non trouvée');
        }

        $confirmations = AlertConfirmation::getByAlertId($id);
        $canConfirm = $this->authService->isAuthenticated() && 
                     !AlertConfirmation::hasUserConfirmed($id, $this->authService->getCurrentUserId());

        return $this->render('alerts/show.twig', [
            'alert' => $alert,
            'confirmations' => $confirmations,
            'canConfirm' => $canConfirm
        ]);
    }

    public function create(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator', 'editor'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $regions = Region::getAllActive();
        $sites = Site::getAllActive();
        $alertTypes = AlertType::getAll();

        return $this->render('alerts/form.twig', [
            'alert' => new Alert(),
            'regions' => $regions,
            'sites' => $sites,
            'alertTypes' => $alertTypes,
            'isEdit' => false
        ]);
    }

    public function store(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator', 'editor'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $data = $request->request->all();
        
        $validation = $this->validationService->validate($data, [
            'title' => ['required', 'max:255'],
            'description' => ['required'],
            'alert_type_id' => ['required', 'integer'],
            'region_id' => ['integer'],
            'site_id' => ['integer'],
            'sector_id' => ['integer'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'start_date' => ['required', 'date'],
            'end_date' => ['date'],
            'active' => ['boolean']
        ]);

        if (!$validation['isValid']) {
            return $this->render('alerts/form.twig', [
                'alert' => (object)$data,
                'regions' => Region::getAllActive(),
                'sites' => Site::getAllActive(),
                'alertTypes' => AlertType::getAll(),
                'errors' => $validation['errors'],
                'isEdit' => false
            ]);
        }

        $alert = new Alert();
        $alert->title = $data['title'];
        $alert->description = $data['description'];
        $alert->alert_type_id = (int)$data['alert_type_id'];
        $alert->region_id = !empty($data['region_id']) ? (int)$data['region_id'] : null;
        $alert->site_id = !empty($data['site_id']) ? (int)$data['site_id'] : null;
        $alert->sector_id = !empty($data['sector_id']) ? (int)$data['sector_id'] : null;
        $alert->severity = $data['severity'];
        $alert->start_date = $data['start_date'];
        $alert->end_date = !empty($data['end_date']) ? $data['end_date'] : null;
        $alert->active = isset($data['active']) ? (bool)$data['active'] : true;
        $alert->created_by = $this->authService->getCurrentUserId();
        $alert->created_at = date('Y-m-d H:i:s');

        if ($alert->save()) {
            $this->addFlash('success', 'Alerte créée avec succès');
            return $this->redirect('/alerts/' . $alert->id);
        }

        $this->addFlash('error', 'Erreur lors de la création de l\'alerte');
        return $this->render('alerts/form.twig', [
            'alert' => $alert,
            'regions' => Region::getAllActive(),
            'sites' => Site::getAllActive(),
            'alertTypes' => AlertType::getAll(),
            'isEdit' => false
        ]);
    }

    public function edit(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator', 'editor'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $id = (int)$request->attributes->get('id');
        $alert = Alert::getById($id);

        if (!$alert) {
            return $this->notFound('Alerte non trouvée');
        }

        $regions = Region::getAllActive();
        $sites = Site::getAllActive();
        $alertTypes = AlertType::getAll();

        return $this->render('alerts/form.twig', [
            'alert' => $alert,
            'regions' => $regions,
            'sites' => $sites,
            'alertTypes' => $alertTypes,
            'isEdit' => true
        ]);
    }

    public function update(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator', 'editor'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $id = (int)$request->attributes->get('id');
        $alert = Alert::getById($id);

        if (!$alert) {
            return $this->notFound('Alerte non trouvée');
        }

        $data = $request->request->all();
        
        $validation = $this->validationService->validate($data, [
            'title' => ['required', 'max:255'],
            'description' => ['required'],
            'alert_type_id' => ['required', 'integer'],
            'region_id' => ['integer'],
            'site_id' => ['integer'],
            'sector_id' => ['integer'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'start_date' => ['required', 'date'],
            'end_date' => ['date'],
            'active' => ['boolean']
        ]);

        if (!$validation['isValid']) {
            return $this->render('alerts/form.twig', [
                'alert' => $alert,
                'regions' => Region::getAllActive(),
                'sites' => Site::getAllActive(),
                'alertTypes' => AlertType::getAll(),
                'errors' => $validation['errors'],
                'isEdit' => true
            ]);
        }

        $alert->title = $data['title'];
        $alert->description = $data['description'];
        $alert->alert_type_id = (int)$data['alert_type_id'];
        $alert->region_id = !empty($data['region_id']) ? (int)$data['region_id'] : null;
        $alert->site_id = !empty($data['site_id']) ? (int)$data['site_id'] : null;
        $alert->sector_id = !empty($data['sector_id']) ? (int)$data['sector_id'] : null;
        $alert->severity = $data['severity'];
        $alert->start_date = $data['start_date'];
        $alert->end_date = !empty($data['end_date']) ? $data['end_date'] : null;
        $alert->active = isset($data['active']) ? (bool)$data['active'] : true;
        $alert->updated_at = date('Y-m-d H:i:s');

        if ($alert->save()) {
            $this->addFlash('success', 'Alerte modifiée avec succès');
            return $this->redirect('/alerts/' . $alert->id);
        }

        $this->addFlash('error', 'Erreur lors de la modification de l\'alerte');
        return $this->render('alerts/form.twig', [
            'alert' => $alert,
            'regions' => Region::getAllActive(),
            'sites' => Site::getAllActive(),
            'alertTypes' => AlertType::getAll(),
            'isEdit' => true
        ]);
    }

    public function delete(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin', 'moderator'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $id = (int)$request->attributes->get('id');
        $alert = Alert::getById($id);

        if (!$alert) {
            return $this->notFound('Alerte non trouvée');
        }

        if ($alert->delete()) {
            $this->addFlash('success', 'Alerte supprimée avec succès');
        } else {
            $this->addFlash('error', 'Erreur lors de la suppression de l\'alerte');
        }

        return $this->redirect('/alerts');
    }

    public function confirm(Request $request): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->unauthorized('Connexion requise');
        }

        $id = (int)$request->attributes->get('id');
        $alert = Alert::getById($id);

        if (!$alert) {
            return new JsonResponse(['error' => 'Alerte non trouvée'], 404);
        }

        $userId = $this->authService->getCurrentUserId();
        
        if (AlertConfirmation::hasUserConfirmed($id, $userId)) {
            return new JsonResponse(['error' => 'Vous avez déjà confirmé cette alerte'], 400);
        }

        $confirmation = new AlertConfirmation();
        $confirmation->alert_id = $id;
        $confirmation->user_id = $userId;
        $confirmation->confirmed_at = date('Y-m-d H:i:s');

        if ($confirmation->save()) {
            $confirmationCount = AlertConfirmation::countByAlertId($id);
            return new JsonResponse([
                'success' => true,
                'message' => 'Alerte confirmée',
                'confirmation_count' => $confirmationCount
            ]);
        }

        return new JsonResponse(['error' => 'Erreur lors de la confirmation'], 500);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $filters = [
            'region_id' => $request->query->get('region_id'),
            'site_id' => $request->query->get('site_id'),
            'alert_type_id' => $request->query->get('alert_type_id'),
            'active' => $request->query->get('active', 1),
            'severity' => $request->query->get('severity')
        ];

        $alerts = Alert::getFilteredAlerts($filters, 0, 100);
        
        return new JsonResponse([
            'alerts' => array_map(function($alert) {
                return [
                    'id' => $alert->id,
                    'title' => $alert->title,
                    'description' => $alert->description,
                    'severity' => $alert->severity,
                    'alert_type' => $alert->alert_type_name,
                    'region' => $alert->region_name,
                    'site' => $alert->site_name,
                    'sector' => $alert->sector_name,
                    'start_date' => $alert->start_date,
                    'end_date' => $alert->end_date,
                    'confirmation_count' => $alert->confirmation_count,
                    'created_at' => $alert->created_at
                ];
            }, $alerts)
        ]);
    }
}