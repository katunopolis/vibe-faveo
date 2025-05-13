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

echo "<p>Connection: $connection</p>";
echo "<p>Host: $host</p>";
echo "<p>Port: $port</p>";
echo "<p>Database: $database</p>";
echo "<p>Username: $username</p>";

try {
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
}
?>