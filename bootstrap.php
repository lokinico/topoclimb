<?php

/**
 * Bootstrap file for TopoclimbCH
 * Defines essential constants and setup
 */

// Définir le chemin de base de l'application
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// Chargement de l'autoloader Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Chargement des variables d'environnement
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Configuration des erreurs selon l'environnement
$environment = $_ENV['APP_ENV'] ?? 'production';
$debug = $_ENV['APP_DEBUG'] ?? false;

if ($environment === 'development' || $debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Europe/Zurich');

// Création des répertoires nécessaires
$directories = [
    BASE_PATH . '/storage/cache',
    BASE_PATH . '/storage/logs',
    BASE_PATH . '/storage/uploads',
    BASE_PATH . '/storage/sessions'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}