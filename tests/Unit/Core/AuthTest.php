<?php

namespace TopoclimbCH\Tests\Unit\Core;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class AuthTest extends TestCase
{
    private $auth;
    private $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une session fictive pour les tests
        $this->session = $this->createMock(Session::class);

        // Initialiser Auth avec des dépendances
        $this->auth = Auth::getInstance($this->session, $this->db);
    }

    public function testLogout()
    {
        // Configuration des attentes pour les méthodes appelées
        $this->session->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive(
                ['auth_user_id'],
                ['is_authenticated']
            );

        // Exécuter la méthode à tester
        $this->auth->logout();

        // Vérifier que l'utilisateur est bien déconnecté
        $this->assertFalse($this->auth->check());
    }

    public function testCheck()
    {
        // Test pour vérifier qu'un utilisateur non connecté renvoie false
        $this->assertFalse($this->auth->check());

        // Test pour vérifier qu'un utilisateur connecté renvoie true
        // Ici, il faudrait mocker la méthode checkSession ou similaire
    }

    // Autres tests pour Auth
}
