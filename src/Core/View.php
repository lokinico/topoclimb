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
    private ?CsrfManager $csrfManager = null;

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
        // ========== FONCTIONS ESSENTIELLES ==========

        // Fonction env() - PRIORITÉ ABSOLUE
        $this->twig->addFunction(new TwigFunction('env', function (string $key, mixed $default = null) {
            return env($key, $default);
        }));

        // URL helper
        $this->twig->addFunction(new TwigFunction('url', function (string $path = '') {
            return url($path);
        }));

        // Asset helper avec cache busting
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return asset($path);
        }));

        // ========== FONCTIONS D'AUTHENTIFICATION ==========

        // Auth helper principal
        $this->twig->addFunction(new TwigFunction('auth', function () {
            return auth();
        }));

        // Utilisateur authentifié
        $this->twig->addFunction(new TwigFunction('auth_user', function () {
            return auth_user();
        }));

        // Vérification d'authentification
        $this->twig->addFunction(new TwigFunction('auth_check', function () {
            return auth_check();
        }));

        // Vérification de permission
        $this->twig->addFunction(new TwigFunction('can', function (string $permission) {
            return can($permission);
        }));

        // ========== FONCTIONS DE NAVIGATION ==========

        // Navigation active
        $this->twig->addFunction(new TwigFunction('is_active', function (string $path) {
            return is_active($path);
        }));

        // Suppression de filtres dans l'URL
        $this->twig->addFunction(new TwigFunction('remove_filter_url', function (string $param) {
            return remove_filter_url($param);
        }));

        // URL courante avec paramètres
        $this->twig->addFunction(new TwigFunction('current_url_with', function (array $params) {
            return current_url_with($params);
        }));

        // ========== FONCTIONS CSRF ==========

        // Token CSRF principal
        $this->twig->addFunction(new TwigFunction('csrf_token', function () {
            return csrf_token();
        }));

        // Champ caché CSRF complet
        $this->twig->addFunction(new TwigFunction('csrf_field', function () {
            return csrf_field();
        }, ['is_safe' => ['html']]));

        // Meta tag pour AJAX
        $this->twig->addFunction(new TwigFunction('csrf_meta', function () {
            $token = csrf_token();
            return sprintf(
                '<meta name="csrf-token" content="%s">',
                htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            );
        }, ['is_safe' => ['html']]));

        // ========== FONCTIONS DE FORMATAGE ==========

        // Date en français
        $this->twig->addFunction(new TwigFunction('date_fr', function (string $date, string $format = 'd/m/Y') {
            return date_fr($date, $format);
        }));

        // Nombre formaté
        $this->twig->addFunction(new TwigFunction('number_format_fr', function (float $number, int $decimals = 0) {
            return number_format_fr($number, $decimals);
        }));

        // Truncate text
        $this->twig->addFunction(new TwigFunction('truncate', function (string $text, int $length = 100, string $suffix = '...') {
            return truncate($text, $length, $suffix);
        }));

        // Slug génération
        $this->twig->addFunction(new TwigFunction('slug', function (string $text) {
            return slug($text);
        }));

        // ========== FONCTIONS UTILITAIRES ==========

        // Échappement HTML
        $this->twig->addFunction(new TwigFunction('e', function (string $value) {
            return e($value);
        }));

        // Conversion bytes
        $this->twig->addFunction(new TwigFunction('bytes_to_human', function (int $bytes, int $precision = 2) {
            return bytes_to_human($bytes, $precision);
        }));

        // Couleur aléatoire
        $this->twig->addFunction(new TwigFunction('random_color', function () {
            return random_color();
        }));

        // IP client
        $this->twig->addFunction(new TwigFunction('client_ip', function () {
            return get_client_ip();
        }));

        // ========== FONCTIONS LEGACY (COMPATIBILITÉ) ==========

        // Component helper
        $this->twig->addFunction(new TwigFunction('component', function (string $name, array $data = []) {
            foreach ($data as $key => $value) {
                if (is_string($value) && !isset($data[$key . '_raw'])) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
            return $this->renderComponent($name, $data);
        }, ['is_safe' => ['html']]));

        // Flash messages
        $this->twig->addFunction(new TwigFunction('flash', function () {
            $messages = $_SESSION['_flashes'] ?? [];
            return $messages;
        }));

        $this->twig->addFunction(new TwigFunction('clear_flash', function () {
            unset($_SESSION['_flashes']);
            return true;
        }));

        // Route helper (placeholder)
        $this->twig->addFunction(new TwigFunction('route', function (string $name, array $params = []) {
            // Future implementation avec Router
            return '#';
        }));

        // ========== FONCTIONS DEBUG ==========

        // Debug function
        $this->twig->addFunction(new TwigFunction('debug', function (mixed $data, bool $die = false) {
            if (env('APP_DEBUG', false)) {
                echo '<pre style="background: #000; color: #0f0; padding: 10px; margin: 10px; font-size: 12px;">';
                print_r($data);
                echo '</pre>';

                if ($die) {
                    die();
                }
            }
            return '';
        }, ['is_safe' => ['html']]));

        // Vérification JSON
        $this->twig->addFunction(new TwigFunction('is_json', function (string $string) {
            return is_json($string);
        }));

        // Array get avec notation pointée
        $this->twig->addFunction(new TwigFunction('array_get', function (array $array, string $key, mixed $default = null) {
            return array_get($array, $key, $default);
        }));

        // ========== FONCTIONS SPÉCIFIQUES ESCALADE ==========

        // Formatage difficulté escalade
        $this->twig->addFunction(new TwigFunction('format_difficulty', function ($difficulty) {
            return $difficulty ?: 'Non spécifié';
        }));

        // Formatage beauté (étoiles)
        $this->twig->addFunction(new TwigFunction('format_beauty', function ($beauty) {
            $beauty = (int) $beauty;
            return str_repeat('★', $beauty) . str_repeat('☆', 5 - $beauty);
        }));

        // Conversion style escalade
        $this->twig->addFunction(new TwigFunction('climbing_style_label', function ($style) {
            $styles = [
                'sport' => 'Sportive',
                'trad' => 'Traditionnelle',
                'boulder' => 'Bloc',
                'multipitch' => 'Grande voie',
                'mix' => 'Mixte',
                'aid' => 'Artificielle',
                'ice' => 'Glace'
            ];
            return $styles[$style] ?? ucfirst($style);
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

        // Date française
        $this->twig->addFilter(new TwigFilter('date_fr', function ($date, $format = 'd/m/Y') {
            return date_fr($date, $format);
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

        // Truncate
        $this->twig->addFilter(new TwigFilter('truncate', function ($text, $length = 100, $suffix = '...') {
            return truncate($text, $length, $suffix);
        }));

        // Slug
        $this->twig->addFilter(new TwigFilter('slug', function ($text) {
            return slug($text);
        }));

        // Formatage nombres
        $this->twig->addFilter(new TwigFilter('number_fr', function ($number, $decimals = 0) {
            return number_format_fr($number, $decimals);
        }));

        // Bytes to human
        $this->twig->addFilter(new TwigFilter('bytes', function ($bytes, $precision = 2) {
            return bytes_to_human($bytes, $precision);
        }));

        // JSON encode/decode
        $this->twig->addFilter(new TwigFilter('json_encode', function ($data) {
            return json_encode($data);
        }));

        $this->twig->addFilter(new TwigFilter('json_decode', function ($json, $assoc = true) {
            return json_decode($json, $assoc);
        }));

        // Formatage pourcentage
        $this->twig->addFilter(new TwigFilter('percentage', function ($value, $total, $decimals = 1) {
            if ($total == 0) return '0%';
            $percentage = ($value / $total) * 100;
            return number_format($percentage, $decimals, ',', ' ') . '%';
        }));

        // Valeur absolue
        $this->twig->addFilter(new TwigFilter('abs', function ($value) {
            return abs($value);
        }));

        // Capitalisation
        $this->twig->addFilter(new TwigFilter('ucfirst', function ($string) {
            return ucfirst($string);
        }));

        $this->twig->addFilter(new TwigFilter('ucwords', function ($string) {
            return ucwords($string);
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
