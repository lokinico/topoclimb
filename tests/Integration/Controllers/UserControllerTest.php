<?php

namespace Tests\Integration\Controllers;

use Tests\TestCase;
use TopoclimbCH\Controllers\UserController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class UserControllerTest extends TestCase
{
    private UserController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(UserController::class);
    }

    public function testProfilePageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->profile($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Profil', $response->getContent());
    }

    public function testProfilePageContainsUserInfo(): void
    {
        $request = new Request();
        $response = $this->controller->profile($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('user-info', $content);
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('inscription', $content);
    }

    public function testProfilePageContainsStats(): void
    {
        $request = new Request();
        $response = $this->controller->profile($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('statistiques', $content);
        $this->assertStringContainsString('ascensions', $content);
        $this->assertStringContainsString('voies', $content);
        $this->assertStringContainsString('niveau', $content);
    }

    public function testAscentsPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->ascents($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Mes ascensions', $response->getContent());
    }

    public function testAscentsPageContainsAscentsList(): void
    {
        $request = new Request();
        $response = $this->controller->ascents($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('ascents-list', $content);
        $this->assertStringContainsString('table', $content);
        $this->assertStringContainsString('date', $content);
        $this->assertStringContainsString('voie', $content);
    }

    public function testAscentsPageContainsFilters(): void
    {
        $request = new Request();
        $response = $this->controller->ascents($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('filter', $content);
        $this->assertStringContainsString('date', $content);
        $this->assertStringContainsString('cotation', $content);
        $this->assertStringContainsString('type', $content);
    }

    public function testFavoritesPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->favorites($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Mes favoris', $response->getContent());
    }

    public function testFavoritesPageContainsFavoritesList(): void
    {
        $request = new Request();
        $response = $this->controller->favorites($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('favorites-list', $content);
        $this->assertStringContainsString('voies', $content);
        $this->assertStringContainsString('secteurs', $content);
        $this->assertStringContainsString('sites', $content);
    }

    public function testSettingsPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->settings($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Paramètres', $response->getContent());
    }

    public function testSettingsPageContainsProfileForm(): void
    {
        $request = new Request();
        $response = $this->controller->settings($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('profile-form', $content);
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('first_name', $content);
        $this->assertStringContainsString('last_name', $content);
    }

    public function testSettingsPageContainsPasswordForm(): void
    {
        $request = new Request();
        $response = $this->controller->settings($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('password-form', $content);
        $this->assertStringContainsString('current_password', $content);
        $this->assertStringContainsString('new_password', $content);
        $this->assertStringContainsString('confirm_password', $content);
    }

    public function testSettingsPageContainsPrivacySettings(): void
    {
        $request = new Request();
        $response = $this->controller->settings($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('privacy', $content);
        $this->assertStringContainsString('public_profile', $content);
        $this->assertStringContainsString('notifications', $content);
    }

    public function testPendingPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->pending($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('En attente', $response->getContent());
    }

    public function testPendingPageContainsMessage(): void
    {
        $request = new Request();
        $response = $this->controller->pending($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('validation', $content);
        $this->assertStringContainsString('administrateur', $content);
        $this->assertStringContainsString('attente', $content);
    }

    public function testBannedPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->banned($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Compte suspendu', $response->getContent());
    }

    public function testBannedPageContainsMessage(): void
    {
        $request = new Request();
        $response = $this->controller->banned($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('suspendu', $content);
        $this->assertStringContainsString('contact', $content);
        $this->assertStringContainsString('administrateur', $content);
    }

    public function testSettingsFormsContainCSRFToken(): void
    {
        $request = new Request();
        $response = $this->controller->settings($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testProfilePageContainsRecentActivity(): void
    {
        $request = new Request();
        $response = $this->controller->profile($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('activité récente', $content);
        $this->assertStringContainsString('dernières ascensions', $content);
    }

    public function testAscentsPageContainsPagination(): void
    {
        $request = new Request();
        $response = $this->controller->ascents($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('pagination', $content);
        $this->assertStringContainsString('page', $content);
    }

    public function testAscentsPageContainsExportButton(): void
    {
        $request = new Request();
        $response = $this->controller->ascents($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('export', $content);
        $this->assertStringContainsString('télécharger', $content);
    }

    public function testFavoritesPageContainsActionButtons(): void
    {
        $request = new Request();
        $response = $this->controller->favorites($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Retirer', $content);
        $this->assertStringContainsString('Voir', $content);
    }

    public function testSettingsPageContainsSuccessMessage(): void
    {
        $request = new Request();
        $request->setSession(['success' => 'Profil mis à jour avec succès']);
        $response = $this->controller->settings($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Profil mis à jour avec succès', $content);
    }

    public function testSettingsPageContainsErrorMessages(): void
    {
        $request = new Request();
        $request->setSession(['errors' => ['email' => 'Email invalide']]);
        $response = $this->controller->settings($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Email invalide', $content);
    }
}