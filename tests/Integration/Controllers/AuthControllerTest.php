<?php

namespace TopoclimbCH\Tests\Integration\Controllers;

use TopoclimbCH\Tests\TestCase;
use TopoclimbCH\Controllers\AuthController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;

class AuthControllerTest extends TestCase
{
    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(AuthController::class);
    }

    public function testLoginFormLoads(): void
    {
        $request = new Request();
        $response = $this->controller->loginForm($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Connexion', $response->getContent());
        $this->assertStringContainsString('form', $response->getContent());
        $this->assertStringContainsString('email', $response->getContent());
        $this->assertStringContainsString('password', $response->getContent());
    }

    public function testRegisterFormLoads(): void
    {
        $request = new Request();
        $response = $this->controller->registerForm($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Inscription', $response->getContent());
        $this->assertStringContainsString('form', $response->getContent());
        $this->assertStringContainsString('username', $response->getContent());
        $this->assertStringContainsString('email', $response->getContent());
        $this->assertStringContainsString('password', $response->getContent());
    }

    public function testForgotPasswordFormLoads(): void
    {
        $request = new Request();
        $response = $this->controller->forgotPasswordForm($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Mot de passe oublié', $response->getContent());
        $this->assertStringContainsString('form', $response->getContent());
        $this->assertStringContainsString('email', $response->getContent());
    }

    public function testResetPasswordFormLoads(): void
    {
        $request = new Request();
        $request->setQueryParam('token', 'test-token');
        $response = $this->controller->resetPasswordForm($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Réinitialiser', $response->getContent());
        $this->assertStringContainsString('form', $response->getContent());
        $this->assertStringContainsString('password', $response->getContent());
    }

    public function testLoginFormContainsCSRFToken(): void
    {
        $request = new Request();
        $response = $this->controller->loginForm($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testRegisterFormContainsCSRFToken(): void
    {
        $request = new Request();
        $response = $this->controller->registerForm($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('csrf_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function testLoginFormContainsValidationErrors(): void
    {
        $request = new Request();
        $request->setSession(['errors' => ['email' => 'Email invalide']]);
        $response = $this->controller->loginForm($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Email invalide', $content);
    }

    public function testRegisterFormContainsValidationErrors(): void
    {
        $request = new Request();
        $request->setSession(['errors' => ['username' => 'Nom d\'utilisateur requis']]);
        $response = $this->controller->registerForm($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('Nom d\'utilisateur requis', $content);
    }

    public function testLoginFormContainsRememberMeOption(): void
    {
        $request = new Request();
        $response = $this->controller->loginForm($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('remember_me', $content);
        $this->assertStringContainsString('Se souvenir de moi', $content);
    }

    public function testRegisterFormContainsTermsCheckbox(): void
    {
        $request = new Request();
        $response = $this->controller->registerForm($request);
        
        $content = $response->getContent();
        $this->assertStringContainsString('terms', $content);
        $this->assertStringContainsString('conditions', $content);
    }
}