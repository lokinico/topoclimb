<?php

namespace Tests\Integration\Controllers;

use Tests\TestCase;
use TopoclimbCH\Controllers\HomeController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class HomeControllerTest extends TestCase
{
    private HomeController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(HomeController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('TopoclimbCH', $response->getContent());
    }

    public function testIndexPageContainsStats(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('sites d\'escalade', $content);
        $this->assertStringContainsString('secteurs', $content);
        $this->assertStringContainsString('voies', $content);
        $this->assertStringContainsString('utilisateurs', $content);
    }

    public function testIndexPageContainsSearchForm(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search-form', $content);
        $this->assertStringContainsString('input', $content);
    }

    public function testIndexPageContainsNavigationTabs(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Sites', $content);
        $this->assertStringContainsString('Guides', $content);
        $this->assertStringContainsString('Voies', $content);
    }

    public function testAboutPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->about($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('TopoclimbCH', $response->getContent());
    }

    public function testContactPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->contact($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Contact', $response->getContent());
    }

    public function testPrivacyPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->privacy($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Politique de confidentialité', $response->getContent());
    }

    public function testTermsPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->terms($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Conditions d\'utilisation', $response->getContent());
    }

    public function testDebugTestPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->debugTest($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}