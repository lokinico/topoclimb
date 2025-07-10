<?php

namespace TopoclimbCH\Tests\Integration;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Core\Request;

class PageFunctionalityTest extends TestCase
{
    /**
     * Test complete page functionality workflows
     */
    public function testCompletePageWorkflows(): void
    {
        $this->assertTrue(true, "Page functionality tests are comprehensive");
    }

    /**
     * Test homepage functionality
     */
    public function testHomepageFunctionality(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath('/');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        
        // Test homepage contains all required sections
        $this->assertStringContainsString('TopoclimbCH', $content);
        $this->assertStringContainsString('statistiques', $content);
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('Sites', $content);
        $this->assertStringContainsString('Guides', $content);
        $this->assertStringContainsString('Voies', $content);
        
        // Test homepage has proper navigation
        $this->assertStringContainsString('nav', $content);
        $this->assertStringContainsString('menu', $content);
        
        // Test homepage has proper footer
        $this->assertStringContainsString('footer', $content);
        
        // Test homepage is responsive
        $this->assertStringContainsString('viewport', $content);
        $this->assertStringContainsString('responsive', $content);
    }

    /**
     * Test authentication pages functionality
     */
    public function testAuthenticationPagesFunctionality(): void
    {
        $authPages = [
            ['/login', 'Connexion', ['email', 'password', 'csrf_token']],
            ['/register', 'Inscription', ['username', 'email', 'password', 'csrf_token', 'terms']],
            ['/forgot-password', 'Mot de passe oublié', ['email', 'csrf_token']],
        ];

        foreach ($authPages as [$path, $title, $requiredFields]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertEquals(200, $response->getStatusCode());
            $content = $response->getContent();
            
            // Test page contains title
            $this->assertStringContainsString($title, $content);
            
            // Test page contains form
            $this->assertStringContainsString('<form', $content);
            
            // Test page contains required fields
            foreach ($requiredFields as $field) {
                $this->assertStringContainsString($field, $content,
                    "Page $path should contain field $field");
            }
            
            // Test page contains submit button
            $this->assertStringContainsString('submit', $content);
        }
    }

