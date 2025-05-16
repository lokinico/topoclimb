<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;

class PreserveCsrfTokenMiddleware
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
        // Log d'initialisation pour confirmer le chargement de la classe modifiée
        error_log("PreserveCsrfTokenMiddleware: Classe initialisée le " . date('Y-m-d H:i:s'));
    }

    public function handle(Request $request, callable $next): Response
    {
        // Logs détaillés pour le diagnostic
        error_log("PreserveCsrfTokenMiddleware: Traitement de la requête pour URL = " . $request->getPathInfo());
        error_log("PreserveCsrfTokenMiddleware: Méthode HTTP = " . $request->getMethod());

        // Ignorer la route de déconnexion pour éviter les conflits
        if ($request->getPathInfo() === '/logout') {
            error_log("PreserveCsrfTokenMiddleware: Contournement pour /logout ACTIVÉ - passage direct au contrôleur");
            return $next($request);
        }

        // Vérification plus large (si la condition exacte ne fonctionne pas)
        if (strpos($request->getPathInfo(), 'logout') !== false) {
            error_log("PreserveCsrfTokenMiddleware: Contournement pour chemin contenant 'logout' - passage direct au contrôleur");
            return $next($request);
        }

        // Sauvegarder le token CSRF avant tout traitement
        $originalToken = $this->session->get('csrf_token');
        $hasOriginalToken = !empty($originalToken);

        if ($hasOriginalToken) {
            // Garder une copie sécurisée du token
            $this->session->set('_original_csrf_token', $originalToken);
            error_log("PreserveCsrfTokenMiddleware: CSRF token sauvegardé: " . substr($originalToken, 0, 10) . "...");
        } else {
            error_log("PreserveCsrfTokenMiddleware: Aucun token CSRF trouvé en session");
        }

        try {
            // Exécuter le pipeline de middleware avec gestion d'erreurs
            error_log("PreserveCsrfTokenMiddleware: Exécution du pipeline de middleware");
            $response = $next($request);
            error_log("PreserveCsrfTokenMiddleware: Pipeline exécuté avec succès");
        } catch (\Throwable $e) {
            error_log("PreserveCsrfTokenMiddleware: ERREUR dans l'exécution du pipeline: " . $e->getMessage());
            throw $e;
        }

        // Restaurer le token si nécessaire
        if ($hasOriginalToken) {
            try {
                $currentToken = $this->session->get('csrf_token');
                error_log("PreserveCsrfTokenMiddleware: Vérification du token actuel: " .
                    ($currentToken ? substr($currentToken, 0, 10) . "..." : "non défini"));

                if ($currentToken !== $originalToken) {
                    $this->session->set('csrf_token', $originalToken);
                    error_log("PreserveCsrfTokenMiddleware: CSRF token restauré: " . substr($originalToken, 0, 10) . "...");
                } else {
                    error_log("PreserveCsrfTokenMiddleware: CSRF token inchangé, pas besoin de restauration");
                }
            } catch (\Throwable $e) {
                error_log("PreserveCsrfTokenMiddleware: ERREUR lors de la restauration du token: " . $e->getMessage());
                // Ne pas relancer l'exception pour éviter les erreurs 500
            }
        }

        // S'assurer que les changements sont persistés
        try {
            error_log("PreserveCsrfTokenMiddleware: Tentative de persistance de la session");
            $this->session->persist();
            error_log("PreserveCsrfTokenMiddleware: Session persistée avec succès");
        } catch (\Throwable $e) {
            error_log("PreserveCsrfTokenMiddleware: ERREUR lors de la persistance de la session: " . $e->getMessage());
            // Ne pas relancer l'exception pour éviter les erreurs 500
        }

        error_log("PreserveCsrfTokenMiddleware: Traitement terminé pour " . $request->getPathInfo());
        return $response;
    }
}
