<?php

namespace TopoclimbCH\Tests\Integration;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Core\Request;

class FullPageSuiteTest extends TestCase
{
    /**
     * Test complete page functionality for all pages
     */
    public function testCompletePageSuite(): void
    {
        $allPages = [
            // Public pages
            ['GET', '/', 'Homepage'],
            ['GET', '/login', 'Login page'],
            ['GET', '/register', 'Registration page'],
            ['GET', '/forgot-password', 'Forgot password page'],
            ['GET', '/about', 'About page'],
            ['GET', '/contact', 'Contact page'],
            ['GET', '/privacy', 'Privacy page'],
            ['GET', '/terms', 'Terms page'],
            ['GET', '/404', '404 error page'],
            ['GET', '/403', '403 error page'],
            ['GET', '/banned', 'Banned page'],
            
            // Protected pages (will require auth)
            ['GET', '/regions', 'Regions index'],
            ['GET', '/regions/create', 'Region create'],
            ['GET', '/sites', 'Sites index'],
            ['GET', '/sites/create', 'Site create'],
            ['GET', '/sectors', 'Sectors index'],
            ['GET', '/sectors/create', 'Sector create'],
            ['GET', '/routes', 'Routes index'],
            ['GET', '/routes/create', 'Route create'],
            ['GET', '/books', 'Books index'],
            ['GET', '/books/create', 'Book create'],
            ['GET', '/profile', 'User profile'],
            ['GET', '/ascents', 'User ascents'],
            ['GET', '/favorites', 'User favorites'],
            ['GET', '/settings', 'User settings'],
            ['GET', '/pending', 'Pending approval'],
            
            // Admin pages (will require admin auth)
            ['GET', '/admin', 'Admin dashboard'],
            ['GET', '/admin/users', 'Admin users'],
        ];

        $results = [];
        
        foreach ($allPages as [$method, $path, $description]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            try {
                $response = $this->app->handle($request);
                $status = $response->getStatusCode();
                $content = $response->getContent();
                
                $results[] = [
                    'path' => $path,
                    'description' => $description,
                    'status' => $status,
                    'content_length' => strlen($content),
                    'has_html' => strpos($content, '<html') !== false,
                    'has_title' => strpos($content, '<title>') !== false,
                    'has_meta' => strpos($content, '<meta') !== false,
                    'has_css' => strpos($content, '.css') !== false,
                    'has_js' => strpos($content, '.js') !== false || strpos($content, '<script') !== false,
                    'success' => true,
                    'error' => null
                ];
                
            } catch (\Exception $e) {
                $results[] = [
                    'path' => $path,
                    'description' => $description,
                    'status' => 500,
                    'content_length' => 0,
                    'has_html' => false,
                    'has_title' => false,
                    'has_meta' => false,
                    'has_css' => false,
                    'has_js' => false,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->generateTestReport($results);
        
        // Assert that all pages either load successfully or return expected auth redirects
        $failedPages = array_filter($results, function($result) {
            return !$result['success'] || ($result['status'] === 500);
        });
        
        $this->assertEmpty($failedPages, 
            "Some pages failed to load: " . json_encode($failedPages, JSON_PRETTY_PRINT));
    }

    /**
     * Generate a comprehensive test report
     */
    private function generateTestReport(array $results): void
    {
        $reportPath = '/tmp/topoclimb_page_test_report.json';
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_pages' => count($results),
            'successful_pages' => count(array_filter($results, fn($r) => $r['success'])),
            'failed_pages' => count(array_filter($results, fn($r) => !$r['success'])),
            'public_pages' => count(array_filter($results, fn($r) => $r['status'] === 200)),
            'protected_pages' => count(array_filter($results, fn($r) => in_array($r['status'], [302, 403]))),
            'error_pages' => count(array_filter($results, fn($r) => $r['status'] >= 400)),
            'pages_with_html' => count(array_filter($results, fn($r) => $r['has_html'])),
            'pages_with_css' => count(array_filter($results, fn($r) => $r['has_css'])),
            'pages_with_js' => count(array_filter($results, fn($r) => $r['has_js'])),
            'average_content_length' => array_sum(array_column($results, 'content_length')) / count($results),
            'results' => $results
        ];
        
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        // Assert report generation
        $this->assertFileExists($reportPath, "Test report should be generated");
        $this->assertGreaterThan(0, filesize($reportPath), "Test report should have content");
    }

    /**
     * Test page accessibility features
     */
    public function testPageAccessibilityFeatures(): void
    {
        $publicPages = [
            '/',
            '/login',
            '/register',
            '/about',
            '/contact',
            '/privacy',
            '/terms',
        ];

        foreach ($publicPages as $path) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                
                // Test accessibility features
                $this->assertStringContainsString('lang=', $content,
                    "Page $path should have language attribute");
                
                $this->assertStringContainsString('charset=', $content,
                    "Page $path should have charset meta tag");
                
                $this->assertStringContainsString('viewport', $content,
                    "Page $path should have viewport meta tag");
                
                $this->assertStringContainsString('<title>', $content,
                    "Page $path should have title tag");
                
                // Check for basic semantic HTML
                $this->assertTrue(
                    strpos($content, '<main') !== false ||
                    strpos($content, '<article') !== false ||
                    strpos($content, '<section') !== false ||
                    strpos($content, '<header') !== false ||
                    strpos($content, '<footer') !== false,
                    "Page $path should use semantic HTML elements"
                );
            }
        }
    }

