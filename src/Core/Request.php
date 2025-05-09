<?php

namespace TopoclimbCH\Core;

class Request
{
    /**
     * Données GET
     *
     * @var array
     */
    private array $get;
    
    /**
     * Données POST
     *
     * @var array
     */
    private array $post;
    
    /**
     * Données FILES
     *
     * @var array
     */
    private array $files;
    
    /**
     * Données COOKIE
     *
     * @var array
     */
    private array $cookies;
    
    /**
     * En-têtes HTTP
     *
     * @var array
     */
    private array $headers;
    
    /**
     * Méthode HTTP
     *
     * @var string
     */
    private string $method;
    
    /**
     * URI de la requête
     *
     * @var string
     */
    private string $uri;
    
    /**
     * Chemin de la requête (sans query string)
     *
     * @var string
     */
    private string $path;
    
    /**
     * Paramètres de l'URL
     *
     * @var array
     */
    private array $params = [];
    
    /**
     * Body de la requête (pour les requêtes JSON)
     *
     * @var array|null
     */
    private ?array $body = null;

    /**
     * Constructeur
     */
    private function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
        $this->headers = $this->getRequestHeaders();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH);
        
        // Gestion du body pour les requêtes JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $this->body = json_decode($json, true) ?? [];
        }
    }

    /**
     * Retourne une instance de la classe Request basée sur les variables globales
     *
     * @return Request
     */
    public static function createFromGlobals(): Request
    {
        return new self();
    }

    /**
     * Récupère les en-têtes HTTP
     *
     * @return array
     */
    private function getRequestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * Retourne la méthode HTTP
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Vérifie si la méthode HTTP est POST
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Vérifie si la méthode HTTP est GET
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Retourne l'URI de la requête
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Retourne le chemin de la requête (sans query string)
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Récupère un paramètre GET
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut si le paramètre n'existe pas
     * @return mixed
     */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres GET
     *
     * @return array
     */
    public function getAllQuery(): array
    {
        return $this->get;
    }

    /**
     * Récupère un paramètre POST
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut si le paramètre n'existe pas
     * @return mixed
     */
    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres POST
     *
     * @return array
     */
    public function getAllPost(): array
    {
        return $this->post;
    }

    /**
     * Récupère un paramètre du body (pour les requêtes JSON)
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut si le paramètre n'existe pas
     * @return mixed
     */
    public function getBody(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres du body (pour les requêtes JSON)
     *
     * @return array|null
     */
    public function getAllBody(): ?array
    {
        return $this->body;
    }

    /**
     * Récupère un fichier uploadé
     *
     * @param string $key Clé du fichier
     * @return array|null
     */
    public function getFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Récupère tous les fichiers uploadés
     *
     * @return array
     */
    public function getAllFiles(): array
    {
        return $this->files;
    }

    /**
     * Récupère un cookie
     *
     * @param string $key Clé du cookie
     * @param mixed $default Valeur par défaut si le cookie n'existe pas
     * @return mixed
     */
    public function getCookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Récupère tous les cookies
     *
     * @return array
     */
    public function getAllCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Récupère un en-tête HTTP
     *
     * @param string $key Clé de l'en-tête
     * @param mixed $default Valeur par défaut si l'en-tête n'existe pas
     * @return mixed
     */
    public function getHeader(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Récupère tous les en-têtes HTTP
     *
     * @return array
     */
    public function getAllHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Définit un paramètre d'URL
     *
     * @param string $key Clé du paramètre
     * @param mixed $value Valeur du paramètre
     * @return void
     */
    public function setParam(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * Récupère un paramètre d'URL
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut si le paramètre n'existe pas
     * @return mixed
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres d'URL
     *
     * @return array
     */
    public function getAllParams(): array
    {
        return $this->params;
    }

    /**
     * Vérifie si une requête est une requête AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }
}