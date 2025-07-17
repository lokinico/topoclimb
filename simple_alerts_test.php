<?php
/**
 * Test simple du système d'alertes
 */

require_once 'vendor/autoload.php';

echo "🚨 Test simple du système d'alertes TopoclimbCH\n";
echo "=================================================\n\n";

try {
    // Configuration de la base de données
    $config = [
        'host' => 'localhost',
        'driver' => 'sqlite',
        'database' => __DIR__ . '/storage/database/topoclimb_test.sqlite',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ];

    // Connexion directe à SQLite
    $pdo = new PDO('sqlite:' . __DIR__ . '/storage/database/topoclimb_test.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base de données réussie\n\n";

    // Créer les tables
    $createTables = "
        -- Table des types d'alertes
        CREATE TABLE IF NOT EXISTS climbing_alert_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            icon VARCHAR(50),
            color VARCHAR(7) DEFAULT '#007bff'
        );

        -- Table des alertes
        CREATE TABLE IF NOT EXISTS climbing_alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            alert_type_id INTEGER NOT NULL,
            region_id INTEGER,
            site_id INTEGER,
            sector_id INTEGER,
            severity VARCHAR(20) NOT NULL DEFAULT 'medium',
            start_date DATE NOT NULL,
            end_date DATE,
            active INTEGER DEFAULT 1,
            created_by INTEGER NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME
        );

        -- Table des confirmations d'alertes
        CREATE TABLE IF NOT EXISTS climbing_alert_confirmations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alert_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            confirmed_at DATETIME NOT NULL,
            UNIQUE(alert_id, user_id)
        );
    ";

    $pdo->exec($createTables);
    echo "✅ Tables créées avec succès\n\n";

    // Insérer les types d'alertes par défaut
    $insertTypes = "
        INSERT OR IGNORE INTO climbing_alert_types (name, description, icon, color) VALUES
        ('Fermeture', 'Site fermé temporairement ou définitivement', 'ban', '#dc3545'),
        ('Danger', 'Danger présent sur le site', 'exclamation-triangle', '#fd7e14'),
        ('Météo', 'Conditions météorologiques défavorables', 'cloud-rain', '#6c757d'),
        ('Travaux', 'Travaux en cours sur le site', 'hard-hat', '#ffc107'),
        ('Faune', 'Présence d''animaux sensibles', 'dove', '#28a745'),
        ('Accès', 'Problème d''accès au site', 'road', '#17a2b8');
    ";

    $pdo->exec($insertTypes);
    echo "✅ Types d'alertes par défaut insérés\n\n";

    // Vérifier les types d'alertes
    $stmt = $pdo->query("SELECT * FROM climbing_alert_types ORDER BY name");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Types d'alertes disponibles (" . count($types) . "):\n";
    foreach ($types as $type) {
        echo "  - ID {$type['id']}: {$type['name']} ({$type['icon']})\n";
    }
    echo "\n";

    // Créer une alerte de test
    $insertAlert = "
        INSERT INTO climbing_alerts (
            title, description, alert_type_id, severity, 
            start_date, end_date, active, created_by, created_at
        ) VALUES (
            'Test d''alerte système', 
            'Ceci est une alerte de test pour valider le système.', 
            1, 'medium', 
            date('now'), date('now', '+7 days'), 1, 1, datetime('now')
        )
    ";

    $pdo->exec($insertAlert);
    $alertId = $pdo->lastInsertId();
    echo "✅ Alerte de test créée (ID: {$alertId})\n\n";

    // Récupérer les alertes avec jointures
    $alertsQuery = "
        SELECT 
            a.id, a.title, a.description, a.severity, 
            a.start_date, a.end_date, a.active, a.created_at,
            t.name as alert_type_name,
            t.icon as alert_type_icon,
            COUNT(c.id) as confirmation_count
        FROM climbing_alerts a
        JOIN climbing_alert_types t ON a.alert_type_id = t.id
        LEFT JOIN climbing_alert_confirmations c ON a.id = c.alert_id
        WHERE a.active = 1
        GROUP BY a.id, a.title, a.description, a.severity, 
                 a.start_date, a.end_date, a.active, a.created_at,
                 t.name, t.icon
        ORDER BY a.created_at DESC
    ";

    $stmt = $pdo->query($alertsQuery);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Alertes actives (" . count($alerts) . "):\n";
    foreach ($alerts as $alert) {
        echo "  - ID {$alert['id']}: {$alert['title']}\n";
        echo "    Type: {$alert['alert_type_name']} ({$alert['alert_type_icon']})\n";
        echo "    Gravité: {$alert['severity']}\n";
        echo "    Période: {$alert['start_date']} → {$alert['end_date']}\n";
        echo "    Confirmations: {$alert['confirmation_count']}\n";
        echo "\n";
    }

    // Test de l'API d'alertes
    echo "Test API d'alertes:\n";
    $apiQuery = "
        SELECT 
            a.id, a.title, a.description, a.severity,
            a.start_date, a.end_date, a.created_at,
            t.name as alert_type_name,
            COUNT(c.id) as confirmation_count
        FROM climbing_alerts a
        JOIN climbing_alert_types t ON a.alert_type_id = t.id
        LEFT JOIN climbing_alert_confirmations c ON a.id = c.alert_id
        WHERE a.active = 1
        GROUP BY a.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ";

    $stmt = $pdo->query($apiQuery);
    $apiAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "API retourne: " . count($apiAlerts) . " alertes\n";
    echo "Format JSON:\n";
    echo json_encode($apiAlerts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";

    // Nettoyage
    $pdo->exec("DELETE FROM climbing_alerts WHERE title = 'Test d''alerte système'");
    echo "✅ Alerte de test supprimée\n\n";

    // Vérifier la structure des tables
    echo "Structure des tables:\n";
    $tables = ['climbing_alert_types', 'climbing_alerts', 'climbing_alert_confirmations'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  - {$table}: {$result['count']} enregistrements\n";
    }

    echo "\n🎉 Système d'alertes validé avec succès!\n";
    echo "Les tables sont prêtes et les fonctionnalités de base marchent.\n\n";

    echo "URLs à tester:\n";
    echo "- Liste des alertes: http://localhost:8000/alerts\n";
    echo "- Créer une alerte: http://localhost:8000/alerts/create\n";
    echo "- API alertes: http://localhost:8000/api/alerts\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}