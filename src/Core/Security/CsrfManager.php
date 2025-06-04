<?php

namespace TopoclimbCH\Core\Security;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Session;

/**
 * Gestionnaire centralisé pour la protection CSRF
 * 
 * Cette classe centralise toute la logique CSRF de l'application
 * pour éviter la duplication et assurer la cohérence.
 */
class CsrfManager
{
    private const TOKEN_KEY = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    private Session $session;
    private array $exemptedRoutes;

    public function __construct(Session $session, array $exemptedRoutes = [])
    {
        $this->session = $session;
        $this->exemptedRoutes = array_merge(['/logout'], $exemptedRoutes);
    }

    /**
     * Génère un nouveau token CSRF
     */
    public function generateToken(bool $force = false): string
    {
        // Si un token existe et qu'on ne force pas, on le retourne
        if (!$force && $this->session->has(self::TOKEN_KEY)) {
            $token = $this->session->get(self::TOKEN_KEY);
            if (!empty($token)) {
                error_log("CSRF: Token existant réutilisé: " . substr($token, 0, 10) . '...');
                return $token;
            }
        }

        // Générer un nouveau token
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $this->session->set(self::TOKEN_KEY, $token);

        error_log("CSRF: Nouveau token généré: " . substr($token, 0, 10) . '...');
        return $token;
    }

    /**
     * Récupère le token CSRF actuel ou en génère un
     */
    public function getToken(): string
    {
        return $this->generateToken(false);
    }

    /**
     * Valide un token CSRF
     */
    public function validateToken(string $submittedToken): bool
    {
        if (empty($submittedToken)) {
            error_log("CSRF: Token soumis vide");
            return false;
        }

        $sessionToken = $this->session->get(self::TOKEN_KEY);
        if (empty($sessionToken)) {
            error_log("CSRF: Aucun token en session");
            return false;
        }

        $isValid = hash_equals($sessionToken, $submittedToken);

        error_log("CSRF: Validation " . ($isValid ? "réussie" : "échouée") .
            " (soumis: " . substr($submittedToken, 0, 10) .
            "..., session: " . substr($sessionToken, 0, 10) . "...)");

        return $isValid;
    }

    /**
     * Extrait le token CSRF depuis une requête
     */
    public function getTokenFromRequest(Request $request): ?string
    {
        // Chercher dans les champs de formulaire
        $tokenFields = ['csrf_token', '_csrf_token', '_token'];

        foreach ($tokenFields as $field) {
            $token = $request->request->get($field) ?? $request->query->get($field);
            if (!empty($token)) {
                return $token;
            }
        }

        // Chercher dans les headers (pour les API)
        return $request->headers->get('X-CSRF-TOKEN');
    }

    /**
     * Valide une requête complète
     */
    public function validateRequest(Request $request): bool
    {
        // Vérifier si la route est exemptée
        if ($this->isRouteExempted($request)) {
            error_log("CSRF: Route exemptée: " . $request->getPathInfo());
            return true;
        }

        // Seules les méthodes non-sûres nécessitent une validation
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        $submittedToken = $this->getTokenFromRequest($request);
        return $this->validateToken($submittedToken ?? '');
    }

    /**
     * Vérifie si une route est exemptée de validation CSRF
     */
    public function isRouteExempted(Request $request): bool
    {
        $path = $request->getPathInfo();
        return in_array($path, $this->exemptedRoutes);
    }

    /**
     * Régénère le token après une validation réussie
     * Utile pour prévenir les attaques de replay
     */
    public function regenerateToken(): string
    {
        return $this->generateToken(true);
    }

    /**
     * Supprime le token CSRF de la session
     */
    public function clearToken(): void
    {
        $this->session->remove(self::TOKEN_KEY);
        error_log("CSRF: Token supprimé de la session");
    }

    /**
     * Retourne le nom du champ par défaut pour les formulaires
     */
    public function getFieldName(): string
    {
        return 'csrf_token';
    }

    /**
     * Génère le HTML pour un champ caché de token CSRF
     */
    public function getHiddenField(): string
    {
        $token = $this->getToken();
        $fieldName = $this->getFieldName();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Ajoute des routes exemptées
     */
    public function addExemptedRoutes(array $routes): void
    {
        $this->exemptedRoutes = array_merge($this->exemptedRoutes, $routes);
    }

    /**
     * Configuration pour les en-têtes meta (utile pour AJAX)
     */
    public function getMetaTag(): string
    {
        $token = $this->getToken();
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
}
