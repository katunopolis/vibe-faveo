<?php
// Direct MySQL connection test script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Direct MySQL Connection Test</h1>";

// Get Railway environment variables
$mysql_host = getenv('MYSQLHOST');
$mysql_port = getenv('MYSQLPORT');
$mysql_database = getenv('MYSQLDATABASE');
$mysql_user = getenv('MYSQLUSER');
$mysql_password = getenv('MYSQLPASSWORD');

echo "<h2>Environment Variables</h2>";
echo "<p>MYSQLHOST: " . ($mysql_host ?: "Not set") . "</p>";
echo "<p>MYSQLPORT: " . ($mysql_port ?: "Not set") . "</p>";
echo "<p>MYSQLDATABASE: " . ($mysql_database ?: "Not set") . "</p>";
echo "<p>MYSQLUSER: " . ($mysql_user ?: "Not set") . "</p>";
echo "<p>MYSQLPASSWORD: " . ($mysql_password ? "Set (hidden)" : "Not set") . "</p>";

// Check if we have a direct MySQL connection
echo "<h2>Connection Tests</h2>";

// 1. Try the standard host
echo "<h3>1. Testing mysql.railway.internal</h3>";
try_connection('mysql.railway.internal', $mysql_port, $mysql_database, $mysql_user, $mysql_password);

// 2. Try alternative Railway hostnames
$alt_hosts = ['mysql', 'db', 'database', 'mysql-service', 'mysql.internal', 'localhost', '127.0.0.1'];
echo "<h3>2. Testing alternative hostnames</h3>";
foreach ($alt_hosts as $host) {
    echo "<h4>Testing '$host'</h4>";
    try_connection($host, $mysql_port, $mysql_database, $mysql_user, $mysql_password);
}

// 3. Try socket connection
echo "<h3>3. Testing socket connection</h3>";
try {
    $dsn = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=" . ($mysql_database ?: 'railway');
    echo "<p>DSN: $dsn</p>";
    $conn = new PDO($dsn, $mysql_user, $mysql_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✓ Socket connection successful!</p>";
    
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . count($tables) . "</p>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Socket connection failed: " . $e->getMessage() . "</p>";
}

// 4. Ping MySQL servers on common ports
echo "<h3>4. Checking TCP ports</h3>";
$ports_to_check = [3306, 3307, 3308, 33060];
foreach ($ports_to_check as $port_to_check) {
    echo "<p>Checking port $port_to_check on localhost: ";
    $connection = @fsockopen('localhost', $port_to_check, $errno, $errstr, 2);
    if (is_resource($connection)) {
        echo "<span style='color:green'>Open</span></p>";
        fclose($connection);
    } else {
        echo "<span style='color:red'>Closed</span></p>";
    }
}

// 5. Show PHP MySQL extension info
echo "<h3>5. PHP MySQL Extensions</h3>";
echo "<p>PDO MySQL extension loaded: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "</p>";
echo "<p>MySQL extension loaded: " . (extension_loaded('mysqli') ? 'Yes' : 'No') . "</p>";

// Function to test a connection
function try_connection($host, $port, $database, $user, $password) {
    try {
        $dsn = "mysql:host=$host;port=" . ($port ?: '3306') . ";dbname=" . ($database ?: 'railway');
        echo "<p>DSN: $dsn</p>";
        $conn = new PDO($dsn, $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color:green'>✓ Connection successful!</p>";
        
        // If connection worked, show tables
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tables found: " . count($tables) . "</p>";
        if (count($tables) > 0) {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>✗ Connection failed: " . $e->getMessage() . "</p>";
    }
} 