<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\AccessControl;

/**
 * Middleware de contrôle d'accès hiérarchique
 * Applique la hiérarchie stricte définie dans AccessControl
 */
class AccessControlMiddleware
{
    private AccessControl $accessControl;
    private Session $session;

    public function __construct(Session $session, Database $db, Auth $auth)
    {
        $this->session = $session;
        $this->accessControl = new AccessControl($auth, $session);
    }

    public function handle(Request $request, callable $next): SymfonyResponse
    {
        $currentPath = $request->getPathInfo();
        
        error_log("AccessControlMiddleware: Vérification accès pour: $currentPath");
        error_log("AccessControlMiddleware: Niveau utilisateur: " . $this->accessControl->getCurrentAccessLevel());

        // Vérifier l'accès à la page
        if (!$this->accessControl->canAccessPage($currentPath)) {
            $redirectUrl = $this->accessControl->getRedirectUrlForLevel($currentPath);
            
            error_log("AccessControlMiddleware: Accès refusé, redirection vers: $redirectUrl");
            
            // Messages d'erreur personnalisés selon le niveau
            $level = $this->accessControl->getCurrentAccessLevel();
            $this->setErrorMessage($level, $currentPath);
            
            return new SymfonyResponse('', 302, ['Location' => $redirectUrl]);
        }

        // Ajouter les informations d'affichage à la requête pour les contrôleurs
        $displayInfo = $this->accessControl->getUIDisplayInfo();
        $request->attributes->set('access_display_info', $displayInfo);
        $request->attributes->set('access_control', $this->accessControl);

        $currentLevel = $this->accessControl->getCurrentAccessLevel();
        error_log("AccessControlMiddleware: Accès autorisé pour niveau: " . $currentLevel);
        
        return $next($request);
    }

    /**
     * Définit des messages d'erreur personnalisés selon le niveau d'accès
     */
    private function setErrorMessage(string $level, string $attemptedPath): void
    {
        switch ($level) {
            case AccessControl::LEVEL_PUBLIC:
                $this->session->flash('info', 'Cette page nécessite une connexion. Créez un compte gratuit pour accéder au contenu complet.');
                break;
                
            case AccessControl::LEVEL_PENDING:
                $this->session->flash('warning', 'Votre compte est en attente de validation. Vous recevrez un email de confirmation sous peu.');
                break;
                
            case AccessControl::LEVEL_RESTRICTED:
                $this->session->flash('info', 'Cette page nécessite un abonnement complet. Découvrez nos offres pour accéder à tout le contenu.');
                break;
                
            case AccessControl::LEVEL_BANNED:
                $this->session->flash('error', 'Votre compte a été suspendu. Contactez l\'administration pour plus d\'informations.');
                break;
                
            default:
                $this->session->flash('error', 'Accès non autorisé à cette page.');
        }
    }
}