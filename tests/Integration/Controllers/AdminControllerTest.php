<?php

namespace Tests\Integration\Controllers;

use Tests\TestCase;
use TopoclimbCH\Controllers\AdminController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class AdminControllerTest extends TestCase
{
    private AdminController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(AdminController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Administration', $response->getContent());
    }

    public function testIndexPageContainsDashboard(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('dashboard', $content);
        $this->assertStringContainsString('statistiques', $content);
        $this->assertStringContainsString('utilisateurs', $content);
        $this->assertStringContainsString('voies', $content);
    }

    public function testIndexPageContainsNavigationMenu(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('navigation', $content);
        $this->assertStringContainsString('Utilisateurs', $content);
        $this->assertStringContainsString('Contenus', $content);
        $this->assertStringContainsString('Paramètres', $content);
    }

    public function testIndexPageContainsQuickActions(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('actions rapides', $content);
        $this->assertStringContainsString('Nouveau', $content);
        $this->assertStringContainsString('Modérer', $content);
    }

    public function testUsersPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->users($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Gestion des utilisateurs', $response->getContent());
    }

    public function testUsersPageContainsUsersList(): void
    {
        $request = new Request();
        $response = $this->controller->users($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('users-list', $content);
        $this->assertStringContainsString('table', $content);
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('role', $content);
    }

    public function testUsersPageContainsSearchAndFilters(): void
    {
        $request = new Request();
        $response = $this->controller->users($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('filter', $content);
        $this->assertStringContainsString('rôle', $content);
        $this->assertStringContainsString('statut', $content);
    }

    public function testUsersPageContainsActionButtons(): void
    {
        $request = new Request();
        $response = $this->controller->users($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Modifier', $content);
        $this->assertStringContainsString('Bannir', $content);
        $this->assertStringContainsString('Activer', $content);
    }

    public function testUserEditPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Modifier utilisateur', $response->getContent());
    }

    public function testUserEditPageContainsForm(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('role', $content);
        $this->assertStringContainsString('status', $content);
    }

    public function testUserEditPageContainsUserInfo(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('user-info', $content);
        $this->assertStringContainsString('inscription', $content);
        $this->assertStringContainsString('dernière connexion', $content);
        $this->assertStringContainsString('ascensions', $content);
    }

    public function testUserEditPageContainsRoleSelector(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('role', $content);
        $this->assertStringContainsString('Admin', $content);
        $this->assertStringContainsString('Modérateur', $content);
        $this->assertStringContainsString('Utilisateur', $content);
    }

    public function testUserEditPageContainsPermissionsSection(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('permissions', $content);
        $this->assertStringContainsString('droits', $content);
        $this->assertStringContainsString('accès', $content);
    }

    public function testUserEditFormContainsCSRFToken(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testIndexPageContainsRecentActivity(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('activité récente', $content);
        $this->assertStringContainsString('dernières inscriptions', $content);
        $this->assertStringContainsString('dernières ascensions', $content);
    }

    public function testIndexPageContainsSystemHealth(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('système', $content);
        $this->assertStringContainsString('santé', $content);
        $this->assertStringContainsString('performance', $content);
    }

    public function testUsersPageContainsPagination(): void
    {
        $request = new Request();
        $response = $this->controller->users($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('pagination', $content);
        $this->assertStringContainsString('page', $content);
    }

    public function testUsersPageContainsExportButton(): void
    {
        $request = new Request();
        $response = $this->controller->users($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('export', $content);
        $this->assertStringContainsString('télécharger', $content);
    }

    public function testUserEditPageContainsActionHistory(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('historique', $content);
        $this->assertStringContainsString('actions', $content);
        $this->assertStringContainsString('modifications', $content);
    }

    public function testUserEditPageContainsDangerZone(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->userEdit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('danger', $content);
        $this->assertStringContainsString('suppression', $content);
        $this->assertStringContainsString('attention', $content);
    }

    public function testIndexPageContainsCharts(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('chart', $content);
        $this->assertStringContainsString('graphique', $content);
        $this->assertStringContainsString('données', $content);
    }
}