    /**
     * Test page SEO features
     */
    public function testPageSeoFeatures(): void
    {
        $publicPages = [
            '/',
            '/about',
            '/contact',
            '/privacy',
            '/terms',
        ];

        foreach ($publicPages as $path) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                
                // Test SEO features
                $this->assertStringContainsString('<title>', $content,
                    "Page $path should have title tag");
                
                $this->assertTrue(
                    strpos($content, 'description') !== false ||
                    strpos($content, 'keywords') !== false,
                    "Page $path should have meta description or keywords"
                );
                
                $this->assertTrue(
                    strpos($content, '<h1') !== false ||
                    strpos($content, '<h2') !== false,
                    "Page $path should have heading tags"
                );
            }
        }
    }

    /**
     * Test page security features
     */
    public function testPageSecurityFeatures(): void
    {
        $formsPages = [
            '/login',
            '/register',
            '/forgot-password',
        ];

        foreach ($formsPages as $path) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                
                // Test security features
                $this->assertStringContainsString('csrf_token', $content,
                    "Page $path should have CSRF token");
                
                $this->assertStringContainsString('name="_token"', $content,
                    "Page $path should have CSRF token field");
                
                $this->assertTrue(
                    strpos($content, 'method="POST"') !== false ||
                    strpos($content, 'method="post"') !== false,
                    "Page $path should use POST method for forms"
                );
            }
        }
    }

    /**
     * Test page performance characteristics
     */
    public function testPagePerformanceCharacteristics(): void
    {
        $testPages = [
            '/',
            '/login',
            '/about',
            '/contact',
        ];

        foreach ($testPages as $path) {
            $startTime = microtime(true);
            
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $endTime = microtime(true);
            $loadTime = $endTime - $startTime;
            
            // Performance assertions
            $this->assertLessThan(3.0, $loadTime,
                "Page $path should load in under 3 seconds");
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                
                // Content size assertions
                $this->assertLessThan(500000, strlen($content),
                    "Page $path should not exceed 500KB");
                
                $this->assertGreaterThan(100, strlen($content),
                    "Page $path should have meaningful content");
            }
        }
    }

    /**
     * Test page error handling
     */
    public function testPageErrorHandling(): void
    {
        $errorScenarios = [
            ['/nonexistent-page', 404],
            ['/regions/99999', 404],
            ['/sites/invalid', 404],
            ['/routes/nonexistent', 404],
        ];

        foreach ($errorScenarios as [$path, $expectedStatus]) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertEquals($expectedStatus, $response->getStatusCode(),
                "Path $path should return status $expectedStatus");
            
            $content = $response->getContent();
            $this->assertNotEmpty($content,
                "Error page should have content");
            
            $this->assertStringContainsString('<!DOCTYPE html>', $content,
                "Error page should be valid HTML");
        }
    }

    /**
     * Test page consistency
     */
    public function testPageConsistency(): void
    {
        $publicPages = [
            '/',
            '/about',
            '/contact',
            '/privacy',
            '/terms',
        ];

        $commonElements = [];
        
        foreach ($publicPages as $path) {
            $request = new Request();
            $request->setMethod('GET');
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                
                // Check for common elements
                $hasNav = strpos($content, '<nav') !== false || strpos($content, 'navigation') !== false;
                $hasFooter = strpos($content, '<footer') !== false || strpos($content, 'footer') !== false;
                $hasTitle = strpos($content, 'TopoclimbCH') !== false;
                
                $commonElements[$path] = [
                    'navigation' => $hasNav,
                    'footer' => $hasFooter,
                    'site_title' => $hasTitle,
                ];
            }
        }
        
        // Assert consistency across pages
        $this->assertNotEmpty($commonElements, "Should have tested some pages");
        
        foreach ($commonElements as $path => $elements) {
            $this->assertTrue($elements['site_title'],
                "Page $path should contain site title");
        }
    }
}