<?php
/**
 * Laravel Application Bootstrapper for Faveo
 * 
 * This file should be included at the beginning of index.php to properly
 * initialize the Laravel application environment.
 */

// Include database configuration
$SKIP_HEADER = true;
if (file_exists(__DIR__ . '/memory-only-fix.php')) {
    $db_config = include __DIR__ . '/memory-only-fix.php';
    
    if ($db_config['success']) {
        // Database configuration successful
        $connection = $db_config['connection'];
        
        // Set environment variables
        putenv('DB_CONNECTION=mysql');
        putenv("DB_HOST={$connection['host']}");
        putenv("DB_PORT={$connection['port']}");
        putenv("DB_DATABASE={$connection['database']}");
        putenv("DB_USERNAME={$connection['username']}");
        putenv("DB_PASSWORD={$connection['password']}");
    }
}

// Ensure APP_KEY is set
if (empty(getenv('APP_KEY'))) {
    putenv('APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=');
}

// Set other important environment variables
if (empty(getenv('APP_ENV'))) {
    putenv('APP_ENV=local');
}

if (empty(getenv('APP_DEBUG'))) {
    putenv('APP_DEBUG=true');
}

// Set storage paths
$storagePath = realpath(__DIR__ . '/../storage');
if (file_exists($storagePath) && is_writable($storagePath)) {
    // Make sure storage directories exist
    $storageSubdirs = [
        'app/public',
        'framework/cache',
        'framework/sessions',
        'framework/views',
        'logs'
    ];
    
    foreach ($storageSubdirs as $dir) {
        $fullPath = $storagePath . '/' . $dir;
        if (!file_exists($fullPath)) {
            @mkdir($fullPath, 0755, true);
        }
    }
}

