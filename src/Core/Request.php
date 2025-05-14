<?php

namespace TopoclimbCH\Core;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest
{
    /**
     * Paramètres d'URL (pour compatibilité)
     *
     * @var array
     */
    private array $routeParams = [];

    /**
     * Retourne une instance de la classe Request basée sur les variables globales
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        return parent::createFromGlobals();
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
        return $this->query->get($key, $default);
    }

    /**
     * Récupère tous les paramètres GET
     *
     * @return array
     */
    public function getAllQuery(): array
    {
        return $this->query->all();
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
        return $this->request->get($key, $default);
    }

    /**
     * Récupère tous les paramètres POST
     *
     * @return array
     */
    public function getAllPost(): array
    {
        return $this->request->all();
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
        if ($this->getContentType() === 'json') {
            $data = json_decode($this->getContent(), true);
            return $data[$key] ?? $default;
        }
        return $default;
    }

    /**
     * Récupère tous les paramètres du body (pour les requêtes JSON)
     *
     * @return array|null
     */
    public function getAllBody(): ?array
    {
        if ($this->getContentType() === 'json') {
            return json_decode($this->getContent(), true);
        }
        return null;
    }

    /**
     * Récupère un fichier uploadé
     *
     * @param string $key Clé du fichier
     * @return array|null
     */
    public function getFile(string $key): ?array
    {
        $file = $this->files->get($key);
        if (!$file) {
            return null;
        }
        
        // Convertir l'objet UploadedFile en tableau pour compatibilité
        return [
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'tmp_name' => $file->getPathname(),
            'error' => $file->getError(),
            'size' => $file->getSize()
        ];
    }

    /**
     * Récupère tous les fichiers uploadés
     *
     * @return array
     */
    public function getAllFiles(): array
    {
        $files = [];
        foreach ($this->files->all() as $key => $file) {
            $files[$key] = $this->getFile($key);
        }
        return $files;
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
        return $this->cookies->get($key, $default);
    }

    /**
     * Récupère tous les cookies
     *
     * @return array
     */
    public function getAllCookies(): array
    {
        return $this->cookies->all();
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
        return $this->headers->get($key, $default);
    }

    /**
     * Récupère tous les en-têtes HTTP
     *
     * @return array
     */
    public function getAllHeaders(): array
    {
        return $this->headers->all();
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
        $this->routeParams[$key] = $value;
        $this->attributes->set($key, $value);
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
        return $this->attributes->get($key, $this->routeParams[$key] ?? $default);
    }

    /**
     * Récupère tous les paramètres d'URL
     *
     * @return array
     */
    public function getAllParams(): array
    {
        return array_merge($this->attributes->all(), $this->routeParams);
    }

    /**
     * Vérifie si une requête est une requête AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Vérifie si la méthode HTTP est GET
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Vérifie si la méthode HTTP est POST
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Récupère le chemin de la requête (sans query string)
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->getPathInfo();
    }

    /**
     * Helper pour détecter le type de contenu
     * 
     * @return string|null
     */
    private function getContentType(): ?string
    {
        $contentType = $this->headers->get('Content-Type');
        if (strpos($contentType, 'application/json') !== false) {
            return 'json';
        }
        return null;
    }
}