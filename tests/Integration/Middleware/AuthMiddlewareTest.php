<?php

namespace TopoclimbCH\Tests\Integration\Middleware;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Middleware\AuthMiddleware;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddlewareTest extends TestCase
{
    private $middleware;
    private $session;
    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = $this->container->get(Session::class);
        $this->db = $this->container->get(Database::class);
        $this->middleware = new AuthMiddleware($this->session, $this->db);
    }

    public function testHandleWithAuthenticatedUser()
    {
        // Simuler un utilisateur authentifié
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['is_authenticated'] = true;

        // Créer une requête
        $request = Request::create('/protected-route', 'GET');

        // Simuler un gestionnaire qui génère une réponse
        $next = function ($request) {
            return new Response('Protected content', 200);
        };

        // Exécuter le middleware
        $response = $this->middleware->handle($request, $next);

        // Vérifier que le middleware a laissé passer la requête
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testHandleWithUnauthenticatedUser()
    {
        // S'assurer qu'aucun utilisateur n'est authentifié
        unset($_SESSION['auth_user_id']);
        unset($_SESSION['is_authenticated']);

        // Créer une requête
        $request = Request::create('/protected-route', 'GET');

        // Intercepter la redirection (le middleware utilise header() et exit())
        $this->expectOutputString(''); // Pas de sortie, car on intercepte avant

        try {
            // Gestionnaire qui ne devrait jamais être appelé
            $next = function ($request) {
                return new Response('Protected content', 200);
            };

            // Cette partie ne sera pas exécutée en raison du header/exit
            $this->middleware->handle($request, $next);
        } catch (\Exception $e) {
            // Intercepter l'erreur si le middleware utilise header() et exit()
            $this->assertStringContainsString('Location: /login', $e->getMessage());
        }

        // Vérifier que intended_url a été défini dans la session
        $this->assertEquals('/protected-route', $_SESSION['intended_url'] ?? null);
    }
}
