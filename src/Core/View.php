<?php

namespace TopoclimbCH\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;
use Twig\Extension\DebugExtension;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\TwigHelpers;

class View
{
    private Environment $twig;
    private array $globalData = [];
    private ?CsrfManager $csrfManager = null;

    /**
     * GARDE LE CONSTRUCTEUR ORIGINAL avec améliorations optionnelles
     */
    public function __construct(?string $viewsPath = null, ?string $cachePath = null, ?CsrfManager $csrfManager = null)
    {
        $viewsPath = $viewsPath ?? BASE_PATH . '/resources/views';
        $cachePath = $cachePath ?? BASE_PATH . '/cache/views';

        $this->csrfManager = $csrfManager;

        $loader = new FilesystemLoader($viewsPath);
        $debug = env('APP_ENV', 'production') === 'development';

        $this->twig = new Environment($loader, [
            'cache' => $debug ? false : $cachePath,
            'debug' => $debug,
            'auto_reload' => $debug,
        ]);

        if ($debug) {
            $this->twig->addExtension(new DebugExtension());
        }

        // Ajouter l'extension TwigHelpers avec les fonctions de formatage
        $this->twig->addExtension(new TwigHelpers());

        $this->registerFunctions();
        $this->registerFilters();
        // Ne plus ajouter de globaux ici - déplacé vers render()
    }

