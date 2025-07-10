<?php

namespace Tests\Integration\Controllers;

use Tests\TestCase;
use TopoclimbCH\Controllers\BookController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class BookControllerTest extends TestCase
{
    private BookController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(BookController::class);
    }

    public function testIndexPageLoads(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Guides', $response->getContent());
    }

    public function testIndexPageContainsBooksList(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('book-list', $content);
        $this->assertStringContainsString('table', $content);
    }

    public function testIndexPageContainsSearchAndFilters(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('filter', $content);
        $this->assertStringContainsString('auteur', $content);
        $this->assertStringContainsString('année', $content);
    }

    public function testCreateFormLoads(): void
    {
        $request = new Request();
        $response = $this->controller->form($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Nouveau guide', $response->getContent());
    }

    public function testCreateFormContainsRequiredFields(): void
    {
        $request = new Request();
        $response = $this->controller->form($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('title', $content);
        $this->assertStringContainsString('author', $content);
        $this->assertStringContainsString('publisher', $content);
        $this->assertStringContainsString('publication_year', $content);
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

    public function testShowPageContainsBookDetails(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('book-details', $content);
        $this->assertStringContainsString('auteur', $content);
        $this->assertStringContainsString('éditeur', $content);
        $this->assertStringContainsString('année', $content);
    }

    public function testShowPageContainsSectorsList(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('secteurs', $content);
        $this->assertStringContainsString('voies', $content);
        $this->assertStringContainsString('pages', $content);
    }

    public function testEditFormLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->form($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Modifier le guide', $response->getContent());
    }

    public function testEditFormContainsPrefilledData(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->form($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('form', $content);
        $this->assertStringContainsString('value=', $content);
        $this->assertStringContainsString('method="POST"', $content);
    }

    public function testSectorSelectorPageLoads(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->sectorSelector($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Sélectionner des secteurs', $response->getContent());
    }

    public function testSectorSelectorContainsAvailableSectors(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->sectorSelector($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('secteurs disponibles', $content);
        $this->assertStringContainsString('checkbox', $content);
        $this->assertStringContainsString('select', $content);
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

    public function testApiSectorsReturnsJson(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->apiSectors($request);
        
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

    public function testShowPageContainsActionButtons(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Modifier', $content);
        $this->assertStringContainsString('Supprimer', $content);
        $this->assertStringContainsString('Gérer secteurs', $content);
    }

    public function testShowPageContainsBookStats(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->show($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('statistiques', $content);
        $this->assertStringContainsString('nombre de secteurs', $content);
        $this->assertStringContainsString('nombre de voies', $content);
    }

    public function testIndexPageContainsPagination(): void
    {
        $request = new Request();
        $response = $this->controller->index($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('pagination', $content);
        $this->assertStringContainsString('page', $content);
    }

    public function testSectorSelectorContainsCSRFToken(): void
    {
        $request = new Request();
        $request->setRouteParam('id', '1');
        $response = $this->controller->sectorSelector($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }
}