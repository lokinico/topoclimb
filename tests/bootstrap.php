<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Définir le chemin de base de l'application
define('BASE_PATH', dirname(__DIR__));

// Charger les variables d'environnement de test
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH, '.env.testing');
$dotenv->safeLoad();

// Autres initialisations nécessaires pour les tests
