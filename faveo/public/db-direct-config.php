<?php
/**
 * Direct Database Configuration Helper for Faveo
 * 
 * This file sets up database configuration directly without relying on Laravel's
 * configuration system. It's designed to be included before running migrations
 * or other database operations when the Laravel application cannot be fully bootstrapped.
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Show a nice header if accessed directly
if (php_sapi_name() !== 'cli' && !isset($SKIP_HEADER)) {
    echo "<html><head><title>Faveo Database Configuration</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
        h1, h2 { color: #336699; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>";
    echo "</head><body>";
    echo "<h1>Faveo Database Configuration Helper</h1>";
}

// Try to detect the best database connection
function detectBestDatabaseConnection() {
    $connection = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => '3306',
        'database' => 'faveo',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
        'strict' => false,
        'engine' => null,
        'source' => 'default'
    ];
    
    // 1. Try env variables
    if (getenv('DB_HOST') && getenv('DB_DATABASE') && getenv('DB_USERNAME')) {
        $connection['host'] = getenv('DB_HOST');
        $connection['port'] = getenv('DB_PORT') ?: '3306';
        $connection['database'] = getenv('DB_DATABASE');
        $connection['username'] = getenv('DB_USERNAME');
        $connection['password'] = getenv('DB_PASSWORD') ?: '';
        $connection['source'] = 'environment variables';
        return $connection;
    }
    
    // 2. Try Railway environment variables
    if (getenv('MYSQLHOST') && getenv('MYSQLDATABASE') && getenv('MYSQLUSER')) {
        $connection['host'] = getenv('MYSQLHOST');
        $connection['port'] = getenv('MYSQLPORT') ?: '3306';
        $connection['database'] = getenv('MYSQLDATABASE');
        $connection['username'] = getenv('MYSQLUSER');
        $connection['password'] = getenv('MYSQLPASSWORD') ?: '';
        $connection['source'] = 'Railway environment variables';
        return $connection;
    }
    
    // 3. Try DATABASE_URL
    $database_url = getenv('DATABASE_URL');
    if ($database_url && strpos($database_url, '${{') === false) {
        try {
            $parsed = parse_url($database_url);
            if ($parsed) {
                $connection['host'] = $parsed['host'] ?? 'localhost';
                $connection['port'] = $parsed['port'] ?? '3306';
                $connection['database'] = ltrim($parsed['path'] ?? '', '/');
                $connection['username'] = $parsed['user'] ?? 'root';
                $connection['password'] = $parsed['pass'] ?? '';
                $connection['source'] = 'DATABASE_URL';
                return $connection;
            }
        } catch (Exception $e) {
            // Continue to next method
        }
    }
    
    // 4. Try Railway internal hostname
    try {
        $dsn = "mysql:host=mysql.railway.internal;port=3306;dbname=railway";
        $conn = new PDO($dsn, 'root', getenv('MYSQLPASSWORD') ?: '');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $connection['host'] = 'mysql.railway.internal';
        $connection['port'] = '3306';
        $connection['database'] = 'railway';
        $connection['username'] = 'root';
        $connection['password'] = getenv('MYSQLPASSWORD') ?: '';
        $connection['source'] = 'Railway internal hostname';
        return $connection;
    } catch (PDOException $e) {
        // Continue to next method
    }
    
    // 5. Try to find .env file and parse it
    $env_path = realpath(__DIR__ . '/../.env');
    if (file_exists($env_path)) {
        $env_content = file_get_contents($env_path);
        preg_match('/DB_HOST=([^\n]+)/', $env_content, $host_matches);
        preg_match('/DB_PORT=([^\n]+)/', $env_content, $port_matches);
        preg_match('/DB_DATABASE=([^\n]+)/', $env_content, $database_matches);
        preg_match('/DB_USERNAME=([^\n]+)/', $env_content, $username_matches);
        preg_match('/DB_PASSWORD=([^\n]+)/', $env_content, $password_matches);
        
        if (!empty($host_matches[1]) && !empty($database_matches[1]) && !empty($username_matches[1])) {
            $connection['host'] = trim($host_matches[1]);
            $connection['port'] = !empty($port_matches[1]) ? trim($port_matches[1]) : '3306';
            $connection['database'] = trim($database_matches[1]);
            $connection['username'] = trim($username_matches[1]);
            $connection['password'] = !empty($password_matches[1]) ? trim($password_matches[1]) : '';
            $connection['source'] = '.env file';
            return $connection;
        }
    }
    
    return $connection;
}

// Try to get connection details
$db_connection = detectBestDatabaseConnection();

// Test connection
$connection_successful = false;
$connection_error = '';

try {
    $dsn = "mysql:host={$db_connection['host']};port={$db_connection['port']};dbname={$db_connection['database']}";
    $pdo = new PDO($dsn, $db_connection['username'], $db_connection['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection_successful = true;
} catch (PDOException $e) {
    $connection_error = $e->getMessage();
}

// Show results if accessed directly
if (php_sapi_name() !== 'cli' && !isset($SKIP_HEADER)) {
    echo "<h2>Connection Information</h2>";
    echo "<table>";
    echo "<tr><th>Parameter</th><th>Value</th></tr>";
    echo "<tr><td>Driver</td><td>{$db_connection['driver']}</td></tr>";
    echo "<tr><td>Host</td><td>{$db_connection['host']}</td></tr>";
    echo "<tr><td>Port</td><td>{$db_connection['port']}</td></tr>";
    echo "<tr><td>Database</td><td>{$db_connection['database']}</td></tr>";
    echo "<tr><td>Username</td><td>{$db_connection['username']}</td></tr>";
    echo "<tr><td>Password</td><td>" . (empty($db_connection['password']) ? '<em>empty</em>' : '<em>hidden</em>') . "</td></tr>";
    echo "<tr><td>Source</td><td>{$db_connection['source']}</td></tr>";
    echo "</table>";
    
    echo "<h2>Connection Test</h2>";
    if ($connection_successful) {
        echo "<p class='success'>Connection successful!</p>";
        
        // Show tables
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>Tables found: " . count($tables) . "</p>";
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
        echo "<p class='error'>Connection failed: $connection_error</p>";
    }
    
    echo "<h2>Create Bootstrap File</h2>";
}

// Generate bootstrap file
$bootstrap_file_path = __DIR__ . '/db_bootstrap.php';
$bootstrap_content = <<<EOT
<?php
// Direct database configuration for Laravel - auto-generated

// Force database configuration through Laravel Config system
\$_ENV['DB_CONNECTION'] = '{$db_connection['driver']}';
\$_ENV['DB_HOST'] = '{$db_connection['host']}';
\$_ENV['DB_PORT'] = '{$db_connection['port']}';
\$_ENV['DB_DATABASE'] = '{$db_connection['database']}';
\$_ENV['DB_USERNAME'] = '{$db_connection['username']}';
\$_ENV['DB_PASSWORD'] = '{$db_connection['password']}';

// Also set them in environment to be doubly sure
putenv('DB_CONNECTION={$db_connection['driver']}');
putenv('DB_HOST={$db_connection['host']}');
putenv('DB_PORT={$db_connection['port']}');
putenv('DB_DATABASE={$db_connection['database']}');
putenv('DB_USERNAME={$db_connection['username']}');
putenv('DB_PASSWORD={$db_connection['password']}');

// Override Laravel Database config directly
if (!function_exists('config') && !isset(\$SKIP_CONFIG_OVERRIDE)) {
    \$GLOBALS['db_config_override'] = [
        'driver' => '{$db_connection['driver']}',
        'host' => '{$db_connection['host']}',
        'port' => '{$db_connection['port']}',
        'database' => '{$db_connection['database']}',
        'username' => '{$db_connection['username']}',
        'password' => '{$db_connection['password']}',
        'charset' => '{$db_connection['charset']}',
        'collation' => '{$db_connection['collation']}',
        'prefix' => '{$db_connection['prefix']}',
        'strict' => {$db_connection['strict'] ? 'true' : 'false'},
        'engine' => null,
    ];
}
EOT;

// Write bootstrap file
$write_success = false;
try {
    file_put_contents($bootstrap_file_path, $bootstrap_content);
    $write_success = true;
} catch (Exception $e) {
    $write_error = $e->getMessage();
}

// Create patch file for index.php to load our bootstrap before Laravel
$index_patch_path = __DIR__ . '/index_patch.php';
$index_patch_content = <<<EOT
<?php
// Patched index.php for Faveo to include direct database config
// Include the bootstrap file for direct database configuration
\$db_bootstrap_file = __DIR__ . '/db_bootstrap.php';
if (file_exists(\$db_bootstrap_file)) {
    require_once \$db_bootstrap_file;
}

// Continue with the regular index.php content
require __DIR__ . '/index.php';
EOT;

// Write patch file
$patch_success = false;
try {
    file_put_contents($index_patch_path, $index_patch_content);
    $patch_success = true;
} catch (Exception $e) {
    $patch_error = $e->getMessage();
}

// Show results if accessed directly
if (php_sapi_name() !== 'cli' && !isset($SKIP_HEADER)) {
    if ($write_success) {
        echo "<p class='success'>Bootstrap file created successfully at $bootstrap_file_path</p>";
        echo "<p>This file will be automatically included when running migrations.</p>";
    } else {
        echo "<p class='error'>Failed to create bootstrap file: $write_error</p>";
    }
    
    if ($patch_success) {
        echo "<p class='success'>Index patch file created successfully at $index_patch_path</p>";
        echo "<p>You can rename this to index.php to use the direct database configuration.</p>";
    } else {
        echo "<p class='error'>Failed to create index patch file: $patch_error</p>";
    }
    
    echo "<h2>Next Steps</h2>";
    echo "<ol>";
    echo "<li>Include <code>require_once '$bootstrap_file_path';</code> at the beginning of your migration scripts</li>";
    echo "<li>Alternatively, you can rename <code>index_patch.php</code> to <code>index.php</code> to automatically use the database configuration</li>";
    echo "<li>If you're still having issues, try the <a href='run-migrations.php'>migration tool</a> which uses this configuration</li>";
    echo "</ol>";
    
    echo "</body></html>";
}

// Return the connection and PDO for use in scripts
return [
    'connection' => $db_connection,
    'pdo' => $connection_successful ? $pdo : null,
    'success' => $connection_successful,
    'error' => $connection_error,
    'bootstrap_file' => $bootstrap_file_path,
    'bootstrap_content' => $bootstrap_content,
    'write_success' => $write_success
];
?> 