<?php
/**
 * This file creates a proper Laravel application instance and sets it as the facade root.
 * Include this at the beginning of your index.php file to prevent "facade root has not been set" errors.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define common paths
$laravel_root = realpath(__DIR__ . "/..");
$bootstrap_file = $laravel_root . "/bootstrap/app.php";
$storage_dir = $laravel_root . "/storage";
$cache_dir = $laravel_root . "/bootstrap/cache";

// Create critical directories if they don't exist
if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0755, true);
}

$storage_subdirs = [
    "/framework/cache/data",
    "/framework/sessions",
    "/framework/views",
    "/logs",
    "/app/public"
];

foreach ($storage_subdirs as $subdir) {
    $dir = $storage_dir . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Attempt to create Laravel application instance
try {
    // Check if bootstrap file exists
    if (file_exists($bootstrap_file)) {
        // Load bootstrap file
        $app = require_once $bootstrap_file;
        
        // Manually set the facade root
        if (class_exists("Illuminate\\Support\\Facades\\Facade")) {
            Illuminate\\Support\\Facades\\Facade::setFacadeApplication($app);
            
            // Initialize key facades
            if (class_exists("Illuminate\\Support\\Facades\\App")) {
                Illuminate\\Support\\Facades\\App::getFacadeRoot();
            }
            
            if (class_exists("Illuminate\\Support\\Facades\\Config")) {
                Illuminate\\Support\\Facades\\Config::getFacadeRoot();
            }
        }
    }
} catch (Exception $e) {
    // Don't throw errors here as we're trying to fix errors
    // Just continue with normal bootstrap process
}

// Setup environment variables for database connection using already defined connections
$database_host = getenv("DB_HOST") ?: "localhost";
$database_port = getenv("DB_PORT") ?: "3306";
$database_name = getenv("DB_DATABASE") ?: "faveo";
$database_user = getenv("DB_USERNAME") ?: "root";
$database_pass = getenv("DB_PASSWORD") ?: "";

// Set DB environment variables
$_ENV["DB_CONNECTION"] = "mysql";
$_ENV["DB_HOST"] = $database_host;
$_ENV["DB_PORT"] = $database_port;
$_ENV["DB_DATABASE"] = $database_name;
$_ENV["DB_USERNAME"] = $database_user;
$_ENV["DB_PASSWORD"] = $database_pass;

putenv("DB_CONNECTION=mysql");
putenv("DB_HOST=" . $database_host);
putenv("DB_PORT=" . $database_port);
putenv("DB_DATABASE=" . $database_name);
putenv("DB_USERNAME=" . $database_user);
putenv("DB_PASSWORD=" . $database_pass);

// Prevent further errors by cleaning cached configuration
$cache_files = glob($cache_dir . "/*.php");
foreach ($cache_files as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}

// Set correct URL to avoid redirection issues
$app_url = getenv("APP_URL") ?: "https://vibe-faveo-production.up.railway.app";
$_ENV["APP_URL"] = $app_url;
putenv("APP_URL=" . $app_url);

// Return true to indicate inclusion was successful
return true; 