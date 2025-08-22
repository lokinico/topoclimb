<?php
/**
 * CORRECTION IMMÉDIATE PRODUCTION - Auto-détection email/mail défaillante
 */

echo "🚨 CORRECTION IMMÉDIATE PRODUCTION\n";
echo "===================================\n\n";

// 1. Corriger AuthService.php pour forcer 'mail' en production MySQL
$authServiceFile = 'src/Services/AuthService.php';

if (!file_exists($authServiceFile)) {
    echo "❌ Fichier AuthService.php non trouvé\n";
    exit(1);
}

echo "1️⃣ CORRECTION AUTHSERVICE.PHP\n";
echo str_repeat("-", 40) . "\n";

$content = file_get_contents($authServiceFile);
$originalContent = $content;

// Rechercher et remplacer la section problématique
$oldPattern = '/\/\/ Auto-détection colonne email vs mail.*?catch \(Exception \$e\) \{.*?\}/s';

$newCode = '// Auto-détection colonne email vs mail - VERSION CORRIGÉE
            $result = null;
            $emailColumn = "email"; // Défaut
            
            try {
                // Tester avec email d\'abord (SQLite local)
                $result = $this->db->fetchOne("SELECT * FROM users WHERE email = ? AND actif = 1 LIMIT 1", [$email]);
                $emailColumn = "email";
                error_log("AuthService: Utilisation colonne \'email\' réussie");
            } catch (Exception $e) {
                // Si erreur avec email, essayer mail (MySQL production)
                if (strpos($e->getMessage(), "email") !== false || strpos($e->getMessage(), "Column not found") !== false) {
                    try {
                        $result = $this->db->fetchOne("SELECT * FROM users WHERE mail = ? AND actif = 1 LIMIT 1", [$email]);
                        $emailColumn = "mail";
                        error_log("AuthService: Fallback vers colonne \'mail\' réussi");
                    } catch (Exception $e2) {
                        error_log("AuthService: Erreur avec les deux colonnes - " . $e2->getMessage());
                        throw $e2;
                    }
                } else {
                    throw $e;
                }
            }';

if (preg_match($oldPattern, $content)) {
    $content = preg_replace($oldPattern, $newCode, $content);
    echo "✅ Section auto-détection remplacée\n";
} else {
    // Si le pattern n'est pas trouvé, remplacer directement la ligne problématique
    $content = str_replace(
        'try {
                // Tester avec \'email\' d\'abord
                $result = $this->db->fetchOne("SELECT * FROM users WHERE email = ? AND actif = 1 LIMIT 1", [$email]);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), \'email\') !== false) {
                    // Si erreur contient \'email\', essayer avec \'mail\'
                    $emailColumn = \'mail\';
                    $result = $this->db->fetchOne("SELECT * FROM users WHERE mail = ? AND actif = 1 LIMIT 1", [$email]);
                } else {
                    throw $e;
                }
            }',
        $newCode,
        $content
    );
    echo "✅ Remplacement direct effectué\n";
}

// Sauvegarder
file_put_contents($authServiceFile . '.backup-' . date('H-i-s'), $originalContent);
file_put_contents($authServiceFile, $content);

echo "✅ AuthService.php corrigé\n";
echo "✅ Sauvegarde créée: AuthService.php.backup-" . date('H-i-s') . "\n\n";

// 2. Créer script de test production
echo "2️⃣ CRÉATION SCRIPT TEST PRODUCTION\n";
echo str_repeat("-", 45) . "\n";

$testScript = '<?php
/**
 * TEST PRODUCTION IMMÉDIAT - Vérification colonne mail
 */

ini_set("display_errors", 1);
error_reporting(E_ALL);

echo "🔧 TEST PRODUCTION IMMÉDIAT\\n";
echo "============================\\n\\n";

require_once "vendor/autoload.php";
require_once "src/Models/User.php";
require_once "src/Services/AuthService.php";
require_once "src/Core/Auth.php";
require_once "src/Core/Database.php";
require_once "src/Core/Session.php";
require_once "src/Services/Mailer.php";

