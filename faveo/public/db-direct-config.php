<?php
/**
 * Direct database configuration script for Laravel on Railway
 * 
 * This script uses PDO to directly connect to the database
 * using environment variables, bypassing hostname resolution issues
 */

// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Railway Direct Database Configuration</h1>";

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

// Create direct database configuration without hostname resolution
// This will be included at the beginning of index.php
$bootstrap_file = __DIR__ . '/db_bootstrap.php';
$bootstrap_content = "<?php
// Direct database configuration for Laravel on Railway
// This file bypasses hostname resolution by using direct IP configuration

// Force database configuration through Laravel Config system
\$_ENV['DB_CONNECTION'] = 'mysql';
\$_ENV['DB_HOST'] = '{$mysql_host}';
\$_ENV['DB_PORT'] = '{$mysql_port}';
\$_ENV['DB_DATABASE'] = '{$mysql_database}';
\$_ENV['DB_USERNAME'] = '{$mysql_user}';
\$_ENV['DB_PASSWORD'] = '{$mysql_password}';

// Also set them in environment to be doubly sure
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST={$mysql_host}');
putenv('DB_PORT={$mysql_port}');
putenv('DB_DATABASE={$mysql_database}');
putenv('DB_USERNAME={$mysql_user}');
putenv('DB_PASSWORD={$mysql_password}');

// Override Laravel's database configuration directly
if (!defined('LARAVEL_DATABASE_CONFIG_OVERRIDDEN')) {
    define('LARAVEL_DATABASE_CONFIG_OVERRIDDEN', true);
    \$GLOBALS['laravel_database_config'] = [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'url' => '',
                'host' => '{$mysql_host}',
                'port' => {$mysql_port},
                'database' => '{$mysql_database}',
                'username' => '{$mysql_user}',
                'password' => '{$mysql_password}',
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => '',
                ]) : [],
            ],
        ],
    ];
}
";

// Write bootstrap file
if (file_put_contents($bootstrap_file, $bootstrap_content)) {
    echo "<p style='color:green'>✓ Created database bootstrap file at {$bootstrap_file}</p>";
} else {
    echo "<p style='color:red'>✗ Failed to create bootstrap file</p>";
}

// Modify index.php to include our bootstrap file
$index_file = __DIR__ . '/index.php';
if (file_exists($index_file)) {
    $original_content = file_get_contents($index_file);
    $backup_file = $index_file . '.bak';
    
    // Back up original
    if (!file_exists($backup_file)) {
        file_put_contents($backup_file, $original_content);
        echo "<p>Created backup of index.php at {$backup_file}</p>";
    }
    
    // Insert our bootstrap include at the top
    if (strpos($original_content, 'db_bootstrap.php') === false) {
        $modified_content = preg_replace('/^<\?php/', "<?php\n// Direct database configuration for Railway\nrequire_once __DIR__ . '/db_bootstrap.php';", $original_content);
        
        if (file_put_contents($index_file, $modified_content)) {
            echo "<p style='color:green'>✓ Modified index.php to include database bootstrap</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to modify index.php</p>";
        }
    } else {
        echo "<p>Bootstrap already included in index.php</p>";
    }
} else {
    echo "<p style='color:red'>✗ index.php not found</p>";
}

// Test the database connection
echo "<h2>Testing Database Connection</h2>";
try {
    $dsn = "mysql:host={$mysql_host};port={$mysql_port};dbname={$mysql_database}";
    echo "<p>DSN: {$dsn}</p>";
    $conn = new PDO($dsn, $mysql_user, $mysql_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✓ Connection successful!</p>";
    
    // Display some database info
    $stmt = $conn->query("SHOW VARIABLES LIKE 'version'");
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>MySQL version: {$version['Value']}</p>";
    
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . count($tables) . "</p>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach (array_slice($tables, 0, 10) as $table) {
            echo "<li>{$table}</li>";
        }
        if (count($tables) > 10) {
            echo "<li>... and " . (count($tables) - 10) . " more</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Connection failed: " . $e->getMessage() . "</p>";
}

// Create a config resolver
echo "<h2>Creating Database Config Resolver</h2>";
$config_dir = __DIR__ . '/../config';
if (is_dir($config_dir)) {
    $db_config_file = $config_dir . '/database.php';
    $db_config_content = "<?php\nreturn \$GLOBALS['laravel_database_config'] ?? [\n";
    $db_config_content .= "    'default' => env('DB_CONNECTION', 'mysql'),\n";
    $db_config_content .= "    'connections' => [\n";
    $db_config_content .= "        'mysql' => [\n";
    $db_config_content .= "            'driver' => 'mysql',\n";
    $db_config_content .= "            'url' => env('
    DATABASE_URL'),\n";
    $db_config_content .= "            'host' => env('DB_HOST', '{$mysql_host}'),\n";
    $db_config_content .= "            'port' => env('DB_PORT', '{$mysql_port}'),\n";
    $db_config_content .= "            'database' => env('DB_DATABASE', '{$mysql_database}'),\n";
    $db_config_content .= "            'username' => env('DB_USERNAME', '{$mysql_user}'),\n";
    $db_config_content .= "            'password' => env('DB_PASSWORD', '{$mysql_password}'),\n";
    $db_config_content .= "            'unix_socket' => env('DB_SOCKET', ''),\n";
    $db_config_content .= "            'charset' => 'utf8mb4',\n";
    $db_config_content .= "            'collation' => 'utf8mb4_unicode_ci',\n";
    $db_config_content .= "            'prefix' => '',\n";
    $db_config_content .= "            'prefix_indexes' => true,\n";
    $db_config_content .= "            'strict' => true,\n";
    $db_config_content .= "            'engine' => null,\n";
    $db_config_content .= "            'options' => extension_loaded('pdo_mysql') ? array_filter([\n";
    $db_config_content .= "                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),\n";
    $db_config_content .= "            ]) : [],\n";
    $db_config_content .= "        ],\n";
    $db_config_content .= "    ],\n";
    $db_config_content .= "];\n";
    
    if (file_exists($db_config_file)) {
        $db_config_backup = $db_config_file . '.bak';
        if (!file_exists($db_config_backup)) {
            copy($db_config_file, $db_config_backup);
            echo "<p>Backed up database config to {$db_config_backup}</p>";
        }
    }
    
    if (file_put_contents($db_config_file, $db_config_content)) {
        echo "<p style='color:green'>✓ Created new database config at {$db_config_file}</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create database config</p>";
    }
} else {
    echo "<p style='color:orange'>⚠ Could not find config directory at {$config_dir}</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>1. Make sure the MySQL service is properly linked to your app service in Railway</p>";
echo "<p>2. Verify that all required environment variables are set</p>";
echo "<p>3. Redeploy your application to apply these changes</p>";
?> 