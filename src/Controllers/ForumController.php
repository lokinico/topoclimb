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
 * Contrôleur pour le forum TopoclimbCH
 */
class ForumController extends BaseController
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
     * Affiche la liste des catégories de forum
     */
    public function index(?Request $request = null): Response
    {
        try {
            // Récupérer les catégories avec les statistiques
            $categories = $this->db->fetchAll(
                "SELECT 
                    c.*,
                    COUNT(DISTINCT t.id) as topic_count,
                    COUNT(DISTINCT p.id) as post_count,
                    MAX(p.created_at) as last_post_date,
                    u.username as last_poster_name
                 FROM forum_categories c
                 LEFT JOIN forum_topics t ON c.id = t.category_id AND t.active = 1
                 LEFT JOIN forum_posts p ON t.id = p.topic_id AND p.active = 1
                 LEFT JOIN users u ON p.user_id = u.id
                 WHERE c.active = 1
                 GROUP BY c.id
                 ORDER BY c.sort_order, c.name"
            );

            // Récupérer les statistiques générales
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(DISTINCT t.id) as total_topics,
                    COUNT(DISTINCT p.id) as total_posts,
                    COUNT(DISTINCT u.id) as total_users
                 FROM forum_topics t
                 LEFT JOIN forum_posts p ON t.id = p.topic_id AND p.active = 1
                 LEFT JOIN users u ON p.user_id = u.id
                 WHERE t.active = 1"
            );

            // Récupérer les derniers messages
            $recentPosts = $this->db->fetchAll(
                "SELECT 
                    p.id, p.content, p.created_at,
                    t.title as topic_title, t.id as topic_id,
                    c.name as category_name, c.id as category_id,
                    u.username, u.prenom, u.nom
                 FROM forum_posts p
                 JOIN forum_topics t ON p.topic_id = t.id
                 JOIN forum_categories c ON t.category_id = c.id
                 JOIN users u ON p.user_id = u.id
                 WHERE p.active = 1 AND t.active = 1 AND c.active = 1
                 ORDER BY p.created_at DESC
                 LIMIT 5"
            );

            return $this->view->render('forum/index.twig', [
                'categories' => $categories,
                'stats' => $stats,
                'recentPosts' => $recentPosts,
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('ForumController::index error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du forum');
            return Response::redirect('/');
        }
    }

    /**
     * Affiche les sujets d'une catégorie
     */
    public function category(?Request $request = null): Response
    {
        $categoryId = $request->attributes->get('id');
        
        if (!$categoryId) {
            $this->session->flash('error', 'Catégorie non trouvée');
            return Response::redirect('/forum');
        }

        try {
            // Récupérer la catégorie
            $category = $this->db->fetchOne(
                "SELECT * FROM forum_categories WHERE id = ? AND active = 1",
                [$categoryId]
            );

            if (!$category) {
                $this->session->flash('error', 'Catégorie non trouvée');
                return Response::redirect('/forum');
            }

            // Pagination
            $page = (int) ($_GET['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;

            // Récupérer les sujets
            $topics = $this->db->fetchAll(
                "SELECT 
                    t.*,
                    u.username as author_name,
                    COUNT(p.id) as post_count,
                    MAX(p.created_at) as last_post_date,
                    lu.username as last_poster_name
                 FROM forum_topics t
                 LEFT JOIN users u ON t.user_id = u.id
                 LEFT JOIN forum_posts p ON t.id = p.topic_id AND p.active = 1
                 LEFT JOIN forum_posts lp ON t.id = lp.topic_id AND lp.created_at = (
                     SELECT MAX(created_at) FROM forum_posts WHERE topic_id = t.id AND active = 1
                 )
                 LEFT JOIN users lu ON lp.user_id = lu.id
                 WHERE t.category_id = ? AND t.active = 1
                 GROUP BY t.id
                 ORDER BY t.is_pinned DESC, t.updated_at DESC
                 LIMIT ? OFFSET ?",
                [$categoryId, $limit, $offset]
            );

            // Compter le total
            $total = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM forum_topics WHERE category_id = ? AND active = 1",
                [$categoryId]
            )['count'];

            $totalPages = ceil($total / $limit);

            return $this->view->render('forum/category.twig', [
                'category' => $category,
                'topics' => $topics,
                'pagination' => [
                    'current' => $page,
                    'total' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'previous' => $page - 1,
                    'next' => $page + 1
                ],
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('ForumController::category error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement de la catégorie');
            return Response::redirect('/forum');
        }
    }

    /**
     * Affiche un sujet avec ses messages
     */
    public function topic(?Request $request = null): Response
    {
        $topicId = $request->attributes->get('id');
        
        if (!$topicId) {
            $this->session->flash('error', 'Sujet non trouvé');
            return Response::redirect('/forum');
        }

        try {
            // Récupérer le sujet
            $topic = $this->db->fetchOne(
                "SELECT 
                    t.*,
                    c.name as category_name,
                    c.id as category_id,
                    u.username as author_name
                 FROM forum_topics t
                 JOIN forum_categories c ON t.category_id = c.id
                 JOIN users u ON t.user_id = u.id
                 WHERE t.id = ? AND t.active = 1",
                [$topicId]
            );

            if (!$topic) {
                $this->session->flash('error', 'Sujet non trouvé');
                return Response::redirect('/forum');
            }

            // Pagination
            $page = (int) ($_GET['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;

            // Récupérer les messages
            $posts = $this->db->fetchAll(
                "SELECT 
                    p.*,
                    u.username, u.prenom, u.nom, u.avatar,
                    u.created_at as user_joined
                 FROM forum_posts p
                 JOIN users u ON p.user_id = u.id
                 WHERE p.topic_id = ? AND p.active = 1
                 ORDER BY p.created_at ASC
                 LIMIT ? OFFSET ?",
                [$topicId, $limit, $offset]
            );

            // Compter le total
            $total = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM forum_posts WHERE topic_id = ? AND active = 1",
                [$topicId]
            )['count'];

            $totalPages = ceil($total / $limit);

            // Marquer comme lu (si utilisateur connecté)
            if ($this->session->get('auth_user_id')) {
                $this->markTopicAsRead($topicId, $this->session->get('auth_user_id'));
            }

            return $this->view->render('forum/topic.twig', [
                'topic' => $topic,
                'posts' => $posts,
                'pagination' => [
                    'current' => $page,
                    'total' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'previous' => $page - 1,
                    'next' => $page + 1
                ],
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('ForumController::topic error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du sujet');
            return Response::redirect('/forum');
        }
    }

    /**
     * Formulaire de création de sujet
     */
    public function createTopic(?Request $request = null): Response
    {
        if (!$this->session->get('auth_user_id')) {
            $this->session->flash('error', 'Vous devez être connecté pour créer un sujet');
            return Response::redirect('/login');
        }

        $categoryId = $request->attributes->get('id');
        
        if (!$categoryId) {
            $this->session->flash('error', 'Catégorie non trouvée');
            return Response::redirect('/forum');
        }

        try {
            $category = $this->db->fetchOne(
                "SELECT * FROM forum_categories WHERE id = ? AND active = 1",
                [$categoryId]
            );

            if (!$category) {
                $this->session->flash('error', 'Catégorie non trouvée');
                return Response::redirect('/forum');
            }

            return $this->view->render('forum/create_topic.twig', [
                'category' => $category,
                'csrf_token' => $this->csrfManager->getToken()
            ]);
        } catch (\Exception $e) {
            error_log('ForumController::createTopic error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/forum');
        }
    }

    /**
     * Enregistrement d'un nouveau sujet
     */
    public function storeTopic(Request $request): Response
    {
        if (!$this->session->get('auth_user_id')) {
            $this->session->flash('error', 'Vous devez être connecté pour créer un sujet');
            return Response::redirect('/login');
        }

        $categoryId = $request->attributes->get('id');
        
        try {
            $data = [
                'title' => trim($request->request->get('title', '')),
                'content' => trim($request->request->get('content', '')),
                'category_id' => $categoryId,
                'user_id' => $this->session->get('auth_user_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'active' => 1
            ];

            // Validation
            $errors = [];
            if (empty($data['title'])) {
                $errors[] = 'Le titre est requis';
            }
            if (empty($data['content'])) {
                $errors[] = 'Le contenu est requis';
            }

            if (!empty($errors)) {
                $this->session->flash('error', implode('<br>', $errors));
                return Response::redirect("/forum/category/$categoryId/create");
            }

            // Créer le sujet
            $topicId = $this->db->insert('forum_topics', $data);

            // Créer le premier message
            $this->db->insert('forum_posts', [
                'topic_id' => $topicId,
                'user_id' => $this->session->get('auth_user_id'),
                'content' => $data['content'],
                'created_at' => date('Y-m-d H:i:s'),
                'active' => 1
            ]);

            $this->session->flash('success', 'Sujet créé avec succès');
            return Response::redirect("/forum/topic/$topicId");
        } catch (\Exception $e) {
            error_log('ForumController::storeTopic error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création du sujet');
            return Response::redirect("/forum/category/$categoryId/create");
        }
    }

    /**
     * Ajouter une réponse à un sujet
     */
    public function reply(Request $request): Response
    {
        if (!$this->session->get('auth_user_id')) {
            $this->session->flash('error', 'Vous devez être connecté pour répondre');
            return Response::redirect('/login');
        }

        $topicId = $request->attributes->get('id');
        
        try {
            $content = trim($request->request->get('content', ''));
            
            if (empty($content)) {
                $this->session->flash('error', 'Le contenu de la réponse est requis');
                return Response::redirect("/forum/topic/$topicId");
            }

            // Vérifier que le sujet existe
            $topic = $this->db->fetchOne(
                "SELECT * FROM forum_topics WHERE id = ? AND active = 1",
                [$topicId]
            );

            if (!$topic) {
                $this->session->flash('error', 'Sujet non trouvé');
                return Response::redirect('/forum');
            }

            // Ajouter la réponse
            $this->db->insert('forum_posts', [
                'topic_id' => $topicId,
                'user_id' => $this->session->get('auth_user_id'),
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'active' => 1
            ]);

            // Mettre à jour le sujet
            $this->db->update('forum_topics', [
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$topicId]);

            $this->session->flash('success', 'Réponse ajoutée avec succès');
            return Response::redirect("/forum/topic/$topicId");
        } catch (\Exception $e) {
            error_log('ForumController::reply error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de l\'ajout de la réponse');
            return Response::redirect("/forum/topic/$topicId");
        }
    }

    /**
     * Marquer un sujet comme lu
     */
    private function markTopicAsRead(int $topicId, int $userId): void
    {
        try {
            $existing = $this->db->fetchOne(
                "SELECT id FROM forum_topic_views WHERE topic_id = ? AND user_id = ?",
                [$topicId, $userId]
            );

            if ($existing) {
                $this->db->update('forum_topic_views', [
                    'viewed_at' => date('Y-m-d H:i:s')
                ], 'topic_id = ? AND user_id = ?', [$topicId, $userId]);
            } else {
                $this->db->insert('forum_topic_views', [
                    'topic_id' => $topicId,
                    'user_id' => $userId,
                    'viewed_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (\Exception $e) {
            error_log('ForumController::markTopicAsRead error: ' . $e->getMessage());
        }
    }
}