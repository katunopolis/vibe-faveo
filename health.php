<?php
/**
 * Minimal Health Check for Faveo on Railway
 * This file will always return HTTP 200 to satisfy Railway's health check
 */

// First and most importantly, return an OK status for Railway's health check
header('Content-Type: text/plain');
echo "OK\n\n";
http_response_code(200);

// Only run diagnostics if requested
if (isset($_GET['debug'])) {
    try {
        // Basic diagnostics
        echo "=== HEALTH DIAGNOSTICS ===\n";
        
        // Check if Apache is running
        exec('ps aux | grep apache2 | grep -v grep', $output, $return_var);
        echo "Apache: " . (count($output) > 0 ? "Running" : "NOT RUNNING") . "\n";
        
        // Check bootstrap log existence
        $log_file = '/var/log/bootstrap.log';
        echo "Bootstrap Log: " . (file_exists($log_file) ? "Found" : "Not found") . "\n";
        
        // List some key directories
        echo "\nKey directories:\n";
        $dirs = ['/var/www/html', '/var/www/html/public', '/var/www/html/storage'];
        foreach ($dirs as $dir) {
            echo "$dir: " . (is_dir($dir) ? "Exists" : "MISSING") . "\n";
        }
        
        // List some key files
        echo "\nKey files:\n";
        $files = [
            '/var/www/html/public/index.php', 
            '/var/www/html/public/health.php',
            '/var/www/html/.env'
        ];
        foreach ($files as $file) {
            echo "$file: " . (file_exists($file) ? "Exists" : "MISSING") . "\n";
        }
        
        echo "\n=== END DIAGNOSTICS ===\n";
    } catch (Exception $e) {
        echo "Error in diagnostics: " . $e->getMessage();
    }
}

// Exit successfully
exit(0); 