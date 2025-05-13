<?php
// Disable error reporting for security in production
// error_reporting(0);
// ini_set('display_errors', 0);

// For debugging, we'll enable errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

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
        print_r(dns_get_record($host, DNS_A));
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
    }
    
    echo "</pre>";
}
?>