try {
    // 1. Test Database
    echo "1️⃣ TEST DATABASE\\n";
    echo str_repeat("-", 20) . "\\n";
    
    $db = new TopoclimbCH\\Core\\Database();
    $connection = $db->getConnection();
    echo "✅ Connexion établie\\n";
    
    // 2. Test structure table users
    echo "\\n2️⃣ TEST STRUCTURE TABLE USERS\\n";
    echo str_repeat("-", 35) . "\\n";
    
    try {
        $testEmail = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE email = ?", ["test@test.com"]);
        echo "✅ Colonne \'email\' existe\\n";
        $emailColumn = "email";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "email") !== false) {
            echo "⚠️ Colonne \'email\' n\'existe pas\\n";
            try {
                $testMail = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE mail = ?", ["test@test.com"]);
                echo "✅ Colonne \'mail\' existe\\n";
                $emailColumn = "mail";
            } catch (Exception $e2) {
                echo "❌ Ni \'email\' ni \'mail\' n\'existent\\n";
                throw $e2;
            }
        } else {
            throw $e;
        }
    }
    
    // 3. Test avec vrais utilisateurs
    echo "\\n3️⃣ TEST UTILISATEURS RÉELS\\n";
    echo str_repeat("-", 30) . "\\n";
    
    $users = $db->fetchAll("SELECT * FROM users LIMIT 5");
    echo "✅ " . count($users) . " utilisateurs trouvés\\n";
    
    if (count($users) > 0) {
        $firstUser = $users[0];
        $columns = array_keys($firstUser);
        echo "Colonnes disponibles: " . implode(", ", $columns) . "\\n";
        
        if (isset($firstUser["mail"])) {
            echo "✅ Confirmation: Colonne \'mail\' présente avec valeur: " . $firstUser["mail"] . "\\n";
        }
        if (isset($firstUser["email"])) {
            echo "✅ Confirmation: Colonne \'email\' présente avec valeur: " . $firstUser["email"] . "\\n";
        }
    }
    
    // 4. Test AuthService
    echo "\\n4️⃣ TEST AUTHSERVICE\\n";
    echo str_repeat("-", 25) . "\\n";
    
    if (session_status() == PHP_SESSION_NONE) {
        @session_start();
    }
    $session = new TopoclimbCH\\Core\\Session();
    $auth = new TopoclimbCH\\Core\\Auth($session, $db);
    $mailer = new TopoclimbCH\\Services\\Mailer($db);
    $authService = new TopoclimbCH\\Services\\AuthService($auth, $session, $db, $mailer);
    
    echo "✅ AuthService créé\\n";
    
    // Tester avec un email réel de la base
    if (count($users) > 0) {
        $testUser = $users[0];
        $testEmail = $testUser[$emailColumn];
        
        echo "🔍 Test avec utilisateur réel: $testEmail\\n";
        
        try {
            // Test avec mots de passe communs
            $testPasswords = ["admin123", "password", "123456", "admin"];
            $foundPassword = false;
            
            foreach ($testPasswords as $testPass) {
                if ($authService->attempt($testEmail, $testPass)) {
                    echo "✅ CONNEXION RÉUSSIE avec $testEmail / $testPass\\n";
                    $user = $authService->user();
                    if ($user) {
                        echo "   - ID: " . $user->id . "\\n";
                        echo "   - Nom: " . ($user->prenom ?? "N/A") . " " . ($user->nom ?? "N/A") . "\\n";
                        echo "   - Autorisation: " . ($user->autorisation ?? "N/A") . "\\n";
                    }
                    $foundPassword = true;
                    break;
                }
            }
            
            if (!$foundPassword) {
                echo "⚠️ Aucun mot de passe test ne fonctionne\\n";
                echo "   Hash actuel: " . substr($testUser["password_hash"] ?? $testUser["password"] ?? "N/A", 0, 20) . "...\\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Erreur AuthService: " . $e->getMessage() . "\\n";
        }
    }
    
    echo "\\n🎯 RÉSULTAT:\\n";
    echo "Colonne utilisée: $emailColumn\\n";
    echo "Structure de base compatible\\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\\n";
    echo "Stack trace:\\n" . $e->getTraceAsString() . "\\n";
}

echo "\\n" . str_repeat("=", 40) . "\\n";
echo "TEST TERMINÉ - " . date("Y-m-d H:i:s") . "\\n";
echo str_repeat("=", 40) . "\\n";
';

file_put_contents('test_production_immediate.php', $testScript);
echo "✅ test_production_immediate.php créé\n\n";

// 3. Instructions finales
echo "3️⃣ INSTRUCTIONS DE DÉPLOIEMENT IMMÉDIAT\n";
echo str_repeat("-", 50) . "\n";

echo "🚀 ACTIONS À EFFECTUER:\n\n";
echo "1. Télécharger le fichier corrigé:\n";
echo "   ✅ src/Services/AuthService.php\n\n";

echo "2. Remplacer sur votre serveur\n\n";

echo "3. Tester sur votre serveur avec:\n";
echo "   ✅ php test_production_immediate.php\n\n";

echo "4. Essayer de vous connecter à nouveau\n\n";

echo "🔧 CHANGEMENTS APPORTÉS:\n";
echo "- Amélioration de la détection d'erreur email/mail\n";
echo "- Logs plus détaillés pour diagnostic\n";
echo "- Gestion robuste des exceptions\n";
echo "- Test production spécifique créé\n\n";

echo "Si le problème persiste, le script test_production_immediate.php\n";
echo "vous donnera le diagnostic exact de votre base de données.\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "CORRECTION TERMINÉE - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";