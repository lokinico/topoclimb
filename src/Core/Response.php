<?php

namespace TopoclimbCH\Core;

class Response
{
    /**
     * Code de statut HTTP
     *
     * @var int
     */
    private int $statusCode = 200;
    
    /**
     * En-têtes HTTP
     *
     * @var array
     */
    private array $headers = [];
    
    /**
     * Contenu de la réponse
     *
     * @var string
     */
    private string $content = '';
    
    /**
     * Cookies à définir
     *
     * @var array
     */
    private array $cookies = [];

    /**
     * Définit le code de statut HTTP
     *
     * @param int $statusCode
     * @return Response
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Récupère le code de statut HTTP
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
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
        $this->headers[$name] = $value;
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
            $this->setHeader($name, $value);
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
        return $this->headers[$name] ?? $default;
    }

    /**
     * Récupère tous les en-têtes HTTP
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
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
        $this->cookies[$name] = [
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly
        ];
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
        $this->content = $content;
        return $this;
    }

    /**
     * Récupère le contenu de la réponse
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Envoie la réponse au client
     *
     * @return void
     */
    public function send(): void
    {
        // Envoi du code de statut
        http_response_code($this->statusCode);
        
        // Envoi des en-têtes
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        
        // Envoi des cookies
        foreach ($this->cookies as $name => $params) {
            setcookie(
                $name,
                $params['value'],
                $params['expire'],
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httpOnly']
            );
        }
        
        // Envoi du contenu
        echo $this->content;
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
        $response = new self();
        $response->setHeader('Content-Type', 'application/json');
        $response->setStatusCode($statusCode);
        $response->setContent(json_encode($data));
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
        $response = new self();
        $response->setHeader('Location', $url);
        $response->setStatusCode($statusCode);
        return $response;
    }
}