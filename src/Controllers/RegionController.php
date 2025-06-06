public function index(Request $request): Response
{
error_log("=== STEP 1: Début méthode index ===");

try {
error_log("STEP 2: Avant création Response");

// Test le plus simple possible : Response manuelle
$response = new \TopoclimbCH\Core\Response();
error_log("STEP 3: Response créée");

$response->setContent('<html>

<body>
    <h1>Test RegionController</h1>
    <p>Si vous voyez ceci, le contrôleur fonctionne !</p>
</body>

</html>');
error_log("STEP 4: Content défini");

$response->setStatusCode(200);
error_log("STEP 5: Status code défini");

error_log("STEP 6: Retour de la response");
return $response;

} catch (\Exception $e) {
error_log("ERREUR CATCHÉE: " . $e->getMessage());
error_log("FICHIER: " . $e->getFile() . " LIGNE: " . $e->getLine());

// Response d'erreur manuelle
$errorResponse = new \TopoclimbCH\Core\Response();
$errorResponse->setContent('<html>

<body>
    <h1>Erreur détectée</h1>
    <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
</body>

</html>');
$errorResponse->setStatusCode(500);
return $errorResponse;
} catch (\Throwable $t) {
error_log("THROWABLE CATCHÉE: " . $t->getMessage());
error_log("FICHIER: " . $t->getFile() . " LIGNE: " . $t->getLine());

$errorResponse = new \TopoclimbCH\Core\Response();
$errorResponse->setContent('<html>

<body>
    <h1>Throwable détectée</h1>
    <pre>' . htmlspecialchars($t->getMessage()) . '</pre>
</body>

</html>');
$errorResponse->setStatusCode(500);
return $errorResponse;
}
}