<?php

namespace Tests\Integration\Controllers;

use Tests\TestCase;
use TopoclimbCH\Controllers\RegionController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class RegionControllerTest extends TestCase
{
    private RegionController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(RegionController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Régions', $response->getContent());
    }

    public function testIndexPageContainsRegionsList(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('region-list', $content);
        $this->assertStringContainsString('table', $content);
    }

    public function testIndexPageContainsSearchFilters(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('filter', $content);
    }

    public function testCreatePageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Nouvelle région', $response->getContent());
    }

    public function testCreatePageContainsForm(): void
    {
        $request = new Request();
        $response = $this->controller->create($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('description', $content);
        $this->assertStringContainsString('latitude', $content);
        $this->assertStringContainsString('longitude', $content);
    }

    public function testShowPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowPageContainsRegionDetails(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('region-details', $content);
        $this->assertStringContainsString('sites', $content);
        $this->assertStringContainsString('secteurs', $content);
    }

    public function testShowPageContainsWeatherWidget(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('weather', $content);
        $this->assertStringContainsString('météo', $content);
    }

    public function testEditPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->edit($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Modifier la région', $response->getContent());
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

    public function testApiIndexReturnsJson(): void
    {
        $request = new Request();
        $response = $this->controller->apiIndex($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testSearchApiReturnsJson(): void
    {
        $request = new Request();
        $request->setQueryParam('q', 'test');
        $response = $this->controller->search($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testWeatherApiReturnsJson(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->weather($request);
        
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
}