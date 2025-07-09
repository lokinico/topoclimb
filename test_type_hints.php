<?php

// Test complet des type-hints pour éviter les erreurs de déploiement
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

use TopoclimbCH\Core\ContainerBuilder;

echo "🔍 TYPE-HINTS VALIDATION TEST\n";
echo "=============================\n\n";

class TypeHintValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $successes = [];
    
    public function validateController(string $controllerClass): void
    {
        echo "🧪 Testing $controllerClass...\n";
        
        try {
            $reflection = new ReflectionClass($controllerClass);
            $constructor = $reflection->getConstructor();
            
            if (!$constructor) {
                $this->successes[] = "$controllerClass: No constructor, OK";
                return;
            }
            
            $parameters = $constructor->getParameters();
            
            foreach ($parameters as $param) {
                $this->validateParameter($controllerClass, $param);
            }
            
            $this->successes[] = "$controllerClass: All type-hints valid";
            
        } catch (Exception $e) {
            $this->errors[] = "$controllerClass: Failed to analyze - " . $e->getMessage();
        }
    }
    
    private function validateParameter(string $controllerClass, ReflectionParameter $param): void
    {
        $paramName = $param->getName();
        $type = $param->getType();
        
        if (!$type) {
            $this->warnings[] = "$controllerClass::$paramName: No type-hint";
            return;
        }
        
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            
            // Check if type is built-in
            if ($type->isBuiltin()) {
                return; // Built-in types (string, int, etc.) are OK
            }
            
            // Check if class/interface exists
            if (!class_exists($typeName) && !interface_exists($typeName)) {
                $this->errors[] = "$controllerClass::$paramName: Type '$typeName' does not exist";
                return;
            }
            
            // Check for common namespace conflicts
            $this->checkNamespaceConflicts($controllerClass, $paramName, $typeName);
            
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                    $typeName = $unionType->getName();
                    if (!class_exists($typeName) && !interface_exists($typeName)) {
                        $this->errors[] = "$controllerClass::$paramName: Union type '$typeName' does not exist";
                    }
                }
            }
        }
    }
    
    private function checkNamespaceConflicts(string $controllerClass, string $paramName, string $typeName): void
    {
        // Check for common problematic patterns
        $problematicPatterns = [
            'Auth' => ['TopoclimbCH\\Core\\Auth', 'TopoclimbCH\\Controllers\\Auth'],
            'Database' => ['TopoclimbCH\\Core\\Database', 'TopoclimbCH\\Models\\Database'],
            'Session' => ['TopoclimbCH\\Core\\Session', 'TopoclimbCH\\Services\\Session'],
            'View' => ['TopoclimbCH\\Core\\View', 'TopoclimbCH\\Services\\View'],
        ];
        
        $shortTypeName = basename(str_replace('\\', '/', $typeName));
        
        if (isset($problematicPatterns[$shortTypeName])) {
            $expectedNamespaces = $problematicPatterns[$shortTypeName];
            
            if (!in_array($typeName, $expectedNamespaces)) {
                $this->warnings[] = "$controllerClass::$paramName: Unusual namespace for '$shortTypeName': $typeName";
            }
            
            // Check if the controller has the correct import
            try {
                $reflection = new ReflectionClass($controllerClass);
                $fileName = $reflection->getFileName();
                $fileContent = file_get_contents($fileName);
                
                if (!preg_match('/use\s+' . preg_quote($typeName, '/') . '\s*;/', $fileContent)) {
                    $this->errors[] = "$controllerClass::$paramName: Missing 'use $typeName;' import statement";
                }
            } catch (Exception $e) {
                $this->warnings[] = "$controllerClass::$paramName: Could not check import statements";
            }
        }
    }
    
    public function validateWithContainer(string $controllerClass): void
    {
        echo "🔧 Testing container instantiation for $controllerClass...\n";
        
        try {
            $containerBuilder = new ContainerBuilder();
            $container = $containerBuilder->build();
            
            // Try to instantiate the controller
            $controller = $container->get($controllerClass);
            $this->successes[] = "$controllerClass: Container instantiation successful";
            
        } catch (Exception $e) {
            $this->errors[] = "$controllerClass: Container instantiation failed - " . $e->getMessage();
        }
    }
    
    public function getResults(): array
    {
        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'successes' => $this->successes
        ];
    }
}

// Test all controllers
$validator = new TypeHintValidator();

$controllersToTest = [
    'TopoclimbCH\\Controllers\\HomeController',
    'TopoclimbCH\\Controllers\\ErrorController',
    'TopoclimbCH\\Controllers\\AuthController',
    'TopoclimbCH\\Controllers\\DifficultySystemController',
    'TopoclimbCH\\Controllers\\RegionController',
    'TopoclimbCH\\Controllers\\SectorController',
    'TopoclimbCH\\Controllers\\RouteController',
    'TopoclimbCH\\Controllers\\UserController',
    'TopoclimbCH\\Controllers\\AdminController',
    'TopoclimbCH\\Controllers\\MediaController',
];

echo "📋 PHASE 1: TYPE-HINT VALIDATION\n";
echo "=================================\n";

foreach ($controllersToTest as $controller) {
    if (class_exists($controller)) {
        $validator->validateController($controller);
    } else {
        echo "⚠️  $controller: Class does not exist\n";
    }
}

echo "\n🔧 PHASE 2: CONTAINER INSTANTIATION TEST\n";
echo "=========================================\n";

foreach ($controllersToTest as $controller) {
    if (class_exists($controller)) {
        $validator->validateWithContainer($controller);
    }
}

// Display results
$results = $validator->getResults();

echo "\n📊 RESULTS SUMMARY\n";
echo "==================\n";

echo "✅ SUCCESSES: " . count($results['successes']) . "\n";
foreach ($results['successes'] as $success) {
    echo "  ✅ $success\n";
}

if (!empty($results['warnings'])) {
    echo "\n⚠️  WARNINGS: " . count($results['warnings']) . "\n";
    foreach ($results['warnings'] as $warning) {
        echo "  ⚠️  $warning\n";
    }
}

if (!empty($results['errors'])) {
    echo "\n❌ ERRORS: " . count($results['errors']) . "\n";
    foreach ($results['errors'] as $error) {
        echo "  ❌ $error\n";
    }
}

echo "\n🎯 FINAL VERDICT\n";
echo "================\n";

if (empty($results['errors'])) {
    echo "✅ ALL TYPE-HINTS ARE VALID!\n";
    echo "🎉 No namespace conflicts detected\n";
    echo "🚀 Controllers should instantiate correctly\n";
} else {
    echo "❌ TYPE-HINT ERRORS DETECTED!\n";
    echo "🔧 Fix the errors above before deployment\n";
    echo "⚠️  Deployment may fail with current configuration\n";
}

echo "\n💡 RECOMMENDATIONS\n";
echo "==================\n";
echo "1. Always add 'use' statements for type-hinted classes\n";
echo "2. Use fully qualified names in type-hints when in doubt\n";
echo "3. Test with container instantiation before deployment\n";
echo "4. Run this script before each deployment\n";
echo "5. Consider using an IDE with namespace validation\n";

if (!empty($results['errors'])) {
    exit(1);
}