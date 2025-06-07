<?php

namespace TopoclimbCH\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TopoclimbCH\Core\TwigHelpers;

class View
{
    private Environment $twig;
    private array $globalVars = [];

    public function __construct(string $templatesPath = 'resources/views')
    {
        $loader = new FilesystemLoader($templatesPath);
        $this->twig = new Environment($loader, [
            'cache' => false, // Désactiver le cache en développement
            'debug' => $_ENV['APP_DEBUG'] ?? true,
            'strict_variables' => false
        ]);

        // Ajouter l'extension de debug si activé
        if ($_ENV['APP_DEBUG'] ?? true) {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        // Enregistrer nos helpers personnalisés
        $this->registerHelpers();

        // Ajouter les variables globales par défaut
        $this->addGlobalDefaults();
    }

    /**
     * Enregistre les helpers Twig personnalisés
     */
    private function registerHelpers(): void
    {
        // Récupérer l'instance Auth si disponible
        $auth = null;
        try {
            if (isset($_SESSION) && !empty($_SESSION)) {
                $session = Session::getInstance();
                $db = Database::getInstance();
                $auth = Auth::getInstance($session, $db);
            }
        } catch (\Exception $e) {
            // Auth pas encore disponible, ce n'est pas grave
            error_log("View: Auth not available during initialization: " . $e->getMessage());
        }

        // Ajouter l'extension des helpers
        $this->twig->addExtension(new TwigHelpers($auth));
    }

    /**
     * Ajoute les variables globales par défaut
     */
    private function addGlobalDefaults(): void
    {
        $this->addGlobal('app_name', $_ENV['APP_NAME'] ?? 'TopoclimbCH');
        $this->addGlobal('app_env', $_ENV['APP_ENV'] ?? 'development');
        $this->addGlobal('app_url', $_ENV['APP_URL'] ?? 'http://localhost');
        $this->addGlobal('app_locale', 'fr');

        // Ajouter la date actuelle
        $this->addGlobal('now', new \DateTime());

        // Ajouter les messages flash si la session existe
        try {
            if (isset($_SESSION)) {
                $session = Session::getInstance();
                $this->addGlobal('flash_messages', $session->getFlashes());
            }
        } catch (\Exception $e) {
            // Session pas encore disponible
        }
    }

    /**
     * Ajoute une variable globale
     */
    public function addGlobal(string $name, $value): void
    {
        $this->globalVars[$name] = $value;
        $this->twig->addGlobal($name, $value);
    }

    /**
     * Render un template avec des variables
     */
    public function render(string $template, array $variables = []): string
    {
        // Fusionner avec les variables globales
        $allVariables = array_merge($this->globalVars, $variables);

        // Ajouter le token CSRF si disponible
        if (!isset($allVariables['csrf_token'])) {
            $allVariables['csrf_token'] = $this->getCsrfToken();
        }

        // Ajouter les informations d'authentification actualisées
        $this->updateAuthInfo($allVariables);

        return $this->twig->render($template, $allVariables);
    }

    /**
     * Met à jour les informations d'authentification
     */
    private function updateAuthInfo(array &$variables): void
    {
        try {
            if (isset($_SESSION) && !empty($_SESSION)) {
                $session = Session::getInstance();
                $db = Database::getInstance();
                $auth = Auth::getInstance($session, $db);

                if ($auth->check()) {
                    $variables['auth_user'] = $auth->user();
                    $variables['auth_id'] = $auth->id();
                    $variables['auth_role'] = $auth->role();
                    $variables['is_admin'] = $auth->isAdmin();
                    $variables['is_moderator'] = $auth->isModerator();
                    $variables['is_accepted'] = $auth->isAccepted();
                    $variables['is_pending'] = $auth->isPending();
                    $variables['is_banned'] = $auth->isBanned();
                } else {
                    $variables['auth_user'] = null;
                    $variables['auth_id'] = null;
                    $variables['auth_role'] = 4; // Nouveau membre par défaut
                    $variables['is_admin'] = false;
                    $variables['is_moderator'] = false;
                    $variables['is_accepted'] = false;
                    $variables['is_pending'] = false;
                    $variables['is_banned'] = false;
                }
            }
        } catch (\Exception $e) {
            error_log("View: Error updating auth info: " . $e->getMessage());
            // Valeurs par défaut si erreur
            $variables['auth_user'] = null;
            $variables['auth_id'] = null;
            $variables['auth_role'] = 4;
            $variables['is_admin'] = false;
            $variables['is_moderator'] = false;
            $variables['is_accepted'] = false;
            $variables['is_pending'] = false;
            $variables['is_banned'] = false;
        }
    }

    /**
     * Récupère le token CSRF
     */
    private function getCsrfToken(): string
    {
        try {
            if (isset($_SESSION['csrf_token'])) {
                return $_SESSION['csrf_token'];
            }

            // Générer un nouveau token si pas existant
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;
            return $token;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Ajoute un filtre personnalisé
     */
    public function addFilter(string $name, callable $callback): void
    {
        $filter = new \Twig\TwigFilter($name, $callback);
        $this->twig->addFilter($filter);
    }

    /**
     * Ajoute une fonction personnalisée
     */
    public function addFunction(string $name, callable $callback): void
    {
        $function = new \Twig\TwigFunction($name, $callback);
        $this->twig->addFunction($function);
    }

    /**
     * Récupère l'environnement Twig (pour usage avancé)
     */
    public function getEnvironment(): Environment
    {
        return $this->twig;
    }

    /**
     * Vérifie si un template existe
     */
    public function exists(string $template): bool
    {
        return $this->twig->getLoader()->exists($template);
    }

    /**
     * Render un template et l'écrit directement
     */
    public function display(string $template, array $variables = []): void
    {
        echo $this->render($template, $variables);
    }

    /**
     * Ajoute des breadcrumbs automatiques
     */
    public function addBreadcrumb(string $title, string $url = ''): void
    {
        if (!isset($this->globalVars['breadcrumbs'])) {
            $this->globalVars['breadcrumbs'] = [];
        }

        $this->globalVars['breadcrumbs'][] = [
            'title' => $title,
            'url' => $url
        ];

        $this->addGlobal('breadcrumbs', $this->globalVars['breadcrumbs']);
    }

    /**
     * Efface les breadcrumbs
     */
    public function clearBreadcrumbs(): void
    {
        $this->globalVars['breadcrumbs'] = [];
        $this->addGlobal('breadcrumbs', []);
    }

    /**
     * Ajoute une métadonnée pour le SEO
     */
    public function addMeta(string $name, string $content): void
    {
        if (!isset($this->globalVars['meta'])) {
            $this->globalVars['meta'] = [];
        }

        $this->globalVars['meta'][$name] = $content;
        $this->addGlobal('meta', $this->globalVars['meta']);
    }

    /**
     * Méthodes utilitaires pour les contrôleurs
     */

    /**
     * Render une page avec layout automatique
     */
    public function page(string $template, array $variables = [], string $layout = 'layouts/app.twig'): string
    {
        // Si le template ne spécifie pas d'extension, on ajoute automatiquement
        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }

        return $this->render($template, $variables);
    }

    /**
     * Render une réponse JSON avec un template
     */
    public function json(array $data, string $template = null): string
    {
        if ($template) {
            $data['html'] = $this->render($template, $data);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Render un fragment HTML (pour AJAX)
     */
    public function fragment(string $template, array $variables = []): string
    {
        return $this->render($template, $variables);
    }

    /**
     * Gestion des erreurs de rendu
     */
    public function renderError(int $statusCode, string $message = '', array $variables = []): string
    {
        $errorTemplates = [
            403 => 'errors/403.twig',
            404 => 'errors/404.twig',
            500 => 'errors/500.twig'
        ];

        $template = $errorTemplates[$statusCode] ?? 'errors/500.twig';

        $variables = array_merge([
            'status_code' => $statusCode,
            'message' => $message,
            'title' => "Erreur $statusCode"
        ], $variables);

        if ($this->exists($template)) {
            return $this->render($template, $variables);
        }

        // Template d'erreur de base si le fichier n'existe pas
        return $this->renderBasicError($statusCode, $message);
    }

    /**
     * Template d'erreur basique en cas de problème avec les fichiers Twig
     */
    private function renderBasicError(int $statusCode, string $message): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Erreur $statusCode</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { background: #f8f8f8; padding: 20px; margin: 20px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='error'>
                <h1>Erreur $statusCode</h1>
                <p>" . htmlspecialchars($message ?: 'Une erreur est survenue') . "</p>
                <a href='/'>Retour à l'accueil</a>
            </div>
        </body>
        </html>";
    }
}
