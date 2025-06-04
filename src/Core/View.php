<?php
// src/Core/View.php

namespace TopoclimbCH\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;
use Twig\Extension\DebugExtension;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Security\CsrfManager;

class View
{
    private Environment $twig;
    private array $globalData = [];
    private CsrfManager $csrfManager;

    public function __construct(string $viewsPath = null, string $cachePath = null, CsrfManager $csrfManager = null)
    {
        $viewsPath = $viewsPath ?? BASE_PATH . '/resources/views';
        $cachePath = $cachePath ?? BASE_PATH . '/cache/views';

        // Pour la compatibilité descendante, CsrfManager peut être null
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

        $this->registerFunctions();
        $this->registerFilters();
    }

    /**
     * Setter pour CsrfManager (utile si injecté après construction)
     */
    public function setCsrfManager(CsrfManager $csrfManager): void
    {
        $this->csrfManager = $csrfManager;
    }

    private function registerFunctions(): void
    {
        // URL helper
        $this->twig->addFunction(new TwigFunction('url', function (string $path = '') {
            if (strpos($path, '://') !== false) {
                return $path;
            }
            return url($path);
        }));

        // Component helper
        $this->twig->addFunction(new TwigFunction('component', function (string $name, array $data = []) {
            foreach ($data as $key => $value) {
                if (is_string($value) && !isset($data[$key . '_raw'])) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
            return $this->renderComponent($name, $data);
        }, ['is_safe' => ['html']]));

        // Asset helper
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // Auth helpers
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

        // Navigation helper
        $this->twig->addFunction(new TwigFunction('is_active', function (string $path) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            return $currentPath === $path ? 'active' : '';
        }));

        // ========== FONCTIONS CSRF CENTRALISÉES ==========

        // Token CSRF principal - utilise CsrfManager si disponible
        $this->twig->addFunction(new TwigFunction('csrf_token', function () {
            if ($this->csrfManager) {
                return $this->csrfManager->getToken();
            }

            // Fallback pour compatibilité
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }));

        // Champ caché CSRF complet
        $this->twig->addFunction(new TwigFunction('csrf_field', function () {
            if ($this->csrfManager) {
                return $this->csrfManager->getHiddenField();
            }

            // Fallback
            $token = $this->csrf_token();
            return sprintf(
                '<input type="hidden" name="csrf_token" value="%s">',
                htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            );
        }, ['is_safe' => ['html']]));

        // Meta tag pour AJAX
        $this->twig->addFunction(new TwigFunction('csrf_meta', function () {
            if ($this->csrfManager) {
                return $this->csrfManager->getMetaTag();
            }

            // Fallback
            $token = $this->csrf_token();
            return sprintf(
                '<meta name="csrf-token" content="%s">',
                htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            );
        }, ['is_safe' => ['html']]));

        // ========== FONCTIONS FLASH ==========

        $this->twig->addFunction(new TwigFunction('flash', function () {
            $messages = $_SESSION['_flashes'] ?? [];
            return $messages;
        }));

        $this->twig->addFunction(new TwigFunction('clear_flash', function () {
            unset($_SESSION['_flashes']);
            return true;
        }));

        // Route helper (placeholder - nécessite implémentation Router)
        $this->twig->addFunction(new TwigFunction('route', function (string $name, array $params = []) {
            // return Router::generateUrl($name, $params);
            return '#'; // Placeholder
        }));
    }

    private function registerFilters(): void
    {
        // Filtre d'échappement HTML
        $this->twig->addFilter(new TwigFilter('e', function ($string) {
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }));

        // Formatage de date
        $this->twig->addFilter(new TwigFilter('format_date', function ($date, $format = 'd/m/Y') {
            if ($date instanceof \DateTime) {
                return $date->format($format);
            }
            return $date ? date($format, strtotime($date)) : '';
        }));

        // Formatage difficulté escalade
        $this->twig->addFilter(new TwigFilter('format_difficulty', function ($difficulty) {
            return $difficulty ?: 'Non spécifié';
        }));

        // Formatage beauté (étoiles)
        $this->twig->addFilter(new TwigFilter('format_beauty', function ($beauty) {
            $beauty = (int) $beauty;
            return str_repeat('★', $beauty) . str_repeat('☆', 5 - $beauty);
        }));

        // Répétition de chaîne
        $this->twig->addFilter(new TwigFilter('repeat', function ($string, $times) {
            return str_repeat($string, $times);
        }));
    }

    public function render(string $view, array $data = []): string
    {
        if (!str_ends_with($view, '.twig')) {
            $view .= '.twig';
        }

        $data = array_merge($this->globalData, $data);

        return $this->twig->render($view, $data);
    }

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
}
