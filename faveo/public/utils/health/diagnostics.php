<?php
/**
 * Comprehensive Diagnostics Script for Faveo
 * Combines functionality from multiple diagnostic scripts
 */

// Set content type for better readability
header('Content-Type: text/plain');

/**
 * Basic System Information
 */
function showSystemInfo() {
    echo "===== SYSTEM INFORMATION =====\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
    echo "Operating System: " . PHP_OS . "\n";
    echo "Hostname: " . gethostname() . "\n";
    echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

    // Get memory usage
    $memUsage = memory_get_usage(true);
    $memPeak = memory_get_peak_usage(true);
    echo "Memory Usage: " . round($memUsage / 1024 / 1024, 2) . " MB\n";
    echo "Peak Memory: " . round($memPeak / 1024 / 1024, 2) . " MB\n\n";
    
    // Process information using ps
    echo "===== PROCESS INFORMATION =====\n";
    $processCommand = 'ps aux | grep -E "apache|php|mysql" | grep -v grep';
    passthru($processCommand);
    echo "\n";
}

/**
 * Environment and Configuration Check
 */
function checkEnvironment() {
    echo "===== ENVIRONMENT VARIABLES =====\n";
    $importantVars = [
        'APP_URL', 'APP_ENV', 'APP_DEBUG', 'DB_CONNECTION', 'DB_HOST', 
        'DB_PORT', 'DB_DATABASE', 'RAILWAY_PUBLIC_DOMAIN', 'RAILWAY_ENVIRONMENT'
    ];
    
    foreach ($importantVars as $var) {
        echo "$var: " . (getenv($var) ? getenv($var) : 'Not set') . "\n";
    }
    
    echo "\n===== CONFIGURATION =====\n";
    echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
    echo "PHP INI Location: " . php_ini_loaded_file() . "\n";
    
    echo "\n===== DIRECTORY PERMISSIONS =====\n";
    $dirs = [
        '/var/www/html', 
        '/var/www/html/public', 
        '/var/www/html/storage',
        '/var/www/html/storage/framework/cache',
        '/var/www/html/storage/framework/sessions',
        '/var/www/html/storage/framework/views',
        '/var/www/html/bootstrap/cache'
    ];
    
    foreach ($dirs as $dir) {
        if (file_exists($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            $owner = posix_getpwuid(fileowner($dir))['name'] ?? 'unknown';
            $group = posix_getgrgid(filegroup($dir))['name'] ?? 'unknown';
            echo "$dir: exists (permissions: $perms, owner: $owner, group: $group)\n";
        } else {
            echo "$dir: DOES NOT EXIST\n";
        }
    }
    echo "\n";
}

/**
 * Database Connection Test
 */
function checkDatabase() {
    echo "===== DATABASE CONNECTION =====\n";
    $dbConnection = getenv('DB_CONNECTION') ?: 'mysql';
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_DATABASE') ?: 'forge';
    $dbUser = getenv('DB_USERNAME') ?: 'forge';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    
    echo "Testing connection to: $dbConnection://$dbHost:$dbPort/$dbName\n";
    
    try {
        $dsn = "$dbConnection:host=$dbHost;port=$dbPort;dbname=$dbName";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "Connection: SUCCESS\n";
        
        // Test query
        $stmt = $pdo->query("SHOW TABLES");
        echo "Tables found: " . $stmt->rowCount() . "\n";
        
        if ($stmt->rowCount() > 0) {
            echo "Tables:\n";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "- " . reset($row) . "\n";
            }
        }
    } catch (PDOException $e) {
        echo "Connection: FAILED\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

/**
 * Laravel Application Check
 */
function checkLaravel() {
    echo "===== LARAVEL APPLICATION =====\n";
    
    // Check .env file
    $envFile = '/var/www/html/.env';
    echo ".env file: " . (file_exists($envFile) ? "EXISTS" : "MISSING") . "\n";
    
    // Check critical files
    $criticalFiles = [
        '/var/www/html/public/index.php',
        '/var/www/html/artisan',
        '/var/www/html/bootstrap/app.php',
        '/var/www/html/config/app.php'
    ];
    
    foreach ($criticalFiles as $file) {
        echo "$file: " . (file_exists($file) ? "EXISTS" : "MISSING") . "\n";
    }
    
    // Check bootstrap cache
    $bootstrapCache = glob('/var/www/html/bootstrap/cache/*.php');
    echo "Bootstrap cache files: " . count($bootstrapCache) . "\n";
    
    echo "\n";
}

/**
 * Apache Configuration Check
 */
function checkApache() {
    echo "===== APACHE CONFIGURATION =====\n";
    
    // Check if Apache is running
    exec('ps aux | grep apache2 | grep -v grep', $output, $return_var);
    echo "Apache status: " . (count($output) > 0 ? "Running" : "NOT RUNNING") . "\n";
    
    // Check Apache configuration files
    $configFiles = [
        '/etc/apache2/sites-available/000-default.conf',
        '/etc/apache2/apache2.conf',
        '/etc/apache2/ports.conf'
    ];
    
    foreach ($configFiles as $file) {
        echo "$file: " . (file_exists($file) ? "EXISTS" : "MISSING") . "\n";
    }
    
    // Check enabled modules
    exec('apache2ctl -M 2>/dev/null', $modules, $return_var);
    if ($return_var === 0) {
        echo "Enabled Apache modules:\n";
        foreach ($modules as $module) {
            if (strpos($module, 'Loaded Modules') === false && trim($module) !== '') {
                echo "- " . trim($module) . "\n";
            }
        }
    } else {
        echo "Could not fetch Apache modules\n";
    }
    
    echo "\n";
}

/**
 * Log Files Check
 */
function checkLogFiles() {
    echo "===== LOG FILES =====\n";
    
    $logFiles = [
        '/var/log/apache2/error.log',
        '/var/log/apache2/access.log',
        '/var/log/bootstrap.log',
        '/var/www/html/storage/logs/laravel.log'
    ];
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            $size = round(filesize($logFile) / 1024, 2);
            echo "$logFile: EXISTS ($size KB)\n";
            
            // Show last few lines of log files
            echo "Last 5 lines:\n";
            passthru("tail -n 5 $logFile 2>&1");
            echo "\n";
        } else {
            echo "$logFile: DOES NOT EXIST\n";
        }
    }
}

// Run all diagnostics
try {
    echo "===============================\n";
    echo "FAVEO DIAGNOSTIC REPORT\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "===============================\n\n";
    
    showSystemInfo();
    checkEnvironment();
    checkDatabase();
    checkLaravel();
    checkApache();
    checkLogFiles();
    
    echo "===============================\n";
    echo "END OF DIAGNOSTIC REPORT\n";
    echo "===============================\n";
} catch (Exception $e) {
    echo "Error during diagnostics: " . $e->getMessage() . "\n";
} 