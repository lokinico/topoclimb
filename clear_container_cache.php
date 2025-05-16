<?php
// clear_container_cache.php

// Chemins potentiels du cache
$cachePaths = [
    __DIR__ . '/cache/container',
    __DIR__ . '/var/cache/container',
    '/tmp/symfony/cache/container'
];

foreach ($cachePaths as $path) {
    if (is_dir($path)) {
        echo "Vidage du cache conteneur dans $path\n";
        array_map('unlink', glob("$path/*"));
    }
}

echo "Cache du conteneur vidé!\n";
