<?php

/**
 * Bootstrap de l'application TopoclimbCH
 * Ce fichier initialise l'autoloader et configure l'environnement
 */

// Vérification de la version PHP
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('PHP 8.0 ou supérieur est requis');
}

// Configuration des erreurs pour le développement
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Chargement de l'autoloader Composer
$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die('Erreur: vendor/autoload.php non trouvé. Exécutez "composer install"');
}

require_once $autoloadFile;

// Chargement des variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
if (file_exists(__DIR__ . '/.env')) {
    $dotenv->load();
}

// Configuration des chemins
define('APP_ROOT', __DIR__);
define('BASE_PATH', __DIR__);  // Alias pour compatibilité
define('PUBLIC_PATH', APP_ROOT . '/public');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('VIEWS_PATH', APP_ROOT . '/resources/views');
define('CONFIG_PATH', APP_ROOT . '/config');

// Configuration par défaut de l'environnement
if (!isset($_ENV['APP_ENV'])) {
    $_ENV['APP_ENV'] = 'production';
}

// Configuration du fuseau horaire
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Zurich');

// Configuration des sessions
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.cookie_samesite', 'Lax');
}

// Fonction d'aide pour les chemins
function app_path($path = '') {
    return APP_ROOT . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

function public_path($path = '') {
    return PUBLIC_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

function storage_path($path = '') {
    return STORAGE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

function config_path($path = '') {
    return CONFIG_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

// Les fonctions url(), asset(), et env() sont définies dans src/Helpers/functions.php

// Créer les répertoires nécessaires s'ils n'existent pas
$directories = [
    storage_path('logs'),
    storage_path('cache'),
    storage_path('sessions'),
    storage_path('uploads')
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Configuration des logs
$logFile = storage_path('logs/app-' . date('Y-m-d') . '.log');
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0644);
}

// Fonction de logging simple
function app_log($message, $level = 'info') {
    $logFile = storage_path('logs/app-' . date('Y-m-d') . '.log');
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Log du démarrage de l'application
app_log('Application bootstrap completed');