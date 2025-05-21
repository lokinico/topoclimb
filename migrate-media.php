<?php

/**
 * Script de migration des médias accessibles via le navigateur
 * 
 * IMPORTANT: À placer dans un répertoire protégé ou à supprimer après utilisation
 */

// Définir une clé secrète pour protéger l'accès (à changer)
define('SECRET_KEY', 'TestMigrationKey');

// Augmenter les limites d'exécution si possible
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '256M');

// Charger l'autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Charger l'application
require_once __DIR__ . '/../src/Core/Application.php';

// Vérifier le token de sécurité
$providedKey = $_GET['key'] ?? '';
if (!hash_equals(SECRET_KEY, $providedKey)) {
    header('HTTP/1.0 403 Forbidden');
    echo '<h1>Accès refusé</h1>';
    exit;
}

// Paramètres
$batchSize = isset($_GET['batch_size']) ? (int)$_GET['batch_size'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';

// Fonction pour générer une URL pour le prochain lot
function getNextBatchUrl($offset, $batchSize, $preview)
{
    $url = $_SERVER['PHP_SELF'] . '?key=' . urlencode(SECRET_KEY) .
        '&batch_size=' . $batchSize .
        '&offset=' . $offset;

    if ($preview) {
        $url .= '&preview=1';
    }

    return $url;
}

// Fonction pour charger le script de migration
function loadMigrationScript()
{
    require_once __DIR__ . '/../src/Scripts/MediaMigrationScript.php';
    return new \TopoclimbCH\Scripts\MediaMigrationScript();
}

// Fonction pour obtenir les médias à migrer
function getMediaToMigrate($db, $offset, $batchSize)
{
    $query = "SELECT * FROM climbing_media WHERE file_path LIKE '%images/%' OR file_path IS NULL OR file_path = '' LIMIT ? OFFSET ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $batchSize, \PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

// Fonction pour obtenir le nombre total de médias à migrer
function getTotalMediaToMigrate($db)
{
    $query = "SELECT COUNT(*) FROM climbing_media WHERE file_path LIKE '%images/%' OR file_path IS NULL OR file_path = ''";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

// Sortie HTML
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration des médias TopoclimbCH</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: #2c3e50;
        }

        .container {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
        }

        .progress {
            background-color: #f3f3f3;
            height: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .progress-bar {
            background-color: #27ae60;
            height: 100%;
            border-radius: 5px;
            text-align: center;
            color: white;
        }

        .log {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            white-space: pre-wrap;
        }

        .success {
            color: #27ae60;
        }

        .error {
            color: #e74c3c;
        }

        .warning {
            color: #f39c12;
        }

        .controls {
            margin-top: 20px;
        }

        .controls a,
        .controls button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }

        .controls a:hover,
        .controls button:hover {
            background-color: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f5f5f5;
        }
    </style>
</head>

<body>
    <h1>Migration des médias TopoclimbCH</h1>
    <div class="container">
        <?php
        try {
            // Obtenir l'instance de l'application
            $app = \TopoclimbCH\Core\Application::getInstance();
            $db = $app->getDatabase();

            // Calculer le progrès
            $total = getTotalMediaToMigrate($db);
            $progress = $total > 0 ? round(($offset / $total) * 100) : 100;

            echo "<h2>Progression de la migration</h2>";
            echo "<div class='progress'><div class='progress-bar' style='width: {$progress}%'>{$progress}%</div></div>";
            echo "<p>Médias traités: $offset sur $total</p>";

            // Obtenir les médias pour ce lot
            $mediaItems = getMediaToMigrate($db, $offset, $batchSize);
            $count = count($mediaItems);

            if ($count === 0) {
                echo "<div class='success'><strong>Migration terminée!</strong> Tous les médias ont été migrés.</div>";
            } else {
                echo "<h3>Lot actuel: $count médias (de l'index $offset à " . ($offset + $count) . ")</h3>";

                // Afficher un tableau des fichiers à traiter
                echo "<table>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Nom de fichier</th>
                            <th>Chemin actuel</th>
                        </tr>";

                foreach ($mediaItems as $item) {
                    echo "<tr>
                            <td>{$item['id']}</td>
                            <td>{$item['media_type']}</td>
                            <td>{$item['filename']}</td>
                            <td>{$item['file_path']}</td>
                          </tr>";
                }

                echo "</table>";

                // Mode aperçu ou migration réelle
                if ($preview) {
                    echo "<div class='warning'>Mode aperçu: aucune modification n'a été effectuée.</div>";
                } else {
                    echo "<div class='log'>";

                    // Exécuter la migration pour ce lot
                    $migrationScript = loadMigrationScript();

                    // Activer la mise en buffer pour capturer la sortie
                    ob_start();

                    // Migrer chaque média individuellement
                    foreach ($mediaItems as $item) {
                        $migrationScript->migrateMedia($item);
                    }

                    // Récupérer la sortie
                    $output = ob_get_clean();
                    echo htmlspecialchars($output);

                    echo "</div>";
                }

                // Calculer l'index pour le prochain lot
                $nextOffset = $offset + $count;
                $nextUrl = getNextBatchUrl($nextOffset, $batchSize, $preview);

                // Barre d'outils navigation / configuration
                echo "<div class='controls'>";

                if ($preview) {
                    echo "<a href='" . getNextBatchUrl($offset, $batchSize, false) . "'>Migrer ce lot</a>";
                } else {
                    echo "<a href='$nextUrl'>Continuer au prochain lot</a>";
                }

                // Ajuster la taille du lot
                echo "<a href='" . getNextBatchUrl($offset, $batchSize * 2, $preview) . "'>Doubler la taille du lot</a>";
                echo "<a href='" . getNextBatchUrl($offset, max(1, $batchSize / 2), $preview) . "'>Réduire de moitié la taille du lot</a>";

                // Basculer entre aperçu et migration
                echo "<a href='" . getNextBatchUrl($offset, $batchSize, !$preview) . "'>" .
                    ($preview ? "Passer en mode migration" : "Passer en mode aperçu") . "</a>";

                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'><strong>Erreur:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        ?>
    </div>
    <p><strong>Note:</strong> Ce script devrait être supprimé après utilisation pour des raisons de sécurité.</p>
</body>

</html>