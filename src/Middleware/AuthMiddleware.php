<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class AuthMiddleware
{
    private Session $session;
    private Database $db;
    private ?Auth $auth = null;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;

        // Initialiser Auth de manière sécurisée
        try {
            $this->auth = Auth::getInstance($session, $db);
        } catch (\Exception $e) {
            error_log("Erreur lors de l'initialisation d'Auth dans le middleware: " . $e->getMessage());
            // Ne pas lancer d'exception - le middleware gérera lui-même l'absence d'authentification
        }
    }

    /**
     * Gère la requête et vérifie l'authentification
     *
     * @param Request $request La requête HTTP
     * @param callable $next La fonction de rappel pour passer au middleware suivant
     * @return Response La réponse HTTP
     */
    public function handle(Request $request, callable $next): Response
    {
        // Vérifier explicitement la présence des données d'authentification en session
        $hasAuthUserId = isset($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'];
        $isAuthenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];

        error_log("AuthMiddleware: Vérification auth - ID=" . ($hasAuthUserId ? $_SESSION['auth_user_id'] : 'non défini') .
            ", Authentifié=" . ($isAuthenticated ? 'oui' : 'non'));

        // Si l'authentification est présente en session, essayer d'initialiser Auth
        if ($hasAuthUserId && $isAuthenticated) {
            try {
                // Initialiser Auth avec les dépendances
                if ($this->auth === null) {
                    $this->auth = Auth::getInstance($this->session, $this->db);
                }

                // Si Auth confirme l'authentification, continuer
                if ($this->auth->check()) {
                    return $next($request);
                }

                error_log("AuthMiddleware: Auth initialisé mais aucun utilisateur trouvé");
            } catch (\Exception $e) {
                error_log("AuthMiddleware: Erreur lors de l'initialisation d'Auth: " . $e->getMessage());
                // Continuer à la logique de redirection
            }
        }

        // Si on arrive ici, l'utilisateur n'est pas authentifié ou Auth a échoué
        $currentPath = $request->getPathInfo();

        // Éviter les boucles de redirection
        if ($currentPath === '/login') {
            return $next($request);
        }

        // Stocker l'URL actuelle pour y revenir après login
        $this->session->set('intended_url', $currentPath);
        $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');

        // IMPORTANT: persister la session
        $this->session->persist();

        // Redirection vers login
        header("Location: /login", true, 302);
        exit;
    }
}
