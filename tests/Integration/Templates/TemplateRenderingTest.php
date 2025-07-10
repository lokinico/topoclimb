<?php

namespace Tests\Integration\Templates;

use Tests\TestCase;
use TopoclimbCH\Core\Request;

class TemplateRenderingTest extends TestCase
{
    /**
     * Test all templates render without errors
     */
    public function testAllTemplatesRender(): void
    {
        $templateRoutes = [
            ['GET', '/', 'homepage'],
            ['GET', '/login', 'login form'],
            ['GET', '/register', 'registration form'],
            ['GET', '/forgot-password', 'forgot password form'],
            ['GET', '/about', 'about page'],
            ['GET', '/contact', 'contact page'],
            ['GET', '/privacy', 'privacy page'],
            ['GET', '/terms', 'terms page'],
            ['GET', '/404', '404 error page'],
            ['GET', '/403', '403 error page'],
            ['GET', '/banned', 'banned page'],
        ];

        foreach ($templateRoutes as [$method, $path, $description]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(500, $response->getStatusCode(),
                "Template for $description should render without errors");
            
            $content = $response->getContent();
            $this->assertNotEmpty($content,
                "Template for $description should have content");
        }
    }

    /**
     * Test all templates contain proper HTML structure
     */
    public function testTemplatesHaveProperHtmlStructure(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/login'],
            ['GET', '/about'],
            ['GET', '/404'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check basic HTML structure
            $this->assertStringContainsString('<!DOCTYPE html>', $content,
                "Template at $path should have DOCTYPE declaration");
            
            $this->assertStringContainsString('<html', $content,
                "Template at $path should have HTML tag");
            
            $this->assertStringContainsString('<head>', $content,
                "Template at $path should have HEAD section");
            
            $this->assertStringContainsString('<body', $content,
                "Template at $path should have BODY tag");
            
            $this->assertStringContainsString('</html>', $content,
                "Template at $path should close HTML tag");
        }
    }

    /**
     * Test all templates contain required meta tags
     */
    public function testTemplatesHaveRequiredMetaTags(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/login'],
            ['GET', '/about'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check required meta tags
            $this->assertStringContainsString('charset=', $content,
                "Template at $path should have charset meta tag");
            
            $this->assertStringContainsString('viewport', $content,
                "Template at $path should have viewport meta tag");
            
            $this->assertStringContainsString('<title>', $content,
                "Template at $path should have title tag");
        }
    }

    /**
     * Test all templates include CSS and JS
     */
    public function testTemplatesIncludeAssetsCorrectly(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/login'],
            ['GET', '/about'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check CSS includes
            $this->assertStringContainsString('stylesheet', $content,
                "Template at $path should include CSS");
            
            $this->assertStringContainsString('.css', $content,
                "Template at $path should reference CSS files");
            
            // Check JS includes (if any)
            $this->assertTrue(
                strpos($content, '<script') !== false || 
                strpos($content, '.js') !== false,
                "Template at $path should include JavaScript"
            );
        }
    }

    /**
     * Test homepage template contains all required sections
     */
    public function testHomepageTemplateCompleteness(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath('/');
        
        $response = $this->app->handle($request);
        $content = $response->getContent();
        
        // Check homepage specific content
        $this->assertStringContainsString('TopoclimbCH', $content,
            "Homepage should contain site name");
        
        $this->assertStringContainsString('statistiques', $content,
            "Homepage should contain statistics section");
        
        $this->assertStringContainsString('search', $content,
            "Homepage should contain search functionality");
        
        $this->assertStringContainsString('Sites', $content,
            "Homepage should contain sites section");
        
        $this->assertStringContainsString('Guides', $content,
            "Homepage should contain guides section");
        
        $this->assertStringContainsString('Voies', $content,
            "Homepage should contain routes section");
    }

