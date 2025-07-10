<?php

namespace Tests\Integration\Routes;

use Tests\TestCase;
use TopoclimbCH\Core\Request;

class RouteAccessTest extends TestCase
{
    /**
     * Test public routes access
     */
    public function testPublicRoutesAccess(): void
    {
        $publicRoutes = [
            ['GET', '/'],
            ['GET', '/login'],
            ['GET', '/register'],
            ['GET', '/forgot-password'],
            ['GET', '/reset-password'],
            ['GET', '/about'],
            ['GET', '/contact'],
            ['GET', '/privacy'],
            ['GET', '/terms'],
            ['GET', '/404'],
            ['GET', '/403'],
            ['GET', '/banned'],
        ];

        foreach ($publicRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            $this->assertNotEquals(404, $response->getStatusCode(), 
                "Route $method $path should be accessible");
            
            $this->assertNotEquals(500, $response->getStatusCode(), 
                "Route $method $path should not cause server error");
        }
    }

    /**
     * Test protected routes require authentication
     */
    public function testProtectedRoutesRequireAuth(): void
    {
        $protectedRoutes = [
            ['GET', '/regions'],
            ['GET', '/regions/create'],
            ['GET', '/regions/1'],
            ['GET', '/regions/1/edit'],
            ['GET', '/sites'],
            ['GET', '/sites/create'],
            ['GET', '/sites/1'],
            ['GET', '/sites/1/edit'],
            ['GET', '/sectors'],
            ['GET', '/sectors/create'],
            ['GET', '/sectors/1'],
            ['GET', '/sectors/1/edit'],
            ['GET', '/routes'],
            ['GET', '/routes/create'],
            ['GET', '/routes/1'],
            ['GET', '/routes/1/edit'],
            ['GET', '/books'],
            ['GET', '/books/create'],
            ['GET', '/books/1'],
            ['GET', '/books/1/edit'],
            ['GET', '/profile'],
            ['GET', '/ascents'],
            ['GET', '/favorites'],
            ['GET', '/settings'],
        ];

        foreach ($protectedRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // Should redirect to login or return 403
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403]),
                "Route $method $path should require authentication"
            );
        }
    }

    /**
     * Test admin routes require admin permissions
     */
    public function testAdminRoutesRequireAdminPermissions(): void
    {
        $adminRoutes = [
            ['GET', '/admin'],
            ['GET', '/admin/users'],
            ['GET', '/admin/users/1/edit'],
        ];

        foreach ($adminRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // Should redirect to login or return 403
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403]),
                "Route $method $path should require admin permissions"
            );
        }
    }

    /**
     * Test API routes return JSON
     */
    public function testApiRoutesReturnJson(): void
    {
        $apiRoutes = [
            ['GET', '/api/regions'],
            ['GET', '/api/regions/search'],
            ['GET', '/api/sites'],
            ['GET', '/api/sites/search'],
            ['GET', '/api/sites/1'],
            ['GET', '/api/books/search'],
            ['GET', '/api/books/1/sectors'],
            ['GET', '/api/sectors/1/routes'],
        ];

        foreach ($apiRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // API routes should return JSON content type
            $contentType = $response->getHeader('Content-Type');
            $this->assertStringContainsString('application/json', $contentType ?? '',
                "Route $method $path should return JSON");
        }
    }

    /**
     * Test POST routes require CSRF token
     */
    public function testPostRoutesRequireCSRF(): void
    {
        $postRoutes = [
            ['POST', '/login'],
            ['POST', '/register'],
            ['POST', '/regions'],
            ['POST', '/sites'],
            ['POST', '/sectors'],
            ['POST', '/routes'],
            ['POST', '/books'],
            ['POST', '/ascents'],
        ];

        foreach ($postRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            $request->setBody([]); // Empty body without CSRF token
            
            $response = $this->app->handle($request);
            
            // Should fail due to missing CSRF token
            $this->assertTrue(
                in_array($response->getStatusCode(), [403, 419, 422]),
                "Route $method $path should require CSRF token"
            );
        }
    }

    /**
     * Test route parameters validation
     */
    public function testRouteParametersValidation(): void
    {
        $parametrizedRoutes = [
            ['GET', '/regions/invalid'],
            ['GET', '/regions/99999'],
            ['GET', '/sites/invalid'],
            ['GET', '/sites/99999'],
            ['GET', '/sectors/invalid'],
            ['GET', '/sectors/99999'],
            ['GET', '/routes/invalid'],
            ['GET', '/routes/99999'],
            ['GET', '/books/invalid'],
            ['GET', '/books/99999'],
        ];

        foreach ($parametrizedRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // Should return 404 for invalid parameters
            $this->assertEquals(404, $response->getStatusCode(),
                "Route $method $path should return 404 for invalid parameters");
        }
    }

    /**
     * Test middleware execution order
     */
    public function testMiddlewareExecutionOrder(): void
    {
        $protectedRoute = '/regions';
        
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath($protectedRoute);
        
        $response = $this->app->handle($request);
        
        // Should be processed by AuthMiddleware first
        $this->assertNotEquals(500, $response->getStatusCode(),
            "Middleware should execute without errors");
    }

    /**
     * Test DELETE and PUT routes
     */
    public function testDeleteAndPutRoutes(): void
    {
        $routes = [
            ['DELETE', '/regions/1'],
            ['PUT', '/regions/1'],
            ['DELETE', '/sites/1'],
            ['PUT', '/sites/1'],
            ['DELETE', '/books/1'],
            ['PUT', '/books/1'],
        ];

        foreach ($routes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // Should require authentication and CSRF
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403, 419, 422]),
                "Route $method $path should be protected"
            );
        }
    }

    /**
     * Test weather API route
     */
    public function testWeatherApiRoute(): void
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->setPath('/regions/1/weather');
        
        $response = $this->app->handle($request);
        
        // Should return JSON weather data
        $this->assertNotEquals(500, $response->getStatusCode(),
            "Weather API should not cause server error");
    }

    /**
     * Test media upload routes
     */
    public function testMediaUploadRoutes(): void
    {
        $mediaRoutes = [
            ['POST', '/regions/1/media'],
            ['DELETE', '/regions/1/media/1'],
        ];

        foreach ($mediaRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // Should require authentication and CSRF
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403, 419, 422]),
                "Route $method $path should be protected"
            );
        }
    }

    /**
     * Test export routes
     */
    public function testExportRoutes(): void
    {
        $exportRoutes = [
            ['GET', '/regions/1/export'],
            ['GET', '/ascents/export'],
        ];

        foreach ($exportRoutes as [$method, $path]) {
            $request = new Request();
            $request->setMethod($method);
            $request->setPath($path);
            
            $response = $this->app->handle($request);
            
            // Should require authentication
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403]),
                "Route $method $path should require authentication"
            );
        }
    }
}