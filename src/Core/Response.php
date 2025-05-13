<?php

namespace TopoclimbCH\Core;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Cookie;

class Response extends SymfonyResponse
{
    /**
     * Constructeur
     *
     * @param string $content Contenu de la réponse
     * @param int $status Code de statut HTTP
     * @param array $headers En-têtes HTTP
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content, $status, $headers);
    }

    /**
     * Définit le code de statut HTTP
     *
     * @param int $statusCode
     * @return Response
     */
    public function setStatusCode(int $statusCode): self
    {
        parent::setStatusCode($statusCode);
        return $this;
    }

    /**
     * Récupère le code de statut HTTP
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return parent::getStatusCode();
    }

    /**
     * Définit un en-tête HTTP
     *
     * @param string $name Nom de l'en-tête
     * @param string $value Valeur de l'en-tête
     * @return Response
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers->set($name, $value);
        return $this;
    }

    /**
     * Définit plusieurs en-têtes HTTP
     *
     * @param array $headers Tableau d'en-têtes [nom => valeur]
     * @return Response
     */
    public function setHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }
        return $this;
    }

    /**
     * Récupère un en-tête HTTP
     *
     * @param string $name Nom de l'en-tête
     * @param string|null $default Valeur par défaut si l'en-tête n'existe pas
     * @return string|null
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers->get($name, $default);
    }

    /**
     * Récupère tous les en-têtes HTTP
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers->all();
    }

    /**
     * Définit un cookie
     *
     * @param string $name Nom du cookie
     * @param string $value Valeur du cookie
     * @param int $expire Timestamp d'expiration
     * @param string $path Chemin du cookie
     * @param string $domain Domaine du cookie
     * @param bool $secure Cookie sécurisé (HTTPS)
     * @param bool $httpOnly Cookie accessible uniquement par HTTP
     * @return Response
     */
    public function setCookie(string $name, string $value, int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true): self
    {
        $cookie = new Cookie(
            $name,
            $value,
            $expire !== 0 ? $expire : 0,
            $path,
            $domain,
            $secure,
            $httpOnly
        );
        
        $this->headers->setCookie($cookie);
        return $this;
    }

    /**
     * Définit le contenu de la réponse
     *
     * @param string $content
     * @return Response
     */
    public function setContent(string $content): self
    {
        parent::setContent($content);
        return $this;
    }

    /**
     * Récupère le contenu de la réponse
     *
     * @return string
     */
    public function getContent(): string
    {
        return parent::getContent();
    }

    /**
     * Envoie la réponse au client
     *
     * @return void
     */
    public function send(): void
    {
        parent::send();
    }

    /**
     * Crée une réponse JSON
     *
     * @param mixed $data Données à encoder en JSON
     * @param int $statusCode Code de statut HTTP
     * @return Response
     */
    public static function json(mixed $data, int $statusCode = 200): self
    {
        $content = json_encode($data);
        
        $response = new self($content, $statusCode);
        $response->headers->set('Content-Type', 'application/json');
        
        return $response;
    }

    /**
     * Crée une réponse de redirection
     *
     * @param string $url URL de redirection
     * @param int $statusCode Code de statut HTTP
     * @return Response
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        // Normalisation de l'URL pour les chemins relatifs
        if (!preg_match('#^https?://#i', $url) && $url[0] !== '/') {
            // Si l'URL ne commence pas par http:// ou https:// et ne commence pas par /, ajouter /
            $url = '/' . $url;
        }
        
        $response = new self('', $statusCode);
        $response->headers->set('Location', $url);
        
        return $response;
    }
    
    /**
     * Définit cette réponse comme publique
     * 
     * @return Response
     */
    public function setPublic(): self
    {
        parent::setPublic();
        return $this;
    }
    
    /**
     * Définit cette réponse comme privée
     * 
     * @return Response
     */
    public function setPrivate(): self
    {
        parent::setPrivate();
        return $this;
    }
    
    /**
     * Définit le temps maximal de mise en cache
     * 
     * @param int $seconds
     * @return Response
     */
    public function setMaxAge(int $seconds): self
    {
        parent::setMaxAge($seconds);
        return $this;
    }
    
    /**
     * Définit le temps partagé maximal de mise en cache
     * 
     * @param int $seconds
     * @return Response
     */
    public function setSharedMaxAge(int $seconds): self
    {
        parent::setSharedMaxAge($seconds);
        return $this;
    }
}