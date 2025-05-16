<?php

namespace TopoclimbCH\Tests\Functional\Auth;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutTest extends TestCase
{
    private $router;
    private $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = $this->container->get(Router::class);
        $this->session = $this->container->get(Session::class);

        // Charger les routes
        $this->router->loadRoutes(BASE_PATH . '/config/routes.php');

        // Simuler un utilisateur connecté
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['is_authenticated'] = true;
    }

    public function testLogout()
    {
        // Créer une requête de déconnexion
        $request = Request::create('/logout', 'GET');

        // Simuler l'exécution de la route (cela peut être difficile à cause du header/exit)
        // Cette partie dépend de votre implémentation exacte
        // Pour un test complet, vous pourriez utiliser un client HTTP comme Guzzle 
        // ou adapter votre architecture pour éviter header/exit dans les tests

        // Dans cet exemple simplifié, nous allons directement appeler le contrôleur
        $authController = $this->container->get(\TopoclimbCH\Controllers\AuthController::class);

        try {
            $response = $authController->logout();

            // Si la méthode retourne une réponse au lieu d'utiliser header/exit
            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(302, $response->getStatusCode()); // Redirection
            $this->assertEquals('/', $response->headers->get('Location'));
        } catch (\Exception $e) {
            // Si la méthode utilise header/exit, nous devons tester autrement
            // Par exemple, en vérifiant que la session est vidée
            $this->assertFalse(isset($_SESSION['auth_user_id']));
            $this->assertFalse(isset($_SESSION['is_authenticated']));
        }

        // Vérifier que l'utilisateur est bien déconnecté
        // (Ces assertions dépendent de la façon dont votre session est recréée après logout)
        $this->assertArrayHasKey('_flashes', $_SESSION);
        $this->assertArrayHasKey('success', $_SESSION['_flashes']);
    }
}
