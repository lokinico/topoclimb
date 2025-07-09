<?php

// Pipeline de test automatisé pour TopoclimbCH
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

echo "🔄 AUTOMATED TESTING PIPELINE\n";
echo "============================\n\n";

class TestPipeline
{
    private array $tests = [];
    private array $results = [];
    private float $startTime;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    public function addTest(string $name, callable $test): void
    {
        $this->tests[$name] = $test;
    }
    
    public function run(): void
    {
        echo "🚀 Starting test pipeline...\n";
        echo "Tests to run: " . count($this->tests) . "\n\n";
        
        foreach ($this->tests as $name => $test) {
            echo "🧪 Running: $name\n";
            echo str_repeat('-', 50) . "\n";
            
            $testStartTime = microtime(true);
            
            try {
                ob_start();
                $result = $test();
                $output = ob_get_clean();
                
                $testEndTime = microtime(true);
                $testTime = ($testEndTime - $testStartTime) * 1000; // ms
                
                $this->results[$name] = [
                    'status' => $result ? 'PASS' : 'FAIL',
                    'output' => $output,
                    'time' => $testTime,
                    'error' => null
                ];
                
                echo $output;
                echo $result ? "✅ PASSED" : "❌ FAILED";
                echo " ({$testTime}ms)\n\n";
                
            } catch (Exception $e) {
                $testEndTime = microtime(true);
                $testTime = ($testEndTime - $testStartTime) * 1000; // ms
                
                $this->results[$name] = [
                    'status' => 'ERROR',
                    'output' => ob_get_clean(),
                    'time' => $testTime,
                    'error' => $e->getMessage()
                ];
                
                echo "💥 ERROR: " . $e->getMessage() . "\n";
                echo "({$testTime}ms)\n\n";
            }
        }
        
        $this->generateReport();
    }
    
    private function generateReport(): void
    {
        $endTime = microtime(true);
        $totalTime = ($endTime - $this->startTime) * 1000; // ms
        
        echo "📊 PIPELINE RESULTS\n";
        echo "==================\n";
        
        $passed = 0;
        $failed = 0;
        $errors = 0;
        
        foreach ($this->results as $name => $result) {
            $status = $result['status'];
            $time = round($result['time'], 2);
            
            if ($status === 'PASS') {
                echo "✅ $name ({$time}ms)\n";
                $passed++;
            } elseif ($status === 'FAIL') {
                echo "❌ $name ({$time}ms)\n";
                $failed++;
            } else {
                echo "💥 $name ({$time}ms) - " . $result['error'] . "\n";
                $errors++;
            }
        }
        
        echo "\n📈 SUMMARY\n";
        echo "==========\n";
        echo "✅ Passed: $passed\n";
        echo "❌ Failed: $failed\n";
        echo "💥 Errors: $errors\n";
        echo "⏱️  Total time: " . round($totalTime, 2) . "ms\n";
        echo "🏆 Success rate: " . round(($passed / count($this->tests)) * 100, 1) . "%\n";
        
        // Generate detailed report
        $this->generateDetailedReport();
        
        // Exit with appropriate code
        if ($failed > 0 || $errors > 0) {
            echo "\n❌ PIPELINE FAILED!\n";
            exit(1);
        } else {
            echo "\n✅ PIPELINE PASSED!\n";
            exit(0);
        }
    }
    
    private function generateDetailedReport(): void
    {
        $reportFile = BASE_PATH . '/test_report.json';
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_tests' => count($this->tests),
            'total_time' => round((microtime(true) - $this->startTime) * 1000, 2),
            'results' => $this->results
        ];
        
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        echo "\n📄 Detailed report saved to: $reportFile\n";
    }
}

// Create pipeline
$pipeline = new TestPipeline();

// Add tests
$pipeline->addTest('Environment Check', function() {
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            echo "❌ Extension $ext missing\n";
            return false;
        }
    }
    echo "✅ All required extensions loaded\n";
    return true;
});

$pipeline->addTest('File Structure', function() {
    $requiredFiles = [
        'src/Core/ContainerBuilder.php',
        'src/Controllers/HomeController.php',
        'src/Controllers/ErrorController.php',
        'config/routes.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            echo "❌ Missing file: $file\n";
            return false;
        }
    }
    echo "✅ All required files present\n";
    return true;
});

$pipeline->addTest('Container Build', function() {
    use TopoclimbCH\Core\ContainerBuilder;
    
    try {
        $containerBuilder = new ContainerBuilder();
        $container = $containerBuilder->build();
        echo "✅ Container built successfully\n";
        return true;
    } catch (Exception $e) {
        echo "❌ Container build failed: " . $e->getMessage() . "\n";
        return false;
    }
});

$pipeline->addTest('Type-Hints Validation', function() {
    use TopoclimbCH\Core\ContainerBuilder;
    
    $controllers = [
        'TopoclimbCH\\Controllers\\HomeController',
        'TopoclimbCH\\Controllers\\ErrorController',
        'TopoclimbCH\\Controllers\\AuthController'
    ];
    
    try {
        $containerBuilder = new ContainerBuilder();
        $container = $containerBuilder->build();
        
        foreach ($controllers as $controller) {
            $instance = $container->get($controller);
            echo "✅ $controller instantiated\n";
        }
        return true;
    } catch (Exception $e) {
        echo "❌ Controller instantiation failed: " . $e->getMessage() . "\n";
        return false;
    }
});

$pipeline->addTest('Database Connection', function() {
    use TopoclimbCH\Core\ContainerBuilder;
    
    try {
        $containerBuilder = new ContainerBuilder();
        $container = $containerBuilder->build();
        $db = $container->get('TopoclimbCH\\Core\\Database');
        
        $connection = $db->getConnection();
        $result = $db->query("SELECT 1 as test")->fetch();
        
        if ($result && $result['test'] == 1) {
            echo "✅ Database connection working\n";
            return true;
        } else {
            echo "❌ Database query failed\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "\n";
        return false;
    }
});

$pipeline->addTest('HomeController Specific', function() {
    use TopoclimbCH\Core\ContainerBuilder;
    
    try {
        $containerBuilder = new ContainerBuilder();
        $container = $containerBuilder->build();
        $homeController = $container->get('TopoclimbCH\\Controllers\\HomeController');
        
        // Check if all dependencies are properly injected
        $reflection = new ReflectionClass($homeController);
        
        $regionServiceProp = $reflection->getProperty('regionService');
        $regionServiceProp->setAccessible(true);
        $regionService = $regionServiceProp->getValue($homeController);
        
        if ($regionService === null) {
            echo "❌ RegionService not injected\n";
            return false;
        }
        
        echo "✅ HomeController fully functional\n";
        return true;
    } catch (Exception $e) {
        echo "❌ HomeController test failed: " . $e->getMessage() . "\n";
        return false;
    }
});

$pipeline->addTest('Memory Usage', function() {
    $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
    
    if ($memoryUsage < 100) {
        echo "✅ Memory usage: {$memoryUsage}MB (good)\n";
        return true;
    } else {
        echo "⚠️  Memory usage: {$memoryUsage}MB (high)\n";
        return true; // Warning, but not failure
    }
});

$pipeline->addTest('Cache Directories', function() {
    $dirs = ['cache/container', 'cache/routes', 'logs'];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (!is_writable($dir)) {
            echo "❌ Directory not writable: $dir\n";
            return false;
        }
    }
    
    echo "✅ All cache directories writable\n";
    return true;
});

// Run the pipeline
$pipeline->run();