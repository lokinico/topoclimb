<?php

/**
 * Script pour corriger les références à la colonne 'code' inexistante dans SectorController
 */

echo "🔧 CORRECTION COLONNE 'CODE' DANS SECTORCONTROLLER\n";
echo "=" . str_repeat("=", 55) . "\n";

$sectorControllerPath = '/home/nibaechl/topoclimb/src/Controllers/SectorController.php';

if (!file_exists($sectorControllerPath)) {
    echo "❌ Fichier SectorController.php non trouvé\n";
    exit(1);
}

$content = file_get_contents($sectorControllerPath);
$originalContent = $content;

echo "📊 Recherche des références à la colonne 'code'...\n";

// 1. Supprimer la vérification d'unicité du code
$pattern1 = '/\s*\/\/ Vérifier unicité du code\s*\$existing = \$this->db->fetchOne\("SELECT id FROM climbing_sectors WHERE code = \?", \[\$data\[\'code\'\]\]\);\s*if \(\$existing\) \{\s*\$this->flash\(\'error\', \'Ce code de secteur existe déjà\'\);\s*return \$this->redirect\(\'\/sectors\/create\'\);\s*\}/s';

if (preg_match($pattern1, $content)) {
    echo "✅ Trouvé: vérification unicité code\n";
    $content = preg_replace($pattern1, '', $content);
}

// 2. Supprimer la validation du code obligatoire
$pattern2 = '/\s*if \(empty\(\$data\[\'code\'\]\)\) \{\s*throw new \\\\InvalidArgumentException\(\'Le code du secteur est obligatoire\'\);\s*\}/s';

if (preg_match($pattern2, $content)) {
    echo "✅ Trouvé: validation code obligatoire\n";
    $content = preg_replace($pattern2, '', $content);
}

// 3. Supprimer la validation de longueur du code
$pattern3 = '/\s*if \(strlen\(\$data\[\'code\'\]\) > 50\) \{\s*throw new \\\\InvalidArgumentException\(\'Le code ne peut pas dépasser 50 caractères\'\);\s*\}/s';

if (preg_match($pattern3, $content)) {
    echo "✅ Trouvé: validation longueur code\n";
    $content = preg_replace($pattern3, '', $content);
}

// 4. Supprimer 'code' de validateSectorData
$pattern4 = '/\'code\' => trim\(\$request->request->get\(\'code\', \'\'\)\),/';

if (preg_match($pattern4, $content)) {
    echo "✅ Trouvé: extraction code dans validateSectorData\n";
    $content = preg_replace($pattern4, '', $content);
}

// 5. Supprimer 'code' de baseColumns dans insertSector
$pattern5 = '/\'name\', \'code\', \'description\'/';

if (preg_match($pattern5, $content)) {
    echo "✅ Trouvé: code dans baseColumns\n";
    $content = str_replace("'name', 'code', 'description'", "'name', 'description'", $content);
}

// 6. Supprimer le paramètre code dans baseParams
$pattern6 = '/\$data\[\'name\'\],\s*\$data\[\'code\'\],\s*\$data\[\'description\'\]/';

if (preg_match($pattern6, $content)) {
    echo "✅ Trouvé: code dans baseParams\n";
    $content = preg_replace($pattern6, '$data[\'name\'], $data[\'description\']', $content);
}

// 7. Corriger baseValues pour enlever un '?'
$pattern7 = '/\$baseValues = \[\'?\?\', \'?\?\', \'?\?\', \'?\?\', \'?\?\', \$dateFunction, \$dateFunction\];/';

if (preg_match($pattern7, $content)) {
    echo "✅ Trouvé: baseValues avec 7 éléments\n";
    $content = preg_replace($pattern7, '$baseValues = [\'?\', \'?\', \'?\', \'?\', $dateFunction, $dateFunction];', $content);
}

// 8. Supprimer 'code' de getAvailableColumns fallback
$pattern8 = '/return \[\'id\', \'name\', \'code\', \'description\'/';

if (preg_match($pattern8, $content)) {
    echo "✅ Trouvé: code dans fallback columns\n";
    $content = str_replace("return ['id', 'name', 'code', 'description'", "return ['id', 'name', 'description'", $content);
}

// Vérifier si des changements ont été apportés
if ($content !== $originalContent) {
    echo "\n📝 Écriture des corrections...\n";
    
    if (file_put_contents($sectorControllerPath, $content)) {
        echo "✅ SectorController.php corrigé avec succès\n";
        
        // Compter les lignes modifiées
        $originalLines = substr_count($originalContent, "\n");
        $newLines = substr_count($content, "\n");
        $linesRemoved = $originalLines - $newLines;
        
        echo "📊 Statistiques:\n";
        echo "   - Lignes supprimées: $linesRemoved\n";
        echo "   - Toutes les références à la colonne 'code' ont été supprimées\n";
        
    } else {
        echo "❌ Erreur lors de l'écriture du fichier\n";
        exit(1);
    }
} else {
    echo "\n✅ Aucune modification nécessaire - le fichier est déjà correct\n";
}

echo "\n🎯 CORRECTION TERMINÉE\n";
echo "Le SectorController ne fait plus référence à la colonne 'code' inexistante.\n";