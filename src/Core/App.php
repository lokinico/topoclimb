<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Exceptions\RouteNotFoundException;

class App
{
    /**
     * Router
     *
     * @var Router
     */
    private Router $router;
    
    /**
     * Requête HTTP
     *
     * @var Request
     */
    private Request $request;
    
    /**
     * Session
     *
     * @var Session
     */
    private Session $session;
    
    /**
     * Database
     *
     * @var Database
     */
    private Database $database;
    
    /**
     * Environnement d'exécution (development, production, testing)
     *
     * @var string
     */
    private string $environment;
    
    /**
     * Chemin de base de l'application
     *
     * @var string
     */
    private string $basePath;

    /**
     * Constructeur
     *
     * @param string $basePath Chemin de base de l'application
     * @param string $environment Environnement d'exécution
     */
    public function __construct(string $basePath, string $environment = 'production')
    {
        $this->basePath = $basePath;
        $this->environment = $environment;
        $this->router = new Router();
        $this->request = Request::createFromGlobals();
        $this->session = new Session();
        $this->database = Database::getInstance();
    }

    /**
     * Récupère le router
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Récupère la requête HTTP
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Récupère la session
     *
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Récupère la base de données
     *
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Récupère l'environnement d'exécution
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Récupère le chemin de base de l'application
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Vérifie si l'application est en environnement de développement
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    /**
     * Vérifie si l'application est en environnement de production
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Initialise l'application
     *
     * @return App
     */
    public function bootstrap(): self
    {
        // Charge les routes
        $routesFile = $this->basePath . '/config/routes.php';
        if (file_exists($routesFile)) {
            $this->router->loadRoutes($routesFile);
        }
        
        return $this;
    }

    /**
     * Gère la requête et retourne une réponse
     *
     * @return Response
     */
    public function handle(): Response
    {
        try {
            return $this->router->dispatch($this->request);
        } catch (RouteNotFoundException $e) {
            return $this->handleNotFound();
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Exécute l'application et envoie la réponse
     *
     * @return void
     */
    public function run(): void
    {
        $response = $this->handle();
        $response->send();
    }

    /**
     * Gère les routes introuvables (404)
     *
     * @return Response
     */
    private function handleNotFound(): Response
    {
        $response = new Response();
        $response->setStatusCode(404);
        
        // Contenu de la page 404
        $errorViewPath = $this->basePath . '/resources/views/errors/404.php';
        if (file_exists($errorViewPath)) {
            ob_start();
            include $errorViewPath;
            $content = ob_get_clean();
            $response->setContent($content);
        } else {
            $response->setContent('<h1>404 - Page non trouvée</h1>');
        }
        
        return $response;
    }

/**
     * Gère les erreurs (500)
     *
     * @param \Exception $exception
     * @return Response
     */
    private function handleError(\Exception $exception): Response
    {
        $response = new Response();
        $response->setStatusCode(500);
        
        if ($this->isDevelopment()) {
            // Affiche les détails de l'erreur en développement
            $content = '<h1>Erreur serveur</h1>';
            $content .= '<p><strong>Message:</strong> ' . $exception->getMessage() . '</p>';
            $content .= '<p><strong>Fichier:</strong> ' . $exception->getFile() . ' (ligne ' . $exception->getLine() . ')</p>';
            $content .= '<h2>Trace:</h2>';
            $content .= '<pre>' . $exception->getTraceAsString() . '</pre>';
            
            $response->setContent($content);
        } else {
            // Affiche une page d'erreur générique en production
            $errorViewPath = $this->basePath . '/resources/views/errors/500.php';
            if (file_exists($errorViewPath)) {
                ob_start();
                include $errorViewPath;
                $content = ob_get_clean();
                $response->setContent($content);
            } else {
                $response->setContent('<h1>500 - Erreur serveur interne</h1>');
            }
        }
        
        return $response;
    }
    
    /**
     * Raccourci pour créer une instance d'application et l'exécuter
     *
     * @param string $basePath
     * @param string $environment
     * @return void
     */
    public static function start(string $basePath, string $environment = 'production'): void
    {
        $app = new self($basePath, $environment);
        $app->bootstrap()->run();
    }
}