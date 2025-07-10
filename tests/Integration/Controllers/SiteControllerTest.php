<?php

namespace TopoclimbCH\Tests\Integration\Controllers;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Controllers\SiteController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class SiteControllerTest extends TestCase
{
    private SiteController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(SiteController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Sites', $response->getContent());
    }

    public function testIndexPageContainsSitesList(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('site-list', $content);
        $this->assertStringContainsString('table', $content);
    }

    public function testIndexPageContainsSearchAndFilters(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('filter', $content);
        $this->assertStringContainsString('région', $content);
    }

    public function testCreateFormLoads(): void
    {
        $request = new Request();
        $response = $this->controller->form($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Nouveau site', $response->getContent());
    }

    public function testCreateFormContainsRequiredFields(): void
    {
        $request = new Request();
        $response = $this->controller->form($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('region_id', $content);
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

    public function testShowPageContainsSiteDetails(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('site-details', $content);
        $this->assertStringContainsString('secteurs', $content);
        $this->assertStringContainsString('voies', $content);
    }

    public function testShowPageContainsMap(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('map', $content);
        $this->assertStringContainsString('latitude', $content);
        $this->assertStringContainsString('longitude', $content);
    }

    public function testEditFormLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->form($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Modifier le site', $response->getContent());
    }

    public function testEditFormContainsPrefilledData(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->form($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('value=', $content);
        $this->assertStringContainsString('selected', $content);
    }

    public function testApiIndexReturnsJson(): void
    {
        $request = new Request();
        $response = $this->controller->apiIndex($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testApiSearchReturnsJson(): void
    {
        $request = new Request();
        $request->setQueryParam('q', 'test');
        $response = $this->controller->apiSearch($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testApiShowReturnsJson(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->apiShow($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testCreateFormContainsCSRFToken(): void
    {
        $request = new Request();
        $response = $this->controller->form($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testEditFormContainsCSRFToken(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->form($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testIndexPageContainsPagination(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('pagination', $content);
        $this->assertStringContainsString('page', $content);
    }

    public function testShowPageContainsActionButtons(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Modifier', $content);
        $this->assertStringContainsString('Supprimer', $content);
        $this->assertStringContainsString('Ajouter secteur', $content);
    }
}