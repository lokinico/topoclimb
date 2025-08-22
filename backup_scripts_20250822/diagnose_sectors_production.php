<?php
/**
 * Script de diagnostic pour identifier le problème exact des secteurs en production
 */

echo "=== DIAGNOSTIC SECTEURS PRODUCTION ===\n\n";

// 1. Vérifier la connexion à la base de données
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sh139940_', 'sh139940_', 'RY[p]x1n4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion DB : OK\n";
} catch (Exception $e) {
    echo "❌ Connexion DB : ERREUR - " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Lister toutes les tables secteurs disponibles
echo "\n=== TABLES SECTEURS DISPONIBLES ===\n";
$tables = $pdo->query("SHOW TABLES LIKE '%sector%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "- $table\n";
}

// 3. Vérifier structure table climbing_sectors
echo "\n=== STRUCTURE climbing_sectors ===\n";
try {
    $columns = $pdo->query("DESCRIBE climbing_sectors")->fetchAll(PDO::FETCH_ASSOC);
    $hasCodeColumn = false;
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
        if ($col['Field'] === 'code') {
            $hasCodeColumn = true;
        }
    }
    echo $hasCodeColumn ? "✅ Colonne 'code' : PRÉSENTE\n" : "❌ Colonne 'code' : ABSENTE\n";
} catch (Exception $e) {
    echo "❌ Table climbing_sectors : N'EXISTE PAS - " . $e->getMessage() . "\n";
}

// 4. Vérifier ancienne table secteur
echo "\n=== STRUCTURE secteur (ancienne) ===\n";
try {
    $columns = $pdo->query("DESCRIBE secteur")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Compter les secteurs
    $count = $pdo->query("SELECT COUNT(*) FROM secteur")->fetchColumn();
    echo "✅ Nombre de secteurs : $count\n";
} catch (Exception $e) {
    echo "❌ Table secteur : N'EXISTE PAS - " . $e->getMessage() . "\n";
}

// 5. Tester la requête problématique du SectorService
echo "\n=== TEST REQUÊTE PROBLÉMATIQUE ===\n";
try {
    $sql = "SELECT 
        s.id, 
        s.name, 
        s.code,
        s.region_id,
        r.name as region_name,
        s.description,
        s.altitude,
        s.coordinates_lat,
        s.coordinates_lng,
        s.active,
        (SELECT COUNT(*) FROM climbing_routes WHERE sector_id = s.id) as routes_count
    FROM climbing_sectors s 
    LEFT JOIN climbing_regions r ON s.region_id = r.id 
    WHERE s.active = 1
    ORDER BY s.name ASC
    LIMIT 50";
    
    $result = $pdo->query($sql)->fetchAll();
    echo "✅ Requête avec 'code' : OK - " . count($result) . " secteurs\n";
} catch (Exception $e) {
    echo "❌ Requête avec 'code' : ERREUR - " . $e->getMessage() . "\n";
    
    // Test requête de fallback
    try {
        $sql = "SELECT 
            s.id, 
            s.name, 
            s.region_id,
            r.name as region_name,
            s.description,
            s.altitude,
            s.coordinates_lat,
            s.coordinates_lng,
            s.active
        FROM climbing_sectors s 
        LEFT JOIN climbing_regions r ON s.region_id = r.id 
        WHERE s.active = 1
        ORDER BY s.name ASC 
        LIMIT 50";
        
        $result = $pdo->query($sql)->fetchAll();
        echo "✅ Requête sans 'code' : OK - " . count($result) . " secteurs\n";
    } catch (Exception $e) {
        echo "❌ Requête sans 'code' : ERREUR - " . $e->getMessage() . "\n";
    }
}

// 6. Analyser authentification utilisateur
echo "\n=== AUTHENTIFICATION UTILISATEUR ===\n";
try {
    $user = $pdo->query("SELECT id, nom, prenom, autorisation FROM users WHERE id = 1")->fetch();
    if ($user) {
        echo "✅ Utilisateur ID 1 trouvé : {$user['prenom']} {$user['nom']} (niveau {$user['autorisation']})\n";
    } else {
        echo "❌ Utilisateur ID 1 : NON TROUVÉ\n";
    }
} catch (Exception $e) {
    echo "❌ Vérification utilisateur : ERREUR - " . $e->getMessage() . "\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";