    /**
     * Test login template contains form elements
     */
    public function testLoginTemplateFormElements(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath('/login');
        
        $response = $this->app->handle($request);
        $content = $response->getContent();
        
        // Check form elements
        $this->assertStringContainsString('<form', $content,
            "Login page should contain form");
        
        $this->assertStringContainsString('email', $content,
            "Login page should contain email field");
        
        $this->assertStringContainsString('password', $content,
            "Login page should contain password field");
        
        $this->assertStringContainsString('csrf_token', $content,
            "Login page should contain CSRF token");
        
        $this->assertStringContainsString('submit', $content,
            "Login page should contain submit button");
    }

    /**
     * Test registration template contains form elements
     */
    public function testRegistrationTemplateFormElements(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath('/register');
        
        $response = $this->app->handle($request);
        $content = $response->getContent();
        
        // Check form elements
        $this->assertStringContainsString('<form', $content,
            "Registration page should contain form");
        
        $this->assertStringContainsString('username', $content,
            "Registration page should contain username field");
        
        $this->assertStringContainsString('email', $content,
            "Registration page should contain email field");
        
        $this->assertStringContainsString('password', $content,
            "Registration page should contain password field");
        
        $this->assertStringContainsString('csrf_token', $content,
            "Registration page should contain CSRF token");
        
        $this->assertStringContainsString('terms', $content,
            "Registration page should contain terms checkbox");
    }

    /**
     * Test error templates contain proper error messages
     */
    public function testErrorTemplatesContent(): void
    {
        $errorRoutes = [
            ['GET', '/404', '404', 'Page non trouvée'],
            ['GET', '/403', '403', 'Accès refusé'],
        ];

        foreach ($errorRoutes as [$method, $path, $errorCode, $errorMessage]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            $this->assertStringContainsString($errorCode, $content,
                "Error page $path should contain error code");
            
            $this->assertStringContainsString($errorMessage, $content,
                "Error page $path should contain error message");
            
            $this->assertStringContainsString('Accueil', $content,
                "Error page $path should contain home link");
        }
    }

    /**
     * Test templates handle empty data gracefully
     */
    public function testTemplatesHandleEmptyData(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath('/');
        
        $response = $this->app->handle($request);
        
        $this->assertNotEquals(500, $response->getStatusCode(),
            "Templates should handle empty data without errors");
        
        $content = $response->getContent();
        $this->assertNotEmpty($content,
            "Templates should render content even with empty data");
    }

    /**
     * Test templates contain navigation elements
     */
    public function testTemplatesContainNavigation(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/about'],
            ['GET', '/contact'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check navigation elements
            $this->assertTrue(
                strpos($content, '<nav') !== false || 
                strpos($content, 'navigation') !== false ||
                strpos($content, 'menu') !== false,
                "Template at $path should contain navigation"
            );
        }
    }

    /**
     * Test templates contain footer elements
     */
    public function testTemplatesContainFooter(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/about'],
            ['GET', '/contact'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check footer elements
            $this->assertTrue(
                strpos($content, '<footer') !== false || 
                strpos($content, 'footer') !== false,
                "Template at $path should contain footer"
            );
        }
    }

    /**
     * Test templates are responsive
     */
    public function testTemplatesAreResponsive(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/login'],
            ['GET', '/about'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check responsive design indicators
            $this->assertStringContainsString('viewport', $content,
                "Template at $path should have viewport meta tag");
            
            $this->assertTrue(
                strpos($content, 'responsive') !== false ||
                strpos($content, 'mobile') !== false ||
                strpos($content, 'container') !== false ||
                strpos($content, 'grid') !== false,
                "Template at $path should have responsive design elements"
            );
        }
    }

    /**
     * Test templates contain SEO elements
     */
    public function testTemplatesContainSeoElements(): void
    {
        $templateRoutes = [
            ['GET', '/'],
            ['GET', '/about'],
            ['GET', '/contact'],
        ];

        foreach ($templateRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            $content = $response->getContent();
            
            // Check SEO elements
            $this->assertStringContainsString('<title>', $content,
                "Template at $path should have title tag");
            
            $this->assertTrue(
                strpos($content, 'description') !== false ||
                strpos($content, 'keywords') !== false,
                "Template at $path should have meta description or keywords"
            );
        }
    }
}