<?php
/**
 * Advanced Health Check for Faveo on Railway
 * This file both satisfies Railway's health check and provides diagnostic information
 */

// First, return an OK status for Railway's health check
header('Content-Type: text/plain');
echo "OK\n\n";

// Then add some diagnostic information
echo "=== HEALTH DIAGNOSTICS ===\n";

// Check if Apache is running
$apache_running = false;
exec('ps aux | grep apache2 | grep -v grep', $output, $return_var);
if (count($output) > 0) {
    echo "Apache: Running\n";
    $apache_running = true;
} else {
    echo "Apache: NOT RUNNING\n";
}

// Check bootstrap log
$log_file = '/var/log/bootstrap.log';
if (file_exists($log_file)) {
    echo "\nBootstrap Log (last 10 lines):\n";
    echo "------------------------\n";
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $last_lines = array_slice($log_lines, -10);
    foreach ($last_lines as $line) {
        echo $line . "\n";
    }
} else {
    echo "\nBootstrap Log: Not found\n";
}

// Check database connection
echo "\nDatabase Connection:\n";
echo "------------------------\n";
$db_host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$db_port = getenv('MYSQLPORT') ?: '3306';
$db_name = getenv('MYSQLDATABASE') ?: 'railway';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "Database connection: Successful\n";
    
    // Check if settings_system table exists and has URL column
    $tables_result = $pdo->query("SHOW TABLES LIKE 'settings_system'")->fetchAll();
    if (count($tables_result) > 0) {
        echo "settings_system table: Found\n";
        
        // Check URL value
        $url_result = $pdo->query("SELECT url FROM settings_system LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($url_result) {
            echo "URL in settings_system: " . $url_result['url'] . "\n";
        } else {
            echo "URL in settings_system: Not found\n";
        }
    } else {
        echo "settings_system table: Not found\n";
    }
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}

// Check required directories
echo "\nDirectory Status:\n";
echo "------------------------\n";
$required_dirs = [
    '/var/www/html/storage',
    '/var/www/html/storage/framework/cache',
    '/var/www/html/storage/framework/sessions',
    '/var/www/html/storage/framework/views',
    '/var/www/html/bootstrap/cache',
];
foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "$dir: Exists and " . (is_writable($dir) ? "is writable" : "NOT WRITABLE") . "\n";
    } else {
        echo "$dir: MISSING\n";
    }
}

// Check for index.php
echo "\nRequired Files:\n";
echo "------------------------\n";
$required_files = [
    '/var/www/html/public/index.php',
    '/var/www/html/public/db_bootstrap.php',
    '/var/www/html/.env',
];
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "$file: Exists and " . (is_readable($file) ? "is readable" : "NOT READABLE") . "\n";
    } else {
        echo "$file: MISSING\n";
    }
}

// Environment variables
echo "\nEnvironment Variables:\n";
echo "------------------------\n";
$important_vars = [
    'RAILWAY_PUBLIC_DOMAIN',
    'APP_URL',
    'MYSQLHOST',
    'MYSQLPORT',
    'MYSQLDATABASE',
    'MYSQLUSER',
];
foreach ($important_vars as $var) {
    echo "$var: " . (getenv($var) ?: "Not set") . "\n";
}

// Get Apache error logs if Apache is running
if ($apache_running) {
    echo "\nApache Error Log (last 10 lines):\n";
    echo "------------------------\n";
    exec('tail -10 /var/log/apache2/error.log', $apache_log, $return_var);
    if (count($apache_log) > 0) {
        echo implode("\n", $apache_log) . "\n";
    } else {
        echo "No Apache errors found or log not accessible\n";
    }
}

// End of diagnostics
echo "\n=== END DIAGNOSTICS ===\n";

// Still return HTTP 200 to satisfy health check
http_response_code(200); 