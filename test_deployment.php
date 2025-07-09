<?php

// Test de validation de déploiement - à exécuter avant chaque déploiement
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

echo "🚀 DEPLOYMENT VALIDATION TEST\n";
echo "=============================\n\n";

class DeploymentValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $successes = [];
    private float $startTime;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    public function validateEnvironment(): void
    {
        echo "🌍 PHASE 1: Environment Validation\n";
        echo "===================================\n";
        
        // Check PHP version
        $requiredPhpVersion = '8.1';
        if (version_compare(PHP_VERSION, $requiredPhpVersion, '>=')) {
            $this->successes[] = "PHP version: " . PHP_VERSION . " (>= $requiredPhpVersion)";
        } else {
            $this->errors[] = "PHP version: " . PHP_VERSION . " (< $requiredPhpVersion required)";
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $this->successes[] = "Extension $ext: loaded";
            } else {
                $this->errors[] = "Extension $ext: missing";
            }
        }
        
        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->convertToBytes($memoryLimit);
        if ($memoryBytes >= 256 * 1024 * 1024) { // 256MB
            $this->successes[] = "Memory limit: $memoryLimit (sufficient)";
        } else {
            $this->warnings[] = "Memory limit: $memoryLimit (may be insufficient)";
        }
        
        // Check composer
        if (file_exists('vendor/autoload.php')) {
            $this->successes[] = "Composer: vendor/autoload.php found";
        } else {
            $this->errors[] = "Composer: vendor/autoload.php missing (run composer install)";
        }
    }
    
    public function validateFileStructure(): void
    {
        echo "\n📁 PHASE 2: File Structure Validation\n";
        echo "=====================================\n";
        
        $requiredFiles = [
            'public/index.php',
            'src/Core/ContainerBuilder.php',
            'src/Core/Database.php',
            'src/Core/Auth.php',
            'src/Controllers/HomeController.php',
            'src/Controllers/ErrorController.php',
            'config/routes.php',
            'composer.json'
        ];
        
        foreach ($requiredFiles as $file) {
            if (file_exists($file)) {
                $this->successes[] = "File exists: $file";
            } else {
                $this->errors[] = "File missing: $file";
            }
        }
        
        // Check directories
        $requiredDirectories = [
            'cache/container',
            'cache/routes',
            'logs',
            'resources/views',
            'src/Controllers',
            'src/Services'
        ];
        
        foreach ($requiredDirectories as $dir) {
            if (is_dir($dir)) {
                if (is_writable($dir)) {
                    $this->successes[] = "Directory writable: $dir";
                } else {
                    $this->errors[] = "Directory not writable: $dir";
                }
            } else {
                $this->warnings[] = "Directory missing: $dir (will be created)";
                mkdir($dir, 0755, true);
            }
        }
    }
    
    public function validateContainer(): void
    {
        echo "\n🔧 PHASE 3: Container Validation\n";
        echo "================================\n";
        
        try {
            $containerBuilder = new ContainerBuilder();
            $container = $containerBuilder->build();
            $this->successes[] = "Container: built successfully";
            
            // Test core services
            $coreServices = [
                'TopoclimbCH\\Core\\Database',
                'TopoclimbCH\\Core\\Auth',
                'TopoclimbCH\\Core\\Session',
                'TopoclimbCH\\Core\\View',
                'TopoclimbCH\\Core\\Security\\CsrfManager',
                'Psr\\Log\\LoggerInterface'
            ];
            
            foreach ($coreServices as $service) {
                try {
                    $container->get($service);
                    $this->successes[] = "Service available: $service";
                } catch (Exception $e) {
                    $this->errors[] = "Service failed: $service - " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Container build failed: " . $e->getMessage();
        }
    }
    
    public function validateControllers(): void
    {
        echo "\n🎮 PHASE 4: Controllers Validation\n";
        echo "==================================\n";
        
        try {
            $containerBuilder = new ContainerBuilder();
            $container = $containerBuilder->build();
            
            $controllers = [
                'TopoclimbCH\\Controllers\\HomeController',
                'TopoclimbCH\\Controllers\\ErrorController',
                'TopoclimbCH\\Controllers\\AuthController',
            ];
            
            foreach ($controllers as $controllerClass) {
                try {
                    $controller = $container->get($controllerClass);
                    $this->successes[] = "Controller instantiated: $controllerClass";
                    
                    // Test specific method for critical controllers
                    if ($controllerClass === 'TopoclimbCH\\Controllers\\HomeController') {
                        $reflection = new ReflectionClass($controller);
                        if ($reflection->hasMethod('index')) {
                            $this->successes[] = "HomeController->index() method exists";
                        }
                    }
                    
                } catch (Exception $e) {
                    $this->errors[] = "Controller failed: $controllerClass - " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Controllers validation failed: " . $e->getMessage();
        }
    }
    
    public function validateTypeHints(): void
    {
        echo "\n🔍 PHASE 5: Type-Hints Validation\n";
        echo "=================================\n";
        
        $controllerFiles = glob('src/Controllers/*.php');
        
        foreach ($controllerFiles as $file) {
            $className = 'TopoclimbCH\\Controllers\\' . basename($file, '.php');
            
            if (!class_exists($className)) {
                continue;
            }
            
            try {
                $reflection = new ReflectionClass($className);
                $constructor = $reflection->getConstructor();
                
                if (!$constructor) {
                    continue;
                }
                
                $fileContent = file_get_contents($file);
                $usedClasses = [];
                
                // Extract use statements
                preg_match_all('/use\s+([^;]+);/', $fileContent, $matches);
                foreach ($matches[1] as $use) {
                    $usedClasses[] = trim($use);
                }
                
                // Check constructor parameters
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $typeName = $type->getName();
                        
                        // Check if type is properly imported
                        $shortName = basename(str_replace('\\', '/', $typeName));
                        $hasImport = false;
                        
                        foreach ($usedClasses as $import) {
                            if ($import === $typeName || basename(str_replace('\\', '/', $import)) === $shortName) {
                                $hasImport = true;
                                break;
                            }
                        }
                        
                        if ($hasImport) {
                            $this->successes[] = "$className: Type-hint $shortName properly imported";
                        } else {
                            $this->errors[] = "$className: Type-hint $typeName missing import statement";
                        }
                    }
                }
                
            } catch (Exception $e) {
                $this->errors[] = "$className: Type-hint validation failed - " . $e->getMessage();
            }
        }
    }
    
    public function validateDatabase(): void
    {
        echo "\n💾 PHASE 6: Database Validation\n";
        echo "===============================\n";
        
        try {
            $containerBuilder = new ContainerBuilder();
            $container = $containerBuilder->build();
            $db = $container->get('TopoclimbCH\\Core\\Database');
            
            // Test database connection
            $connection = $db->getConnection();
            $this->successes[] = "Database: connection established";
            
            // Test simple query
            $result = $db->query("SELECT 1 as test")->fetch();
            if ($result && $result['test'] == 1) {
                $this->successes[] = "Database: query execution working";
            } else {
                $this->errors[] = "Database: query execution failed";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Database: connection failed - " . $e->getMessage();
        }
    }
    
    public function validatePerformance(): void
    {
        echo "\n⚡ PHASE 7: Performance Validation\n";
        echo "=================================\n";
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $this->startTime) * 1000; // ms
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        
        if ($executionTime < 5000) { // 5 seconds
            $this->successes[] = "Performance: Execution time {$executionTime}ms (good)";
        } else {
            $this->warnings[] = "Performance: Execution time {$executionTime}ms (slow)";
        }
        
        if ($memoryUsage < 100) { // 100MB
            $this->successes[] = "Performance: Memory usage {$memoryUsage}MB (good)";
        } else {
            $this->warnings[] = "Performance: Memory usage {$memoryUsage}MB (high)";
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
    
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}

// Run validation
$validator = new DeploymentValidator();

$validator->validateEnvironment();
$validator->validateFileStructure();
$validator->validateContainer();
$validator->validateControllers();
$validator->validateTypeHints();
$validator->validateDatabase();
$validator->validatePerformance();

// Display results
$results = $validator->getResults();

echo "\n📊 DEPLOYMENT VALIDATION RESULTS\n";
echo "=================================\n";

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

echo "\n🎯 DEPLOYMENT VERDICT\n";
echo "====================\n";

if (empty($results['errors'])) {
    echo "✅ DEPLOYMENT READY!\n";
    echo "🎉 All critical checks passed\n";
    echo "🚀 Safe to deploy to production\n";
    
    if (!empty($results['warnings'])) {
        echo "⚠️  Some warnings detected, review recommended\n";
    }
} else {
    echo "❌ DEPLOYMENT NOT READY!\n";
    echo "🔧 Fix the errors above before deployment\n";
    echo "⚠️  Deployment will likely fail\n";
}

echo "\n💡 DEPLOYMENT CHECKLIST\n";
echo "=======================\n";
echo "□ All type-hints have proper imports\n";
echo "□ Container can build successfully\n";
echo "□ All controllers can be instantiated\n";
echo "□ Database connection works\n";
echo "□ File permissions are correct\n";
echo "□ Cache directories are writable\n";
echo "□ Environment variables are set\n";

if (!empty($results['errors'])) {
    exit(1);
}