<?php

namespace TopoclimbCH\Tests\Integration\Controllers;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Controllers\ErrorController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class ErrorControllerTest extends TestCase
{
    private ErrorController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(ErrorController::class);
    }

    public function testNotFoundPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('404', $response->getContent());
    }

    public function testNotFoundPageContainsErrorMessage(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Page non trouvée', $content);
        $this->assertStringContainsString('404', $content);
        $this->assertStringContainsString('introuvable', $content);
    }

    public function testNotFoundPageContainsNavigationLinks(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Accueil', $content);
        $this->assertStringContainsString('href', $content);
        $this->assertStringContainsString('retour', $content);
    }

    public function testNotFoundPageContainsHelpfulSuggestions(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('suggestions', $content);
        $this->assertStringContainsString('régions', $content);
        $this->assertStringContainsString('voies', $content);
        $this->assertStringContainsString('guides', $content);
    }

    public function testForbiddenPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('403', $response->getContent());
    }

    public function testForbiddenPageContainsErrorMessage(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Accès refusé', $content);
        $this->assertStringContainsString('403', $content);
        $this->assertStringContainsString('autorisé', $content);
    }

    public function testForbiddenPageContainsNavigationLinks(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Accueil', $content);
        $this->assertStringContainsString('href', $content);
        $this->assertStringContainsString('retour', $content);
    }

    public function testForbiddenPageContainsLoginSuggestion(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('connexion', $content);
        $this->assertStringContainsString('connecter', $content);
        $this->assertStringContainsString('login', $content);
    }

    public function testForbiddenPageContainsPermissionExplanation(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('permissions', $content);
        $this->assertStringContainsString('droits', $content);
        $this->assertStringContainsString('accès', $content);
    }

    public function testNotFoundPageContainsSearchForm(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('recherche', $content);
        $this->assertStringContainsString('input', $content);
    }

    public function testNotFoundPageContainsProperLayout(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('<head>', $content);
        $this->assertStringContainsString('<body', $content);
    }

    public function testForbiddenPageContainsProperLayout(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('<head>', $content);
        $this->assertStringContainsString('<body', $content);
    }

    public function testNotFoundPageContainsMetaTags(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('<meta', $content);
        $this->assertStringContainsString('charset', $content);
        $this->assertStringContainsString('viewport', $content);
    }

    public function testForbiddenPageContainsMetaTags(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('<meta', $content);
        $this->assertStringContainsString('charset', $content);
        $this->assertStringContainsString('viewport', $content);
    }

    public function testNotFoundPageContainsCSS(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('css', $content);
        $this->assertStringContainsString('stylesheet', $content);
    }

    public function testForbiddenPageContainsCSS(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('css', $content);
        $this->assertStringContainsString('stylesheet', $content);
    }

    public function testNotFoundPageContainsContactInfo(): void
    {
        $request = new Request();
        $response = $this->controller->notFound($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('contact', $content);
        $this->assertStringContainsString('aide', $content);
        $this->assertStringContainsString('support', $content);
    }

    public function testForbiddenPageContainsContactInfo(): void
    {
        $request = new Request();
        $response = $this->controller->forbidden($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('contact', $content);
        $this->assertStringContainsString('aide', $content);
        $this->assertStringContainsString('support', $content);
    }
}