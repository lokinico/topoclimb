<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

/**
 * Contrôleur pour les événements TopoclimbCH
 */
class EventController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * Affiche la liste des événements
     */
    public function index(?Request $request = null): Response
    {
        try {
            // Pagination
            $page = (int) ($_GET['page'] ?? 1);
            $limit = 12;
            $offset = ($page - 1) * $limit;

            // Filtres
            $filters = [
                'type' => $_GET['type'] ?? null,
                'region' => $_GET['region'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
            ];

            // Construire la requête
            $where = ['1=1'];
            $params = [];

            if ($filters['type']) {
                $where[] = 'type = ?';
                $params[] = $filters['type'];
            }

            if ($filters['region']) {
                $where[] = 'region_id = ?';
                $params[] = $filters['region'];
            }

            if ($filters['date_from']) {
                $where[] = 'date_event >= ?';
                $params[] = $filters['date_from'];
            }

            if ($filters['date_to']) {
                $where[] = 'date_event <= ?';
                $params[] = $filters['date_to'];
            }

            $whereClause = implode(' AND ', $where);

            // Récupérer les événements
            $events = $this->db->fetchAll(
                "SELECT e.*, r.name as region_name, u.username as organizer_name
                 FROM events e
                 LEFT JOIN regions r ON e.region_id = r.id
                 LEFT JOIN users u ON e.organizer_id = u.id
                 WHERE $whereClause
                 ORDER BY e.date_event DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
            );

            // Compter le total pour la pagination
            $total = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM events e WHERE $whereClause",
                $params
            )['count'];

            $totalPages = ceil($total / $limit);

            // Récupérer les données pour les filtres
            $eventTypes = [
                'meet' => 'Rencontre',
                'competition' => 'Compétition',
                'course' => 'Cours',
                'maintenance' => 'Maintenance',
                'other' => 'Autre'
            ];

            $regions = $this->db->fetchAll(
                "SELECT id, name FROM regions WHERE active = 1 ORDER BY name"
            );

            return $this->view->render('events/index.twig', [
                'events' => $events,
                'filters' => $filters,
                'eventTypes' => $eventTypes,
                'regions' => $regions,
                'pagination' => [
                    'current' => $page,
                    'total' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'previous' => $page - 1,
                    'next' => $page + 1
                ],
                'total' => $total,
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('EventController::index error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement des événements');
            return Response::redirect('/');
        }
    }

    /**
     * Affiche un événement
     */
    public function show(?Request $request = null): Response
    {
        $id = $request->attributes->get('id');
        
        if (!$id) {
            $this->session->flash('error', 'Événement non trouvé');
            return Response::redirect('/events');
        }

        try {
            $event = $this->db->fetchOne(
                "SELECT e.*, r.name as region_name, u.username as organizer_name, u.email as organizer_email
                 FROM events e
                 LEFT JOIN regions r ON e.region_id = r.id
                 LEFT JOIN users u ON e.organizer_id = u.id
                 WHERE e.id = ?",
                [$id]
            );

            if (!$event) {
                $this->session->flash('error', 'Événement non trouvé');
                return Response::redirect('/events');
            }

            // Récupérer les participants
            $participants = $this->db->fetchAll(
                "SELECT u.username, u.prenom, u.nom, ep.created_at as registered_at
                 FROM event_participants ep
                 JOIN users u ON ep.user_id = u.id
                 WHERE ep.event_id = ?
                 ORDER BY ep.created_at",
                [$id]
            );

            // Vérifier si l'utilisateur est inscrit
            $isRegistered = false;
            $userId = $this->session->get('auth_user_id');
            if ($userId) {
                $registration = $this->db->fetchOne(
                    "SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?",
                    [$id, $userId]
                );
                $isRegistered = !empty($registration);
            }

            return $this->view->render('events/show.twig', [
                'event' => $event,
                'participants' => $participants,
                'isRegistered' => $isRegistered,
                'canRegister' => $userId && strtotime($event['date_event']) > time(),
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('EventController::show error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement de l\'événement');
            return Response::redirect('/events');
        }
    }

    /**
     * Formulaire de création d'événement
     */
    public function create(?Request $request = null): Response
    {
        if (!$this->session->get('auth_user_id')) {
            $this->session->flash('error', 'Vous devez être connecté pour créer un événement');
            return Response::redirect('/login');
        }

        try {
            $regions = $this->db->fetchAll(
                "SELECT id, name FROM regions WHERE active = 1 ORDER BY name"
            );

            $eventTypes = [
                'meet' => 'Rencontre',
                'competition' => 'Compétition',
                'course' => 'Cours',
                'maintenance' => 'Maintenance',
                'other' => 'Autre'
            ];

            return $this->view->render('events/create.twig', [
                'regions' => $regions,
                'eventTypes' => $eventTypes,
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('EventController::create error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/events');
        }
    }

    /**
     * Enregistrement d'un événement
     */
    public function store(Request $request): Response
    {
        if (!$this->session->get('auth_user_id')) {
            $this->session->flash('error', 'Vous devez être connecté pour créer un événement');
            return Response::redirect('/login');
        }

        try {
            $data = [
                'title' => trim($request->request->get('title', '')),
                'description' => trim($request->request->get('description', '')),
                'type' => $request->request->get('type', ''),
                'date_event' => $request->request->get('date_event', ''),
                'time_event' => $request->request->get('time_event', ''),
                'location' => trim($request->request->get('location', '')),
                'region_id' => $request->request->get('region_id', '') ?: null,
                'max_participants' => $request->request->get('max_participants', '') ?: null,
                'contact_info' => trim($request->request->get('contact_info', '')),
                'organizer_id' => $this->session->get('auth_user_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'active' => 1
            ];

            // Validation
            $errors = [];
            if (empty($data['title'])) {
                $errors[] = 'Le titre est requis';
            }
            if (empty($data['description'])) {
                $errors[] = 'La description est requise';
            }
            if (empty($data['date_event'])) {
                $errors[] = 'La date est requise';
            }
            if (empty($data['location'])) {
                $errors[] = 'Le lieu est requis';
            }

            if (!empty($errors)) {
                $this->session->flash('error', implode('<br>', $errors));
                return Response::redirect('/events/create');
            }

            $eventId = $this->db->insert('events', $data);

            if ($eventId) {
                $this->session->flash('success', 'Événement créé avec succès');
                return Response::redirect("/events/$eventId");
            } else {
                $this->session->flash('error', 'Erreur lors de la création de l\'événement');
                return Response::redirect('/events/create');
            }
        } catch (\Exception $e) {
            error_log('EventController::store error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création de l\'événement');
            return Response::redirect('/events/create');
        }
    }

    /**
     * Inscription à un événement
     */
    public function register(Request $request): Response
    {
        $eventId = $request->attributes->get('id');
        $userId = $this->session->get('auth_user_id');

        if (!$userId) {
            $this->session->flash('error', 'Vous devez être connecté pour vous inscrire');
            return Response::redirect('/login');
        }

        try {
            // Vérifier si l'événement existe
            $event = $this->db->fetchOne(
                "SELECT * FROM events WHERE id = ? AND active = 1",
                [$eventId]
            );

            if (!$event) {
                $this->session->flash('error', 'Événement non trouvé');
                return Response::redirect('/events');
            }

            // Vérifier si l'utilisateur est déjà inscrit
            $existing = $this->db->fetchOne(
                "SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?",
                [$eventId, $userId]
            );

            if ($existing) {
                $this->session->flash('warning', 'Vous êtes déjà inscrit à cet événement');
                return Response::redirect("/events/$eventId");
            }

            // Vérifier le nombre maximum de participants
            if ($event['max_participants']) {
                $currentCount = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM event_participants WHERE event_id = ?",
                    [$eventId]
                )['count'];

                if ($currentCount >= $event['max_participants']) {
                    $this->session->flash('error', 'Cet événement est complet');
                    return Response::redirect("/events/$eventId");
                }
            }

            // Inscrire l'utilisateur
            $this->db->insert('event_participants', [
                'event_id' => $eventId,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $this->session->flash('success', 'Inscription réussie !');
            return Response::redirect("/events/$eventId");
        } catch (\Exception $e) {
            error_log('EventController::register error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de l\'inscription');
            return Response::redirect("/events/$eventId");
        }
    }

    /**
     * Désincription d'un événement
     */
    public function unregister(Request $request): Response
    {
        $eventId = $request->attributes->get('id');
        $userId = $this->session->get('auth_user_id');

        if (!$userId) {
            $this->session->flash('error', 'Vous devez être connecté');
            return Response::redirect('/login');
        }

        try {
            $deleted = $this->db->delete(
                'event_participants',
                'event_id = ? AND user_id = ?',
                [$eventId, $userId]
            );

            if ($deleted) {
                $this->session->flash('success', 'Désinscription réussie');
            } else {
                $this->session->flash('error', 'Vous n\'étiez pas inscrit à cet événement');
            }

            return Response::redirect("/events/$eventId");
        } catch (\Exception $e) {
            error_log('EventController::unregister error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la désinscription');
            return Response::redirect("/events/$eventId");
        }
    }
}