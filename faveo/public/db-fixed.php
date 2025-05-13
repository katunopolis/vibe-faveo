<?php
/**
 * Simple Database Configuration Helper for Faveo
 * 
 * This is a simplified version that works with older PHP versions.
 * It establishes a database connection and creates necessary configuration files.
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Show a nice header if accessed directly
if (php_sapi_name() !== 'cli' && !isset($SKIP_HEADER)) {
    echo "<html><head><title>Faveo Database Fix</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        pre { background: #f5f5f5; padding: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>";
    echo "</head><body>";
    echo "<h1>Faveo Database Connection Fix</h1>";
}

// Function to test a database connection
function testConnection($host, $port, $database, $username, $password) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$database";
        $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return array(
            'success' => true,
            'pdo' => $pdo,
            'message' => "Connected successfully to $host:$port"
        );
    } catch (PDOException $e) {
        return array(
            'success' => false,
            'pdo' => null,
            'message' => $e->getMessage()
        );
    }
}

// Try multiple connection methods
function findWorkingConnection() {
    $connections = array();
    
    // 1. Try environment variables
    if (getenv('DB_HOST') && getenv('DB_DATABASE') && getenv('DB_USERNAME')) {
        $connections[] = array(
            'method' => 'Environment Variables',
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT') ? getenv('DB_PORT') : '3306',
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD') ? getenv('DB_PASSWORD') : ''
        );
    }
    
    // 2. Try Railway environment variables
    if (getenv('MYSQLHOST') && getenv('MYSQLDATABASE') && getenv('MYSQLUSER')) {
        $connections[] = array(
            'method' => 'Railway Variables',
            'host' => getenv('MYSQLHOST'),
            'port' => getenv('MYSQLPORT') ? getenv('MYSQLPORT') : '3306',
            'database' => getenv('MYSQLDATABASE'),
            'username' => getenv('MYSQLUSER'),
            'password' => getenv('MYSQLPASSWORD') ? getenv('MYSQLPASSWORD') : ''
        );
    }
    
    // 3. Try DATABASE_URL
    $database_url = getenv('DATABASE_URL');
    if ($database_url && strpos($database_url, '${{') === false) {
        $parsed = parse_url($database_url);
        if ($parsed) {
            $connections[] = array(
                'method' => 'DATABASE_URL',
                'host' => isset($parsed['host']) ? $parsed['host'] : 'localhost',
                'port' => isset($parsed['port']) ? $parsed['port'] : '3306',
                'database' => ltrim(isset($parsed['path']) ? $parsed['path'] : '', '/'),
                'username' => isset($parsed['user']) ? $parsed['user'] : 'root',
                'password' => isset($parsed['pass']) ? $parsed['pass'] : ''
            );
        }
    }
    
    // 4. Try Railway internal hostname
    $connections[] = array(
        'method' => 'Railway Internal',
        'host' => 'mysql.railway.internal',
        'port' => '3306',
        'database' => 'railway',
        'username' => 'root',
        'password' => getenv('MYSQLPASSWORD') ? getenv('MYSQLPASSWORD') : ''
    );
    
    // 5. Try .env file if it exists
    $env_path = realpath(__DIR__ . '/../.env');
    if (file_exists($env_path)) {
        $env_content = file_get_contents($env_path);
        $db_host = '';
        $db_port = '3306';
        $db_database = '';
        $db_username = '';
        $db_password = '';
        
        if (preg_match('/DB_HOST=([^\n]+)/', $env_content, $matches)) {
            $db_host = trim($matches[1]);
        }
        if (preg_match('/DB_PORT=([^\n]+)/', $env_content, $matches)) {
            $db_port = trim($matches[1]);
        }
        if (preg_match('/DB_DATABASE=([^\n]+)/', $env_content, $matches)) {
            $db_database = trim($matches[1]);
        }
        if (preg_match('/DB_USERNAME=([^\n]+)/', $env_content, $matches)) {
            $db_username = trim($matches[1]);
        }
        if (preg_match('/DB_PASSWORD=([^\n]+)/', $env_content, $matches)) {
            $db_password = trim($matches[1]);
        }
        
        if ($db_host && $db_database && $db_username) {
            $connections[] = array(
                'method' => '.env File',
                'host' => $db_host,
                'port' => $db_port,
                'database' => $db_database,
                'username' => $db_username,
                'password' => $db_password
            );
        }
    }
    
    // Test each connection method
    foreach ($connections as $connection) {
        $test = testConnection(
            $connection['host'],
            $connection['port'],
            $connection['database'],
            $connection['username'],
            $connection['password']
        );
        
        if ($test['success']) {
            return array(
                'success' => true,
                'connection' => $connection,
                'pdo' => $test['pdo'],
                'message' => "Connected successfully using {$connection['method']}"
            );
        }
    }
    
    // If all failed, return the first connection with error
    return array(
        'success' => false,
        'connection' => $connections[0],
        'pdo' => null,
        'message' => "All connection methods failed"
    );
}

// Find a working connection
$result = findWorkingConnection();

// Create bootstrap file for success
if ($result['success']) {
    $connection = $result['connection'];
    $bootstrap_file = __DIR__ . '/db_bootstrap.php';
    
    $bootstrap_content = "<?php
// Direct database configuration for Laravel - created by db-fixed.php

// Force database configuration through Laravel Config system
\$_ENV['DB_CONNECTION'] = 'mysql';
\$_ENV['DB_HOST'] = '{$connection['host']}';
\$_ENV['DB_PORT'] = '{$connection['port']}';
\$_ENV['DB_DATABASE'] = '{$connection['database']}';
\$_ENV['DB_USERNAME'] = '{$connection['username']}';
\$_ENV['DB_PASSWORD'] = '{$connection['password']}';

// Also set them in environment
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST={$connection['host']}');
putenv('DB_PORT={$connection['port']}');
putenv('DB_DATABASE={$connection['database']}');
putenv('DB_USERNAME={$connection['username']}');
putenv('DB_PASSWORD={$connection['password']}');

// For Laravel database config
if (!defined('DB_CONFIG_OVERRIDE')) {
    define('DB_CONFIG_OVERRIDE', true);
    \$GLOBALS['db_config'] = array(
        'driver' => 'mysql',
        'host' => '{$connection['host']}',
        'port' => '{$connection['port']}',
        'database' => '{$connection['database']}',
        'username' => '{$connection['username']}',
        'password' => '{$connection['password']}',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
        'strict' => false,
        'engine' => null
    );
}
";
    
    // Write bootstrap file
    $write_success = false;
    try {
        file_put_contents($bootstrap_file, $bootstrap_content);
        $write_success = true;
    } catch (Exception $e) {
        $write_error = $e->getMessage();
    }
}

// Show results if accessed directly
if (php_sapi_name() !== 'cli' && !isset($SKIP_HEADER)) {
    if ($result['success']) {
        echo "<div class='success'>";
        echo "<h2>✓ Database Connection Successful</h2>";
        echo "<p>{$result['message']}</p>";
        echo "</div>";
        
        echo "<h3>Connection Details:</h3>";
        echo "<ul>";
        echo "<li><strong>Method:</strong> {$result['connection']['method']}</li>";
        echo "<li><strong>Host:</strong> {$result['connection']['host']}</li>";
        echo "<li><strong>Port:</strong> {$result['connection']['port']}</li>";
        echo "<li><strong>Database:</strong> {$result['connection']['database']}</li>";
        echo "<li><strong>Username:</strong> {$result['connection']['username']}</li>";
        echo "<li><strong>Password:</strong> " . (empty($result['connection']['password']) ? '<em>empty</em>' : '<em>hidden</em>') . "</li>";
        echo "</ul>";
        
        if ($write_success) {
            echo "<p class='success'>Created bootstrap file at: $bootstrap_file</p>";
        } else {
            echo "<p class='error'>Failed to create bootstrap file: $write_error</p>";
        }
        
        // Show tables
        try {
            $pdo = $result['pdo'];
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "<h3>Database Tables:</h3>";
            echo "<p>Found " . count($tables) . " tables</p>";
            if (count($tables) > 0) {
                echo "<ul>";
                foreach ($tables as $table) {
                    echo "<li>$table</li>";
                }
                echo "</ul>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>Error fetching tables: {$e->getMessage()}</p>";
        }
    } else {
        echo "<div class='error'>";
        echo "<h2>✗ Database Connection Failed</h2>";
        echo "<p>{$result['message']}</p>";
        echo "</div>";
        
        echo "<h3>Last Attempted Connection:</h3>";
        echo "<ul>";
        echo "<li><strong>Method:</strong> {$result['connection']['method']}</li>";
        echo "<li><strong>Host:</strong> {$result['connection']['host']}</li>";
        echo "<li><strong>Port:</strong> {$result['connection']['port']}</li>";
        echo "<li><strong>Database:</strong> {$result['connection']['database']}</li>";
        echo "<li><strong>Username:</strong> {$result['connection']['username']}</li>";
        echo "</ul>";
    }
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='run-migrations.php'>Run Migrations</a> - This will now use the working connection</li>";
    echo "<li><a href='repair-database.php'>Repair Database</a> - Check database tables and structure</li>";
    echo "<li><a href='create-admin.php'>Create Admin User</a> - Create an administrator account</li>";
    echo "</ol>";
    
    echo "</body></html>";
}

// Return the connection result for use in other scripts
return $result; 