<?php
/**
 * Script de configuration spécifique pour Plesk
 * À exécuter après le déploiement pour vérifier l'environnement
 */

echo "🔧 Configuration TopoclimbCH pour Plesk\n";
echo "======================================\n\n";

// 1. Vérifier la version PHP
echo "1. Vérification PHP...\n";
echo "Version PHP : " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.0', '>=')) {
    echo "✅ Version PHP compatible\n";
} else {
    echo "❌ Version PHP trop ancienne (8.0+ requis)\n";
}

// 2. Vérifier les extensions PHP
echo "\n2. Vérification des extensions PHP...\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'zip', 'gd', 'intl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension $ext chargée\n";
    } else {
        echo "❌ Extension $ext manquante\n";
        $missingExtensions[] = $ext;
    }
}

// 3. Vérifier les permissions
echo "\n3. Vérification des permissions...\n";
$directories = ['storage', 'storage/logs', 'storage/cache', 'storage/sessions'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ Répertoire $dir accessible en écriture\n";
        } else {
            echo "❌ Répertoire $dir non accessible en écriture\n";
        }
    } else {
        echo "⚠️  Répertoire $dir n'existe pas\n";
    }
}

// 4. Vérifier les fichiers critiques
echo "\n4. Vérification des fichiers critiques...\n";
$criticalFiles = [
    'resources/views/checklists/index.twig',
    'resources/views/equipment/index.twig',
    'resources/views/map/index.twig',
    'public/css/pages/map.css',
    'src/Controllers/ChecklistController.php',
    'src/Controllers/EquipmentController.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "✅ Fichier $file trouvé\n";
    } else {
        echo "❌ Fichier $file manquant\n";
    }
}

// 5. Vérifier la configuration .env
echo "\n5. Vérification de la configuration...\n";
if (file_exists('.env')) {
    echo "✅ Fichier .env trouvé\n";
} else {
    echo "❌ Fichier .env manquant (copiez .env.example)\n";
}

// 6. Test de connexion à la base de données (si .env existe)
if (file_exists('.env')) {
    echo "\n6. Test de connexion à la base de données...\n";
    
    // Lire le fichier .env
    $envContent = file_get_contents('.env');
    if ($envContent) {
        echo "ℹ️  Fichier .env lu avec succès\n";
        echo "⚠️  Vérifiez manuellement la connexion à la base de données\n";
    }
}

// 7. Recommandations
echo "\n7. Recommandations pour Plesk...\n";
echo "✅ Configurez le document root vers le dossier public/\n";
echo "✅ Activez mod_rewrite pour Apache\n";
echo "✅ Définissez les variables d'environnement dans .env\n";
echo "✅ Configurez un certificat SSL\n";
echo "✅ Activez la compression gzip\n";

// 8. Résumé
echo "\n📊 Résumé de la configuration :\n";
echo "==============================\n";
echo "PHP Version : " . PHP_VERSION . "\n";
echo "Extensions manquantes : " . (empty($missingExtensions) ? 'Aucune' : implode(', ', $missingExtensions)) . "\n";
echo "Statut : " . (empty($missingExtensions) ? '✅ Prêt' : '❌ Configuration requise') . "\n";

echo "\n🚀 Prochaines étapes :\n";
echo "1. Corrigez les problèmes signalés ci-dessus\n";
echo "2. Configurez .env avec vos paramètres de base de données\n";
echo "3. Testez les routes : /checklists, /equipment, /map\n";
echo "4. Exécutez php test_deployment.php pour validation complète\n";
echo "\n✅ Configuration terminée !\n";