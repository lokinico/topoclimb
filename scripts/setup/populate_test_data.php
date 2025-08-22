<?php
// populate_test_data.php - Ajoute des données fictives complètes pour tests
require_once __DIR__ . '/bootstrap.php';

echo "=== AJOUT DONNÉES FICTIVES DE TEST ===\n\n";

try {
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Base de données connectée\n";
    
    $db->beginTransaction();
    
    // 1. Expositions
    echo "\n1. AJOUT EXPOSITIONS:\n";
    echo "====================\n";
    
    $exposures = [
        ['N', 'Nord', 'Exposition fraîche, ombre le matin', 0, 45],
        ['NE', 'Nord-Est', 'Matin ombragé, soleil après midi', 45, 90],
        ['E', 'Est', 'Soleil matinal', 90, 135],
        ['SE', 'Sud-Est', 'Excellent soleil matin et midi', 135, 180],
        ['S', 'Sud', 'Exposition plein soleil', 180, 225],
        ['SW', 'Sud-Ouest', 'Soleil après-midi et soir', 225, 270],
        ['W', 'Ouest', 'Soleil du soir', 270, 315],
        ['NW', 'Nord-Ouest', 'Frais, soleil en soirée', 315, 360]
    ];
    
    foreach ($exposures as [$code, $name, $desc, $min, $max]) {
        try {
            $db->query("INSERT OR IGNORE INTO climbing_exposures (code, name, description, angle_min, angle_max) VALUES (?, ?, ?, ?, ?)",
                [$code, $name, $desc, $min, $max]);
            echo "✅ Exposition: $code ($name)\n";
        } catch (\Exception $e) {
            echo "⚠️  Erreur $code: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Mois
    echo "\n2. AJOUT MOIS:\n";
    echo "==============\n";
    
    $months = [
        ['JAN', 'Janvier', 'Hiver'],
        ['FEV', 'Février', 'Hiver'], 
        ['MAR', 'Mars', 'Printemps'],
        ['AVR', 'Avril', 'Printemps'],
        ['MAI', 'Mai', 'Printemps'],
        ['JUN', 'Juin', 'Été'],
        ['JUL', 'Juillet', 'Été'],
        ['AOU', 'Août', 'Été'],
        ['SEP', 'Septembre', 'Automne'],
        ['OCT', 'Octobre', 'Automne'],
        ['NOV', 'Novembre', 'Automne'],
        ['DEC', 'Décembre', 'Hiver']
    ];
    
    foreach ($months as [$code, $name, $season]) {
        try {
            $db->query("INSERT OR IGNORE INTO climbing_months (code, name, season) VALUES (?, ?, ?)",
                [$code, $name, $season]);
            echo "✅ Mois: $code ($name)\n";
        } catch (\Exception $e) {
            echo "⚠️  Erreur $code: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Secteurs enrichis avec toutes les colonnes
    echo "\n3. ENRICHISSEMENT SECTEURS:\n";
    echo "===========================\n";
    
    $sectorsData = [
        [
            'name' => 'Dalle des Géants',
            'code' => 'DAL001',
            'description' => 'Magnifique dalle calcaire avec voies techniques et variées',
            'access_info' => 'Parking au village, 15min de marche',
            'color' => '#8B4513',
            'access_time' => 15,
            'altitude' => 1200,
            'approach' => 'Sentier balisé depuis le parking principal',
            'height' => 80.5,
            'parking_info' => 'Parking gratuit 50 places',
            'coordinates_lat' => 46.2044,
            'coordinates_lng' => 7.1500,
            'coordinates_swiss_e' => '600000',
            'coordinates_swiss_n' => '150000',
            'region_id' => 1,
            'site_id' => 1
        ],
        [
            'name' => 'Mur des Champions',
            'code' => 'MUR002', 
            'description' => 'Paroi verticale idéale pour l\'entraînement sportif',
            'access_info' => 'Téléphérique jusqu\'à 1800m puis 30min',
            'color' => '#FF6347',
            'access_time' => 45,
            'altitude' => 1850,
            'approach' => 'Téléphérique puis sentier de montagne',
            'height' => 120.0,
            'parking_info' => 'Parking téléphérique payant',
            'coordinates_lat' => 46.0207,
            'coordinates_lng' => 7.7491,
            'coordinates_swiss_e' => '634000',
            'coordinates_swiss_n' => '135000',
            'region_id' => 1,
            'site_id' => 2
        ],
        [
            'name' => 'Overhangs Paradise',
            'code' => 'OVR003',
            'description' => 'Secteur de dévers extrêmes pour grimpeurs confirmés',
            'access_info' => 'Route forestière puis 45min de marche',
            'color' => '#9400D3',
            'access_time' => 50,
            'altitude' => 950,
            'approach' => 'Chemin forestier puis descente technique',
            'height' => 25.5,
            'parking_info' => 'Places limitées en forêt',
            'coordinates_lat' => 46.5197,
            'coordinates_lng' => 6.6323,
            'coordinates_swiss_e' => '542000',
            'coordinates_swiss_n' => '185000',
            'region_id' => 1,
            'site_id' => null
        ],
        [
            'name' => 'Fissures Magiques',
            'code' => 'FIS004',
            'description' => 'Spécialité fissures et dièdres dans un cadre exceptionnel',
            'access_info' => 'Accès direct depuis la route cantonale',
            'color' => '#32CD32',
            'access_time' => 5,
            'altitude' => 750,
            'approach' => 'Accès immédiat, quelques mètres seulement',
            'height' => 45.0,
            'parking_info' => 'Parking en bord de route',
            'coordinates_lat' => 47.3769,
            'coordinates_lng' => 8.5417,
            'coordinates_swiss_e' => '683000',
            'coordinates_swiss_n' => '247000',
            'region_id' => 2,
            'site_id' => 3
        ],
        [
            'name' => 'Couloir des Aiglons',
            'code' => 'COU005',
            'description' => 'Voies alpines d\'initiation dans un couloir protégé',
            'access_info' => 'Remontées mécaniques puis randonnée 1h30',
            'color' => '#4169E1',
            'access_time' => 120,
            'altitude' => 2200,
            'approach' => 'Télésiège puis marche d\'approche alpine',
            'height' => 200.0,
            'parking_info' => 'Parking station de ski',
            'coordinates_lat' => 46.4901,
            'coordinates_lng' => 7.8667,
            'coordinates_swiss_e' => '645000',
            'coordinates_swiss_n' => '162000',
            'region_id' => 1,
            'site_id' => null
        ]
    ];
    
    foreach ($sectorsData as $i => $sector) {
        $sectorId = $i + 1;
        
        // Mettre à jour le secteur existant avec toutes les données
        try {
            $db->query("UPDATE climbing_sectors SET 
                code = ?,
                description = ?,
                access_info = ?,
                color = ?,
                access_time = ?,
                altitude = ?,
                approach = ?,
                height = ?,
                parking_info = ?,
                coordinates_lat = ?,
                coordinates_lng = ?,
                coordinates_swiss_e = ?,
                coordinates_swiss_n = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?",
                [
                    $sector['code'],
                    $sector['description'],
                    $sector['access_info'],
                    $sector['color'],
                    $sector['access_time'],
                    $sector['altitude'],
                    $sector['approach'],
                    $sector['height'],
                    $sector['parking_info'],
                    $sector['coordinates_lat'],
                    $sector['coordinates_lng'],
                    $sector['coordinates_swiss_e'],
                    $sector['coordinates_swiss_n'],
                    $sectorId
                ]
            );
            echo "✅ Secteur enrichi: {$sector['name']} ({$sector['code']})\n";
        } catch (\Exception $e) {
            echo "⚠️  Erreur secteur $sectorId: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Relations secteur-expositions
    echo "\n4. AJOUT RELATIONS EXPOSITIONS:\n";
    echo "===============================\n";
    
    $sectorExposures = [
        [1, 5, 1, 'Exposition idéale toute la journée'], // Dalle des Géants - Sud
        [1, 4, 0, 'Matin excellent'], // Dalle des Géants - Sud-Est
        [2, 3, 1, 'Parfait le matin'], // Mur des Champions - Est  
        [2, 4, 0, 'Bon aussi en matinée'], // Mur des Champions - Sud-Est
        [3, 1, 1, 'Frais l\'été'], // Overhangs Paradise - Nord
        [3, 8, 0, 'Soirée possible'], // Overhangs Paradise - Nord-Ouest
        [4, 6, 1, 'Après-midi parfait'], // Fissures Magiques - Sud-Ouest
        [4, 7, 0, 'Soleil du soir'], // Fissures Magiques - Ouest
        [5, 2, 1, 'Matin protégé'], // Couloir des Aiglons - Nord-Est
        [5, 3, 0, 'Soleil matinal'] // Couloir des Aiglons - Est
    ];
    
    foreach ($sectorExposures as [$sectorId, $exposureId, $isPrimary, $notes]) {
        try {
            $db->query("INSERT OR IGNORE INTO climbing_sector_exposures (sector_id, exposure_id, is_primary, notes) VALUES (?, ?, ?, ?)",
                [$sectorId, $exposureId, $isPrimary, $notes]);
            echo "✅ Relation secteur $sectorId - exposition $exposureId\n";
        } catch (\Exception $e) {
            echo "⚠️  Erreur relation $sectorId-$exposureId: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Relations secteur-mois (qualité par mois)
    echo "\n5. AJOUT QUALITÉ PAR MOIS:\n";
    echo "==========================\n";
    
    $seasonalQuality = [
        // Dalle des Géants (1) - Bon toute l'année sauf hiver
        [1, [3,4,5,6,7,8,9,10], 'excellent'],
        [1, [1,2,11,12], 'poor'],
        
        // Mur des Champions (2) - Haute altitude, été seulement
        [2, [6,7,8,9], 'excellent'],
        [2, [5,10], 'good'],
        [2, [1,2,3,4,11,12], 'avoid'],
        
        // Overhangs Paradise (3) - Ombragé, bon en été
        [3, [6,7,8], 'excellent'],
        [3, [4,5,9,10], 'good'],
        [3, [1,2,3,11,12], 'fair'],
        
        // Fissures Magiques (4) - Accessible toute l'année
        [4, [3,4,5,6,7,8,9,10,11], 'excellent'],
        [4, [1,2,12], 'good'],
        
        // Couloir des Aiglons (5) - Très haute altitude
        [5, [7,8], 'excellent'],
        [5, [6,9], 'good'],
        [5, [1,2,3,4,5,10,11,12], 'avoid']
    ];
    
    foreach ($seasonalQuality as [$sectorId, $months, $quality]) {
        foreach ($months as $monthId) {
            try {
                $db->query("INSERT OR IGNORE INTO climbing_sector_months (sector_id, month_id, quality) VALUES (?, ?, ?)",
                    [$sectorId, $monthId, $quality]);
            } catch (\Exception $e) {
                echo "⚠️  Erreur qualité secteur $sectorId mois $monthId: " . $e->getMessage() . "\n";
            }
        }
        echo "✅ Qualité secteur $sectorId configurée pour " . count($months) . " mois\n";
    }
    
    // 6. Ajouter plus de routes variées
    echo "\n6. AJOUT ROUTES SUPPLÉMENTAIRES:\n";
    echo "================================\n";
    
    $additionalRoutes = [
        // Dalle des Géants (secteur 1)
        ['La Directe', '6a', 3, 1, 'Voie directe dans la dalle, technique'],
        ['Évasion', '5c', 4, 1, 'Traversée spectaculaire'],
        ['Pilier Central', '6b+', 5, 1, 'Pilier athlétique, réservé'],
        
        // Mur des Champions (secteur 2) 
        ['Face Nord', '7a', 4, 2, 'Voie de référence du secteur'],
        ['L\'Overture', '6c', 3, 2, 'Ouverture technique'],
        ['Champions Only', '7b+', 5, 2, 'Pour les experts uniquement'],
        ['Warm Up', '5a', 2, 2, 'Échauffement parfait'],
        
        // Overhangs Paradise (secteur 3)
        ['Toit de Glace', '7c', 5, 3, 'Dévers extrême'],
        ['Pendule Magique', '6c+', 4, 3, 'Mouvement de pendule requis'],
        
        // Fissures Magiques (secteur 4)
        ['Grande Fissure', '5b', 2, 4, 'Classique de la région'],
        ['Dièdre Parfait', '6a+', 4, 4, 'Technique de dièdre pure'],
        ['Crack Master', '6b', 3, 4, 'Spécialité fissures'],
        
        // Couloir des Aiglons (secteur 5)
        ['Couloir Facile', '4c', 1, 5, 'Initiation alpine'],
        ['Variante Directe', '5c+', 3, 5, 'Plus technique'],
        ['Sortie des Pros', '6a', 4, 5, 'Finale exposée']
    ];
    
    foreach ($additionalRoutes as [$name, $difficulty, $beauty, $sectorId, $description]) {
        try {
            $routeId = $db->insert('climbing_routes', [
                'name' => $name,
                'difficulty' => $difficulty,
                'beauty_rating' => $beauty,
                'sector_id' => $sectorId,
                'description' => $description,
                // 'active' => 1, // Colonne pas encore disponible
                'created_at' => date('Y-m-d H:i:s'),
                // 'created_by' => 1 // Colonne pas encore disponible
            ]);
            echo "✅ Route: $name ($difficulty) - Secteur $sectorId\n";
        } catch (\Exception $e) {
            echo "⚠️  Erreur route $name: " . $e->getMessage() . "\n";
        }
    }
    
    $db->commit();
    
    // 7. Statistiques finales
    echo "\n7. STATISTIQUES FINALES:\n";
    echo "========================\n";
    
    $stats = [
        'sectors' => $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors WHERE active = 1")['count'],
        'routes' => $db->fetchOne("SELECT COUNT(*) as count FROM climbing_routes")['count'],
        'exposures' => $db->fetchOne("SELECT COUNT(*) as count FROM climbing_exposures")['count'],
        'months' => $db->fetchOne("SELECT COUNT(*) as count FROM climbing_months")['count'],
        'sector_exposures' => $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sector_exposures")['count'],
        'seasonal_quality' => $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sector_months")['count']
    ];
    
    foreach ($stats as $item => $count) {
        echo "✅ " . ucfirst($item) . ": $count\n";
    }
    
    echo "\n✅ DONNÉES FICTIVES AJOUTÉES AVEC SUCCÈS\n";
    
} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN AJOUT DONNÉES ===\n";