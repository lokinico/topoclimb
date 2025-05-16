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



class View
{
    /**
     * @var Environment
     */
    private Environment $twig;

    /**
     * @var array
     */
    private array $globalData = [];

    /**
     * Constructor
     *
     * @param string $viewsPath
     * @param string $cachePath
     */
    public function __construct(string $viewsPath = null, string $cachePath = null)
    {
        $viewsPath = $viewsPath ?? BASE_PATH . '/resources/views';
        $cachePath = $cachePath ?? BASE_PATH . '/cache/views';

        $loader = new FilesystemLoader($viewsPath);
        $debug = env('APP_ENV', 'production') === 'development';

        $this->twig = new Environment($loader, [
            'cache' => $debug ? false : $cachePath,
            'debug' => $debug,
            'auto_reload' => $debug,
        ]);

        // Add debug extension if in development
        if ($debug) {
            $this->twig->addExtension(new DebugExtension());
        }

        // Register global functions and filters
        $this->registerFunctions();
        $this->registerFilters();
    }

    /**
     * Register Twig functions
     *
     * @return void
     */
    private function registerFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('url', function (string $path = '') {
            if (strpos($path, '://') !== false) {
                return $path; // URL absolue
            }
            return url($path);
        }));

        // Component helper - seule déclaration
        $this->twig->addFunction(new TwigFunction('component', function (string $name, array $data = []) {
            // Échapper les données qui ne sont pas marquées comme 'raw'
            foreach ($data as $key => $value) {
                if (is_string($value) && !isset($data[$key . '_raw'])) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
            return $this->renderComponent($name, $data);
        }, ['is_safe' => ['html']]));

        // Add asset() function for loading assets
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            // Retourne directement le chemin à partir de la racine
            return '/' . ltrim($path, '/');
        }));

        // MODIFIÉ: Add auth() function correctement avec auth_user_id
        $this->twig->addFunction(new TwigFunction('auth', function () {
            // Utiliser auth_user_id au lieu de user_id pour être cohérent
            return isset($_SESSION['auth_user_id']) ? true : false;
        }));

        // AJOUTÉ: Add auth_user() function pour récupérer les données de l'utilisateur
        $this->twig->addFunction(new TwigFunction('auth_user', function () {
            if (!isset($_SESSION['auth_user_id'])) {
                return null;
            }

            // Récupérer la base de données
            global $db;

            // Si $db n'est pas disponible, essayer de le récupérer via Container
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

            // Si toujours pas de base de données, retourner simplement un objet minimal
            if (!isset($db) || $db === null) {
                error_log("View::auth_user - Base de données non disponible, retour objet minimal");
                return (object) [
                    'id' => $_SESSION['auth_user_id'],
                    'prenom' => 'Utilisateur',
                    'nom' => 'Connecté'
                ];
            }

            // Récupérer l'utilisateur depuis la base de données
            try {
                $userId = $_SESSION['auth_user_id'];
                $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
                $result = $db->query($query, [$userId])->fetch();

                if (!$result) {
                    error_log("View::auth_user - Utilisateur non trouvé: " . $userId);
                    return null;
                }

                // Retourner l'utilisateur comme objet
                return (object) $result;
            } catch (\Throwable $e) {
                error_log("View::auth_user - Erreur récupération utilisateur: " . $e->getMessage());
                return null;
            }
        }));

        // Add is_active() function for navigation
        $this->twig->addFunction(new TwigFunction('is_active', function (string $path) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            return $currentPath === $path ? 'active' : '';
        }));

        // Add csrf_token() function
        $this->twig->addFunction(new TwigFunction('csrf_token', function () {
            // This assumes you have a Session class that can generate CSRF tokens
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }));

        $this->twig->addFunction(new TwigFunction('flash', function () {
            // Ne pas changer cette logique, mais assurez-vous qu'elle fonctionne
            $messages = $_SESSION['_flashes'] ?? [];

            // IMPORTANT: Ne supprimez pas les messages ici pour permettre leur affichage
            // à divers endroits du template - nous les supprimerons plus tard
            // unset($_SESSION['_flashes']);

            return $messages;
        }));

        // Ajoutez cette nouvelle fonction pour nettoyer les messages après l'affichage
        $this->twig->addFunction(new TwigFunction('clear_flash', function () {
            // N'effacer les messages que lorsqu'on appelle explicitement cette fonction
            unset($_SESSION['_flashes']);
            return true;
        }));

        // Add route() function for generating URLs
        $this->twig->addFunction(new TwigFunction('route', function (string $name, array $params = []) {
            // Assuming you have a Router class that can generate URLs
            return Router::generateUrl($name, $params);
        }));
    }

    /**
     * Register Twig filters
     *
     * @return void
     */
    private function registerFilters(): void
    {
        // Add 'e' filter for HTML escaping (redundant with Twig's escape but for consistency)
        $this->twig->addFilter(new TwigFilter('e', function ($string) {
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }));

        // Add 'format_date' filter
        $this->twig->addFilter(new TwigFilter('format_date', function ($date, $format = 'd/m/Y') {
            if ($date instanceof \DateTime) {
                return $date->format($format);
            }
            return $date ? date($format, strtotime($date)) : '';
        }));

        // Add 'format_difficulty' filter for climbing grades
        $this->twig->addFilter(new TwigFilter('format_difficulty', function ($difficulty) {
            // Simple implementation, could be expanded
            return $difficulty ?: 'Non spécifié';
        }));

        // Add 'format_beauty' filter for star ratings
        $this->twig->addFilter(new TwigFilter('format_beauty', function ($beauty) {
            $beauty = (int) $beauty;
            return str_repeat('★', $beauty) . str_repeat('☆', 5 - $beauty);
        }));

        // Add 'repeat' filter
        $this->twig->addFilter(new TwigFilter('repeat', function ($string, $times) {
            return str_repeat($string, $times);
        }));
    }


    /**
     * Render a view
     *
     * @param string $view
     * @param array $data
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function render(string $view, array $data = []): string
    {
        // Add .twig extension if not present
        if (!str_ends_with($view, '.twig')) {
            $view .= '.twig';
        }

        // Combine global data with local data
        $data = array_merge($this->globalData, $data);

        // Render the template
        return $this->twig->render($view, $data);
    }

    /**
     * Add global data available to all views
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->twig->addGlobal($key, $value);
        $this->globalData[$key] = $value;
    }

    /**
     * Add multiple global variables at once
     *
     * @param array $data
     * @return void
     */
    public function addGlobals(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->addGlobal($key, $value);
        }
    }

    /**
     * Get the Twig environment
     *
     * @return Environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    /**
     * Render a component
     *
     * @param string $name
     * @param array $data
     * @return string
     */
    private function renderComponent(string $name, array $data = []): string
    {
        $componentPath = "components/{$name}.twig";
        return $this->twig->render($componentPath, $data);
    }
}
