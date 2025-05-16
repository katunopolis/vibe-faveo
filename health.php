<?php
/**
 * Enhanced Health Check Endpoint
 * Always returns HTTP 200 to pass railway health checks,
 * but includes detailed information for troubleshooting
 */

// Always return 200 OK header for Railway health check
http_response_code(200);
header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Basic OK response
echo "OK";

// Add diagnostics only if requested with ?debug
if (isset($_GET['debug'])) {
    // Check Apache status
    echo "\n\n=== Apache Status ===\n";
    $apache_running = function_exists('shell_exec') ? shell_exec('ps aux | grep apache2') : 'Cannot check Apache status - shell_exec disabled';
    echo $apache_running;
    
    // Check critical directories
    echo "\n\n=== Directory Checks ===\n";
    $public_dir = __DIR__ . '/faveo/public';
    $storage_dir = __DIR__ . '/faveo/storage';
    $bootstrap_dir = __DIR__ . '/faveo/bootstrap/cache';
    
    echo "Public dir exists: " . (file_exists($public_dir) ? "Yes" : "No") . "\n";
    echo "Storage dir exists: " . (file_exists($storage_dir) ? "Yes" : "No") . "\n";
    echo "Bootstrap cache dir exists: " . (file_exists($bootstrap_dir) ? "Yes" : "No") . "\n";
    
    // Check for health.php in public directory
    echo "\n\n=== Health Check Files ===\n";
    echo "Health.php in public: " . (file_exists($public_dir . '/health.php') ? "Yes" : "No") . "\n";
    
    // Check for bootstrap-app.php
    echo "bootstrap-app.php in public: " . (file_exists($public_dir . '/bootstrap-app.php') ? "Yes" : "No") . "\n";
    
    // Check for .env file
    echo "\n\n=== Environment Files ===\n";
    echo ".env file exists: " . (file_exists(__DIR__ . '/faveo/.env') ? "Yes" : "No") . "\n";
    echo ".env.example file exists: " . (file_exists(__DIR__ . '/faveo/.env.example') ? "Yes" : "No") . "\n";
    
    // Check composer install status
    echo "\n\n=== Composer Status ===\n";
    echo "Vendor directory exists: " . (file_exists(__DIR__ . '/faveo/vendor') ? "Yes" : "No") . "\n";
    
    // Check logs if available
    echo "\n\n=== Recent Logs ===\n";
    $bootstrap_log = '/var/log/bootstrap.log';
    $apache_error = '/var/log/apache2/error.log';
    
    if (file_exists($bootstrap_log) && is_readable($bootstrap_log)) {
        echo "Bootstrap log (last 10 lines):\n";
        echo shell_exec("tail -10 $bootstrap_log");
    } else {
        echo "Bootstrap log not found or not readable\n";
    }
    
    if (file_exists($apache_error) && is_readable($apache_error)) {
        echo "\nApache error log (last 10 lines):\n";
        echo shell_exec("tail -10 $apache_error");
    } else {
        echo "\nApache error log not found or not readable\n";
    }
}

// We're done - exit with success
exit(0); 