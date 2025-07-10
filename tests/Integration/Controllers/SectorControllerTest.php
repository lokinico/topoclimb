<?php

namespace TopoclimbCH\Tests\Integration\Controllers;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Controllers\SectorController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class SectorControllerTest extends TestCase
{
    private SectorController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(SectorController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Secteurs', $response->getContent());
    }

    public function testIndexPageContainsSectorsList(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('sector-list', $content);
        $this->assertStringContainsString('table', $content);
    }

    public function testIndexPageContainsFilters(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('filter', $content);
        $this->assertStringContainsString('site', $content);
        $this->assertStringContainsString('région', $content);
    }

    public function testCreatePageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Nouveau secteur', $response->getContent());
    }

    public function testCreatePageContainsForm(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('site_id', $content);
        $this->assertStringContainsString('description', $content);
        $this->assertStringContainsString('approach', $content);
    }

    public function testShowPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowPageContainsSectorDetails(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('sector-details', $content);
        $this->assertStringContainsString('voies', $content);
        $this->assertStringContainsString('approche', $content);
    }

    public function testShowPageContainsRoutesList(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('routes-list', $content);
        $this->assertStringContainsString('cotation', $content);
        $this->assertStringContainsString('longueur', $content);
    }

    public function testEditPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->edit($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Modifier le secteur', $response->getContent());
    }

    public function testEditPageContainsForm(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->edit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('description', $content);
        $this->assertStringContainsString('method="POST"', $content);
    }

    public function testDeletePageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->delete($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Supprimer le secteur', $response->getContent());
    }

    public function testDeletePageContainsWarning(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->delete($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('attention', $content);
        $this->assertStringContainsString('suppression', $content);
        $this->assertStringContainsString('définitive', $content);
    }

    public function testGetRoutesApiReturnsJson(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->getRoutes($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testCreateFormContainsCSRFToken(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testEditFormContainsCSRFToken(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->edit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testShowPageContainsActionButtons(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Modifier', $content);
        $this->assertStringContainsString('Supprimer', $content);
        $this->assertStringContainsString('Ajouter voie', $content);
    }

    public function testShowPageContainsSectorStats(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('statistiques', $content);
        $this->assertStringContainsString('nombre de voies', $content);
        $this->assertStringContainsString('cotation', $content);
    }
}