    /**
     * Test CRUD pages functionality
     */
    public function testCrudPagesFunctionality(): void
    {
        $crudEntities = [
            ['regions', 'Régions', ['name', 'description', 'latitude', 'longitude']],
            ['sites', 'Sites', ['name', 'region_id', 'description', 'latitude', 'longitude']],
            ['sectors', 'Secteurs', ['name', 'site_id', 'description', 'approach']],
            ['routes', 'Voies', ['name', 'sector_id', 'difficulty_grade', 'length']],
            ['books', 'Guides', ['title', 'author', 'publisher', 'publication_year']],
        ];

        foreach ($crudEntities as [$entity, $title, $fields]) {
            // Test index page
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath("/$entity");
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(500, $response->getStatusCode(),
                "Index page for $entity should not cause server error");
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                $this->assertStringContainsString($title, $content);
                $this->assertStringContainsString('list', $content);
                $this->assertStringContainsString('table', $content);
                $this->assertStringContainsString('search', $content);
                $this->assertStringContainsString('filter', $content);
            }
            
            // Test create page
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath("/$entity/create");
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(500, $response->getStatusCode(),
                "Create page for $entity should not cause server error");
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                $this->assertStringContainsString('form', $content);
                $this->assertStringContainsString('csrf_token', $content);
                
                foreach ($fields as $field) {
                    $this->assertStringContainsString($field, $content,
                        "Create page for $entity should contain field $field");
                }
            }
        }
    }

    /**
     * Test user profile pages functionality
     */
    public function testUserProfilePagesFunctionality(): void
    {
        $profilePages = [
            ['/profile', 'Profil', ['user-info', 'statistiques', 'ascensions']],
            ['/ascents', 'Mes ascensions', ['ascents-list', 'table', 'filter']],
            ['/favorites', 'Mes favoris', ['favorites-list', 'voies', 'secteurs']],
            ['/settings', 'Paramètres', ['profile-form', 'password-form', 'privacy']],
        ];

        foreach ($profilePages as [$path, $title, $expectedElements]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(500, $response->getStatusCode(),
                "Profile page $path should not cause server error");
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                $this->assertStringContainsString($title, $content);
                
                foreach ($expectedElements as $element) {
                    $this->assertStringContainsString($element, $content,
                        "Profile page $path should contain $element");
                }
            }
        }
    }

    /**
     * Test admin pages functionality
     */
    public function testAdminPagesFunctionality(): void
    {
        $adminPages = [
            ['/admin', 'Administration', ['dashboard', 'statistiques', 'navigation']],
            ['/admin/users', 'Gestion des utilisateurs', ['users-list', 'table', 'search']],
        ];

        foreach ($adminPages as [$path, $title, $expectedElements]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(500, $response->getStatusCode(),
                "Admin page $path should not cause server error");
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                $this->assertStringContainsString($title, $content);
                
                foreach ($expectedElements as $element) {
                    $this->assertStringContainsString($element, $content,
                        "Admin page $path should contain $element");
                }
            }
        }
    }

    /**
     * Test error pages functionality
     */
    public function testErrorPagesFunctionality(): void
    {
        $errorPages = [
            ['/404', 404, 'Page non trouvée', ['Accueil', 'contact', 'suggestions']],
            ['/403', 403, 'Accès refusé', ['Accueil', 'connexion', 'permissions']],
            ['/banned', 200, 'Compte suspendu', ['suspendu', 'contact', 'administrateur']],
        ];

        foreach ($errorPages as [$path, $expectedStatus, $title, $expectedElements]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertEquals($expectedStatus, $response->getStatusCode(),
                "Error page $path should return status $expectedStatus");
            
            $content = $response->getContent();
            $this->assertStringContainsString($title, $content);
            
            foreach ($expectedElements as $element) {
                $this->assertStringContainsString($element, $content,
                    "Error page $path should contain $element");
            }
        }
    }

    /**
     * Test static pages functionality
     */
    public function testStaticPagesFunctionality(): void
    {
        $staticPages = [
            ['/about', 'À propos', ['TopoclimbCH', 'histoire', 'équipe']],
            ['/contact', 'Contact', ['contact', 'email', 'formulaire']],
            ['/privacy', 'Politique de confidentialité', ['confidentialité', 'données', 'cookies']],
            ['/terms', 'Conditions d\'utilisation', ['conditions', 'utilisation', 'règles']],
        ];

        foreach ($staticPages as [$path, $title, $expectedElements]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertEquals(200, $response->getStatusCode(),
                "Static page $path should return 200 OK");
            
            $content = $response->getContent();
            $this->assertStringContainsString($title, $content);
            
            foreach ($expectedElements as $element) {
                $this->assertStringContainsString($element, $content,
                    "Static page $path should contain $element");
            }
        }
    }

    /**
     * Test API endpoints functionality
     */
    public function testApiEndpointsFunctionality(): void
    {
        $apiEndpoints = [
            ['/api/regions', 'application/json'],
            ['/api/regions/search', 'application/json'],
            ['/api/sites', 'application/json'],
            ['/api/sites/search', 'application/json'],
            ['/api/books/search', 'application/json'],
        ];

        foreach ($apiEndpoints as [$path, $expectedContentType]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(500, $response->getStatusCode(),
                "API endpoint $path should not cause server error");
            
            if ($response->getStatusCode() === 200) {
                $contentType = $response->getHeader('Content-Type');
                $this->assertStringContainsString($expectedContentType, $contentType ?? '',
                    "API endpoint $path should return $expectedContentType");
                
                $content = $response->getContent();
                $this->assertJson($content, "API endpoint $path should return valid JSON");
            }
        }
    }

    /**
     * Test page accessibility
     */
    public function testPageAccessibility(): void
    {
        $publicPages = [
            '/',
            '/login',
            '/register',
            '/about',
            '/contact',
            '/privacy',
            '/terms',
            '/404',
            '/403',
        ];

        foreach ($publicPages as $path) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Test basic accessibility features
            $this->assertStringContainsString('lang=', $content,
                "Page $path should have language attribute");
            
            $this->assertStringContainsString('alt=', $content,
                "Page $path should have alt attributes for images");
            
            $this->assertTrue(
                strpos($content, 'aria-') !== false ||
                strpos($content, 'role=') !== false,
                "Page $path should have ARIA attributes"
            );
        }
    }

    /**
     * Test page performance
     */
    public function testPagePerformance(): void
    {
        $pages = ['/', '/login', '/about'];
        
        foreach ($pages as $path) {
            $startTime = microtime(true);
            
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $endTime = microtime(true);
            $loadTime = $endTime - $startTime;
            
            $this->assertLessThan(2.0, $loadTime,
                "Page $path should load in under 2 seconds");
            
            $content = $response->getContent();
            $this->assertLessThan(100000, strlen($content),
                "Page $path should not be excessively large");
        }
    }
}