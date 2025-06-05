// Test rapide à mettre dans un fichier temporaire
require_once 'vendor/autoload.php';

try {
$validator = \Symfony\Component\Validator\Validation::createValidator();
echo "✅ Symfony Validator installé correctement !";
} catch (Error $e) {
echo "❌ Erreur : " . $e->getMessage();
}