    /**
     * NOUVEAU - Récupère les variables globales par défaut (appelé au moment du rendu)
     */
    private function getGlobalDefaults(): array
    {
        try {
            return [
                // Variables d'environnement de base
                'app_name' => env('APP_NAME', 'TopoclimbCH'),
                'app_env' => env('APP_ENV', 'development'),
                'app_url' => env('APP_URL', 'http://localhost'),
                'app_locale' => 'fr',
                'now' => new \DateTime(),

                // Messages flash si session disponible
                'flash_messages' => $this->getFlashMessages(),

                // Infos auth si disponibles
                'auth_user' => $this->getAuthUser(),
                'csrf_token' => $this->generateCsrfToken(),
            ];
        } catch (\Exception $e) {
            error_log("View: Error getting global defaults: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NOUVEAU - Récupère les messages flash de façon sécurisée
     */
    private function getFlashMessages(): array
    {
        try {
            return $_SESSION['_flashes'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * NOUVEAU - Récupère l'utilisateur authentifié de façon sécurisée
     */
    private function getAuthUser(): ?object
    {
        try {
            if (!isset($_SESSION['auth_user_id'])) {
                return null;
            }

            // Utiliser la fonction auth_user existante si disponible
            $authUserFunction = $this->twig->getFunction('auth_user');
            if ($authUserFunction) {
                return $authUserFunction->getCallable()();
            }

            return (object) [
                'id' => $_SESSION['auth_user_id'],
                'prenom' => 'Utilisateur',
                'nom' => 'Connecté'
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * NOUVEAU - Génère token CSRF de façon sécurisée
     */
    private function generateCsrfToken(): string
    {
        try {
            if ($this->csrfManager) {
                return $this->csrfManager->getToken();
            }

            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Setter pour CsrfManager - GARDE L'API ORIGINALE
     */
    public function setCsrfManager(CsrfManager $csrfManager): void
    {
        $this->csrfManager = $csrfManager;
    }

    /**
     * GARDE TOUTES LES FONCTIONS EXISTANTES + quelques améliorations
     */
    private function registerFunctions(): void
    {
        // ===== FONCTIONS ORIGINALES =====

        $this->twig->addFunction(new TwigFunction('url', function (string $path = '') {
            if (strpos($path, '://') !== false) {
                return $path;
            }
            return url($path);
        }));

        $this->twig->addFunction(new TwigFunction('component', function (string $name, array $data = []) {
            foreach ($data as $key => $value) {
                if (is_string($value) && !isset($data[$key . '_raw'])) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
            return $this->renderComponent($name, $data);
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        $this->twig->addFunction(new TwigFunction('auth', function () {
            return isset($_SESSION['auth_user_id']) ? true : false;
        }));

        $this->twig->addFunction(new TwigFunction('auth_user', function () {
            if (!isset($_SESSION['auth_user_id'])) {
                return null;
            }

            global $db;

            if (!isset($db) || $db === null) {
                try {
                    $container = Container::getInstance();
                    if ($container && $container->has(Database::class)) {
                        $db = $container->get(Database::class);
                    }
                } catch (\Throwable $e) {
                    error_log("View::auth_user - Erreur récupération DB: " . $e->getMessage());
                    return null;
                }
            }

            if (!isset($db) || $db === null) {
                error_log("View::auth_user - Base de données non disponible, retour objet minimal");
                return (object) [
                    'id' => $_SESSION['auth_user_id'],
                    'prenom' => 'Utilisateur',
                    'nom' => 'Connecté'
                ];
            }

            try {
                $userId = $_SESSION['auth_user_id'];
                $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
                $result = $db->query($query, [$userId])->fetch();

                if (!$result) {
                    error_log("View::auth_user - Utilisateur non trouvé: " . $userId);
                    return null;
                }

                return (object) $result;
            } catch (\Throwable $e) {
                error_log("View::auth_user - Erreur récupération utilisateur: " . $e->getMessage());
                return null;
            }
        }));

        $this->twig->addFunction(new TwigFunction('is_active', function (string $path) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            return $currentPath === $path ? 'active' : '';
        }));

        // ===== FONCTIONS CSRF ORIGINALES =====

        $this->twig->addFunction(new TwigFunction('csrf_token', function () {
            if ($this->csrfManager) {
                return $this->csrfManager->getToken();
            }

            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }));

        $this->twig->addFunction(new TwigFunction('csrf_field', function () {
            if ($this->csrfManager) {
                return $this->csrfManager->getHiddenField();
            }

            $token = $this->csrf_token();
            return sprintf(
                '<input type="hidden" name="csrf_token" value="%s">',
                htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            );
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('csrf_meta', function () {
            if ($this->csrfManager) {
                return $this->csrfManager->getMetaTag();
            }

            $token = $this->csrf_token();
            return sprintf(
                '<meta name="csrf-token" content="%s">',
                htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            );
        }, ['is_safe' => ['html']]));

        // ===== NOUVELLES FONCTIONS POUR URLS ET PARAMÈTRES =====
        $this->twig->addFunction(new TwigFunction('query_string', function (array $params = []) {
            $filteredParams = [];

            // Filtrer les paramètres vides et null
            foreach ($params as $key => $value) {
                if ($value !== null && $value !== '') {
                    $filteredParams[$key] = $value;
                }
            }

            return http_build_query($filteredParams);
        }));

        // ===== FONCTIONS FLASH ORIGINALES =====

        $this->twig->addFunction(new TwigFunction('flash', function () {
            $messages = $_SESSION['_flashes'] ?? [];
            return $messages;
        }));

        $this->twig->addFunction(new TwigFunction('clear_flash', function () {
            unset($_SESSION['_flashes']);
            return true;
        }));

        $this->twig->addFunction(new TwigFunction('route', function (string $name, array $params = []) {
            return '#'; // Placeholder
        }));

        // ===== NOUVELLES FONCTIONS POUR RÉGIONS =====

        $this->twig->addFunction(new TwigFunction('breadcrumb', function (array $items) {
            $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
            foreach ($items as $item) {
                if (isset($item['url']) && $item['url']) {
                    $html .= sprintf(
                        '<li class="breadcrumb-item"><a href="%s">%s</a></li>',
                        htmlspecialchars($item['url']),
                        htmlspecialchars($item['title'])
                    );
                } else {
                    $html .= sprintf(
                        '<li class="breadcrumb-item active">%s</li>',
                        htmlspecialchars($item['title'])
                    );
                }
            }
            $html .= '</ol></nav>';
            return $html;
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('format_coordinates', function ($lat, $lng) {
            if (!$lat || !$lng) return '';
            return sprintf('%.6f, %.6f', $lat, $lng);
        }));

        // Configuration function for accessing app settings
        $this->twig->addFunction(new TwigFunction('config', function (string $key, $default = null) {
            // Try to get from environment variables first
            $envKey = strtoupper(str_replace('.', '_', $key));
            if (isset($_ENV[$envKey])) {
                return $_ENV[$envKey];
            }
            
            // Try to get from database settings if available
            try {
                $db = \TopoclimbCH\Core\Database::getInstance();
                $settings = $db->fetchOne("SELECT {$key} FROM app_settings WHERE id = 1");
                if ($settings && isset($settings[$key]) && !empty($settings[$key])) {
                    return $settings[$key];
                }
            } catch (\Exception $e) {
                // Table might not exist, continue to default
            }
            
            return $default;
        }));
    }

    /**
     * GARDE TOUS LES FILTRES EXISTANTS + nouveaux
     */
    private function registerFilters(): void
    {
        // ===== FILTRES ORIGINAUX =====

        $this->twig->addFilter(new TwigFilter('e', function ($string) {
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }));

        $this->twig->addFilter(new TwigFilter('format_date', function ($date, $format = 'd/m/Y') {
            if ($date instanceof \DateTime) {
                return $date->format($format);
            }
            return $date ? date($format, strtotime($date)) : '';
        }));

        $this->twig->addFilter(new TwigFilter('format_difficulty', function ($difficulty) {
            return $difficulty ?: 'Non spécifié';
        }));

        $this->twig->addFilter(new TwigFilter('format_beauty', function ($beauty) {
            $beauty = (int) $beauty;
            return str_repeat('★', $beauty) . str_repeat('☆', 5 - $beauty);
        }));

        $this->twig->addFilter(new TwigFilter('repeat', function ($string, $times) {
            return str_repeat($string, $times);
        }));

        // ===== NOUVEAUX FILTRES POUR RÉGIONS =====

        $this->twig->addFilter(new TwigFilter('format_distance', function ($meters) {
            if (!$meters) return '';
            if ($meters < 1000) {
                return $meters . ' m';
            }
            return round($meters / 1000, 1) . ' km';
        }));

        $this->twig->addFilter(new TwigFilter('format_duration', function ($minutes) {
            if (!$minutes) return '';
            if ($minutes < 60) {
                return $minutes . ' min';
            }
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');
        }));
    }

    /**
     * GARDE LA MÉTHODE RENDER ORIGINALE avec ajout des globaux dynamiques
     */
    public function render(string $view, array $data = []): string
    {
        if (!str_ends_with($view, '.twig')) {
            $view .= '.twig';
        }

        // Ajouter les données globales dynamiques au moment du rendu
        $globalDefaults = $this->getGlobalDefaults();
        $data = array_merge($this->globalData, $globalDefaults, $data);

        return $this->twig->render($view, $data);
    }

    /**
     * GARDE LES MÉTHODES ORIGINALES
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->twig->addGlobal($key, $value);
        $this->globalData[$key] = $value;
    }

    public function addGlobals(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->addGlobal($key, $value);
        }
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }

    private function renderComponent(string $name, array $data = []): string
    {
        $componentPath = "components/{$name}.twig";
        return $this->twig->render($componentPath, $data);
    }

    // ===== NOUVELLES MÉTHODES UTILITAIRES =====

    /**
     * Render une page d'erreur
     */
    public function renderError(int $statusCode, string $message = ''): string
    {
        $errorTemplates = [
            403 => 'errors/403.twig',
            404 => 'errors/404.twig',
            500 => 'errors/500.twig'
        ];

        $template = $errorTemplates[$statusCode] ?? 'errors/500.twig';

        $data = [
            'status_code' => $statusCode,
            'message' => $message,
            'title' => "Erreur $statusCode"
        ];

        try {
            return $this->render($template, $data);
        } catch (\Exception $e) {
            // Fallback basique si template d'erreur non trouvé
            return "<!DOCTYPE html><html><head><title>Erreur $statusCode</title></head><body><h1>Erreur $statusCode</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
        }
    }

    /**
     * Vérifie si un template existe
     */
    public function exists(string $template): bool
    {
        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }
        return $this->twig->getLoader()->exists($template);
    }

    /**
     * Render JSON pour AJAX
     */
    public function json(array $data, ?string $template = null): string
    {
        if ($template && $this->exists($template)) {
            $data['html'] = $this->render($template, $data);
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