// Clear potential cached configurations
$bootstrapPath = realpath(__DIR__ . '/../bootstrap');
if (file_exists($bootstrapPath) && is_writable($bootstrapPath)) {
    $cachePath = $bootstrapPath . '/cache';
    if (file_exists($cachePath)) {
        $cacheFiles = [
            'config.php',
            'routes.php',
            'services.php'
        ];
        
        foreach ($cacheFiles as $file) {
            $fullPath = $cachePath . '/' . $file;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
}

// Initialize Facade Application if it doesn't affect the main bootstrap process
if (!defined('FACADE_APPLIED') && class_exists('Illuminate\Support\Facades\Facade')) {
    // Initialize a minimal application to set as the facade root
    if (!isset($app) || !$app) {
        $appPath = realpath(__DIR__ . '/..');
        $app = new Illuminate\Foundation\Application($appPath);
        
        // Bind the essential services
        $app->singleton(
            Illuminate\Contracts\Http\Kernel::class,
            App\Http\Kernel::class
        );
        
        $app->singleton(
            Illuminate\Contracts\Console\Kernel::class,
            App\Console\Kernel::class
        );
        
        $app->singleton(
            Illuminate\Contracts\Debug\ExceptionHandler::class,
            App\Exceptions\Handler::class
        );
    }
    
    // Set the facade root
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    define('FACADE_APPLIED', true);
}

// Function to patch the index.php file to include this bootstrap
function patchIndexFile() {
    $indexPath = __DIR__ . '/index.php';
    
    if (!file_exists($indexPath) || !is_writable($indexPath)) {
        return false;
    }
    
    $indexContent = file_get_contents($indexPath);
    
    // Check if the bootstrap is already included
    if (strpos($indexContent, 'bootstrap-app.php') !== false) {
        return true;
    }
    
    // Add the include statement right after the opening PHP tag
    $newContent = preg_replace(
        '/<\?php/',
        "<?php\n// Bootstrap the Laravel application environment\nrequire __DIR__.'/bootstrap-app.php';\n",
        $indexContent,
        1
    );
    
    return file_put_contents($indexPath, $newContent) !== false;
}

// Automatically patch the index.php file if accessed directly
if (isset($_GET['patch']) && $_GET['patch'] === 'auto') {
    if (patchIndexFile()) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        echo "Failed to patch index.php. You may need to manually include bootstrap-app.php in your index.php.";
        exit;
    }
}

// Show a configuration page if accessed directly
if (php_sapi_name() !== 'cli' && !isset($SKIP_UI)) {
    echo "<html><head><title>Laravel Application Bootstrapper</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; max-width: 800px; margin: 0 auto; }
        h1, h2, h3 { color: #336699; }
        .box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { color: green; }
        .error { color: red; }
        code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
    </style>";
    echo "</head><body>";
    echo "<h1>Laravel Application Bootstrapper</h1>";
    echo "<div class='box'>";
    echo "<h2>Configuration Status</h2>";
    
    // Check database configuration
    if (isset($db_config) && $db_config['success']) {
        echo "<p class='success'>✓ Database configuration: Connected to {$connection['host']}:{$connection['port']} as {$connection['username']}</p>";
    } else {
        echo "<p class='error'>✗ Database configuration: Not configured or connection failed</p>";
    }
    
    // Check environment variables
    echo "<h3>Environment Variables</h3>";
    echo "<ul>";
    echo "<li>APP_ENV: " . getenv('APP_ENV') . "</li>";
    echo "<li>APP_DEBUG: " . getenv('APP_DEBUG') . "</li>";
    echo "<li>APP_KEY: " . (getenv('APP_KEY') ? 'Set' : 'Not set') . "</li>";
    echo "<li>DB_CONNECTION: " . getenv('DB_CONNECTION') . "</li>";
    echo "<li>DB_HOST: " . getenv('DB_HOST') . "</li>";
    echo "<li>DB_PORT: " . getenv('DB_PORT') . "</li>";
    echo "<li>DB_DATABASE: " . getenv('DB_DATABASE') . "</li>";
    echo "<li>DB_USERNAME: " . getenv('DB_USERNAME') . "</li>";
    echo "<li>DB_PASSWORD: " . (getenv('DB_PASSWORD') ? '****** (set)' : 'Not set') . "</li>";
    echo "</ul>";
    
    // Check storage paths
    echo "<h3>Storage Directories</h3>";
    if (file_exists($storagePath) && is_writable($storagePath)) {
        echo "<p class='success'>✓ Storage path exists and is writable: $storagePath</p>";
        foreach ($storageSubdirs as $dir) {
            $fullPath = $storagePath . '/' . $dir;
            if (file_exists($fullPath)) {
                echo "<p>✓ Directory exists: $dir</p>";
            } else {
                echo "<p class='error'>✗ Directory does not exist: $dir</p>";
            }
        }
    } else {
        echo "<p class='error'>✗ Storage path does not exist or is not writable: $storagePath</p>";
    }
    
    // Check if index.php is patched
    $indexPath = __DIR__ . '/index.php';
    $isPatched = file_exists($indexPath) && strpos(file_get_contents($indexPath), 'bootstrap-app.php') !== false;
    
    echo "<h3>Index File Patching</h3>";
    if ($isPatched) {
        echo "<p class='success'>✓ index.php is already patched to include bootstrap-app.php</p>";
    } else {
        echo "<p class='error'>✗ index.php is not patched yet</p>";
        echo "<p>You can <a href='?patch=auto'>patch it automatically</a> or add the following code at the beginning of index.php:</p>";
        echo "<pre><code>// Bootstrap the Laravel application environment
require __DIR__.'/bootstrap-app.php';</code></pre>";
    }
    
    echo "</div>";
    
    echo "<div class='box'>";
    echo "<h2>Next Steps</h2>";
    echo "<ol>";
    if (!$isPatched) {
        echo "<li>Patch index.php to include bootstrap-app.php</li>";
    }
    echo "<li><a href='/public'>Go to Faveo</a> after patching index.php</li>";
    echo "<li>If you still encounter issues, check the <a href='db-test.php'>database configuration</a></li>";
    echo "<li>For permission issues, use the <a href='fix-permissions.php'>permission fixer</a></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "</body></html>";
    exit;
}

// Return true to indicate successful bootstrapping
return true; 