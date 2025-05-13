<?php
/**
 * Facade Root Fix Script
 * 
 * This script fixes the "facade root has not been set" error by 
 * initializing the Laravel application and setting it as the facade root.
 * 
 * Include this script at the beginning of index.php or any entry point
 * that needs to use Laravel facades.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// This prevents infinite recursion if index.php includes this file
if (defined('FACADE_ROOT_FIXED')) {
    return true;
}

define('FACADE_ROOT_FIXED', true);

// Require autoloader if it's not already loaded
if (!class_exists('Illuminate\Foundation\Application')) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        throw new Exception("Autoloader not found. Cannot initialize Laravel application.");
    }
}

// Create a minimal application
$appBasePath = realpath(__DIR__ . '/..');
$app = new Illuminate\Foundation\Application($appBasePath);

// Setup essential bindings
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

// Set the application as the facade root
if (class_exists('Illuminate\Support\Facades\Facade')) {
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
}

// Initialize essential facades
if (class_exists('Illuminate\Support\Facades\App')) {
    Illuminate\Support\Facades\App::setFacadeApplication($app);
}

if (class_exists('Illuminate\Support\Facades\Config')) {
    try {
        // Attempt to load configuration to make Config facade work
        $app->make('config');
    } catch (Exception $e) {
        // Ignore errors during config loading
    }
}

// If the script is accessed directly, show info page
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    echo "<html><head><title>Facade Root Fix</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #336699; }
        .box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { color: green; }
        code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
    </style>";
    echo "</head><body><div class='container'>";
    echo "<h1>Facade Root Fix</h1>";
    echo "<div class='box'>";
    echo "<p class='success'>✓ Facade root has been set successfully!</p>";
    echo "<p>To use this fix, add the following code at the beginning of your index.php file:</p>";
    echo "<pre><code>require_once __DIR__ . '/facade-fix.php';</code></pre>";
    echo "<p>This should resolve the 'A facade root has not been set' error.</p>";
    echo "<p><a href='/public'>Return to Faveo</a></p>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

// Return the application
return $app; 