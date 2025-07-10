<?php

namespace Tests\Integration\Controllers;

use Tests\TestCase;
use TopoclimbCH\Controllers\RouteController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class RouteControllerTest extends TestCase
{
    private RouteController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(RouteController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Voies', $response->getContent());
    }

    public function testIndexPageContainsRoutesList(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('route-list', $content);
        $this->assertStringContainsString('table', $content);
    }

    public function testIndexPageContainsSearchAndFilters(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('filter', $content);
        $this->assertStringContainsString('cotation', $content);
        $this->assertStringContainsString('secteur', $content);
    }

    public function testCreatePageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Nouvelle voie', $response->getContent());
    }

    public function testCreatePageContainsForm(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('sector_id', $content);
        $this->assertStringContainsString('difficulty_grade', $content);
        $this->assertStringContainsString('length', $content);
        $this->assertStringContainsString('description', $content);
    }

    public function testShowPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowPageContainsRouteDetails(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('route-details', $content);
        $this->assertStringContainsString('cotation', $content);
        $this->assertStringContainsString('longueur', $content);
        $this->assertStringContainsString('équipement', $content);
    }

    public function testShowPageContainsAscentsList(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('ascensions', $content);
        $this->assertStringContainsString('répétitions', $content);
        $this->assertStringContainsString('premières', $content);
    }

    public function testEditPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->edit($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Modifier la voie', $response->getContent());
    }

    public function testEditPageContainsForm(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->edit($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('difficulty_grade', $content);
        $this->assertStringContainsString('method="POST"', $content);
    }

    public function testDeletePageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->delete($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Supprimer la voie', $response->getContent());
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

    public function testLogAscentPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->logAscent($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Logger une ascension', $response->getContent());
    }

    public function testLogAscentPageContainsForm(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->logAscent($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('ascent_date', $content);
        $this->assertStringContainsString('ascent_type', $content);
        $this->assertStringContainsString('comment', $content);
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
        $this->assertStringContainsString('Logger ascension', $content);
    }

    public function testShowPageContainsRouteStats(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('statistiques', $content);
        $this->assertStringContainsString('ascensions', $content);
        $this->assertStringContainsString('popularité', $content);
    }

    public function testLogAscentFormContainsCSRFToken(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->logAscent($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }
}