<?php
// Disable error reporting for security in production
// error_reporting(0);
// ini_set('display_errors', 0);

// For debugging, we'll enable errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

// File system checks
echo "<h2>File System Checks</h2>";
$envPath = realpath(__DIR__ . "/../.env");
echo "<p>.env path: " . ($envPath ?: "Not found") . "</p>";
echo "<p>.env exists: " . (file_exists($envPath) ? "Yes" : "No") . "</p>";

if (file_exists($envPath)) {
    echo "<p>.env file permissions: " . substr(sprintf('%o', fileperms($envPath)), -4) . "</p>";
    echo "<p>.env file readable: " . (is_readable($envPath) ? "Yes" : "No") . "</p>";
    echo "<p>.env file owner: " . posix_getpwuid(fileowner($envPath))['name'] . "</p>";
    echo "<p>.env file size: " . filesize($envPath) . " bytes</p>";
    
    echo "<h3>First 10 lines of .env file:</h3>";
    echo "<pre>";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        // Mask password if present
        if (strpos($lines[$i], "PASSWORD") !== false) {
            $parts = explode('=', $lines[$i], 2);
            if (count($parts) > 1) {
                echo htmlspecialchars($parts[0] . "=***MASKED***") . "\n";
            } else {
                echo htmlspecialchars($lines[$i]) . "\n";
            }
        } else {
            echo htmlspecialchars($lines[$i]) . "\n";
        }
    }
    echo "</pre>";
}

// Get environment variables
$connection = getenv('DB_CONNECTION');
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$database = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

// Get Railway specific environment variables
$railway_env = getenv('RAILWAY_ENVIRONMENT');
$mysql_host = getenv('MYSQLHOST');
$mysql_port = getenv('MYSQLPORT');
$mysql_database = getenv('MYSQLDATABASE');
$mysql_user = getenv('MYSQLUSER');
$mysql_password = getenv('MYSQLPASSWORD');

echo "<h2>Environment Variables from .env</h2>";
echo "<p>Connection: $connection</p>";
echo "<p>Host: $host</p>";
echo "<p>Port: $port</p>";
echo "<p>Database: $database</p>";
echo "<p>Username: $username</p>";

echo "<h2>Railway Environment Variables</h2>";
echo "<p>RAILWAY_ENVIRONMENT: $railway_env</p>";
echo "<p>MYSQLHOST: $mysql_host</p>";
echo "<p>MYSQLPORT: $mysql_port</p>";
echo "<p>MYSQLDATABASE: $mysql_database</p>";
echo "<p>MYSQLUSER: $mysql_user</p>";
echo "<p>MYSQLPASSWORD: " . ($mysql_password ? "Set (hidden)" : "Not set") . "</p>";

try {
    echo "<h2>Connection Test</h2>";
    
    // Use direct variables if .env is empty
    if (empty($host) && !empty($mysql_host)) {
        $host = $mysql_host;
        $port = $mysql_port;
        $database = $mysql_database;
        $username = $mysql_user;
        $password = $mysql_password;
        echo "<p><strong>Using direct environment variables instead of .env</strong></p>";
    }
    
    $dsn = "mysql:host=$host;port=$port;dbname=$database";
    echo "<p>Attempting to connect to: $dsn</p>";
    
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2 style='color:green'>✓ Connected successfully to database!</h2>";
    
    // Test query to make sure we can read data
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables in database:</h3>";
    echo "<ul>";
    if (count($tables) > 0) {
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
    } else {
        echo "<li>No tables found (empty database)</li>";
    }
    echo "</ul>";
    
} catch(PDOException $e) {
    echo "<h2 style='color:red'>✗ Connection failed:</h2>";
    echo "<p style='color:red'>" . $e->getMessage() . "</p>";
    
    // Additional diagnostics
    echo "<h3>Hostname Resolution Test</h3>";
    echo "<pre>";
    $ip = gethostbyname($host);
    echo "Resolving $host: " . ($ip != $host ? "Success ($ip)" : "Failed (could not resolve)") . "\n";
    
    if (function_exists('dns_get_record')) {
        echo "\nDNS Records for $host:\n";
        print_r(@dns_get_record($host, DNS_A));
    }
    
    // Try alternative connection if using Railway
    if ($mysql_host) {
        echo "\n\nTrying direct Railway variables connection:\n";
        try {
            $alt_dsn = "mysql:host=$mysql_host;port=$mysql_port;dbname=$mysql_database";
            echo "DSN: $alt_dsn\n";
            $alt_conn = new PDO($alt_dsn, $mysql_user, $mysql_password);
            echo "SUCCESS: Direct connection using Railway variables worked!\n";
        } catch (PDOException $alt_e) {
            echo "FAILED: " . $alt_e->getMessage() . "\n";
        }
        
        // Try various common hostnames for Railway MySQL
        $hostnames = ['mysql', 'db', 'database', 'mysql-service', 'mysql.internal', 'localhost', '127.0.0.1'];
        echo "\n\nTrying common MySQL hostnames in Railway:\n";
        foreach ($hostnames as $test_host) {
            try {
                $test_dsn = "mysql:host=$test_host;port=$mysql_port;dbname=$mysql_database";
                echo "Testing host '$test_host': $test_dsn\n";
                $test_conn = new PDO($test_dsn, $mysql_user, $mysql_password);
                echo "SUCCESS: Connection worked with hostname '$test_host'!\n";
                echo "Use this hostname in your .env file\n";
                break;
            } catch (PDOException $test_e) {
                echo "FAILED for $test_host: " . $test_e->getMessage() . "\n";
            }
        }
    }
    
    // Try socket connection if host is localhost
    if ($host == 'localhost' || $host == '127.0.0.1') {
        echo "\n\nTrying socket connection:\n";
        try {
            $socket_dsn = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$database";
            echo "Socket DSN: $socket_dsn\n";
            $socket_conn = new PDO($socket_dsn, $username, $password);
            echo "SUCCESS: Socket connection worked!\n";
        } catch (PDOException $socket_e) {
            echo "FAILED: " . $socket_e->getMessage() . "\n";
        }
    }
    
    echo "</pre>";
    
    echo "<h3>MySQL Service Status Check</h3>";
    echo "<pre>";
    echo "This is a Railway deployment, so we can't directly check service status from PHP.\n";
    echo "Please check the Railway dashboard for the MySQL service status.\n";
    echo "</pre>";
}
?>