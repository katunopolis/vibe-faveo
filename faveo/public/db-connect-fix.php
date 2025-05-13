<?php
/**
 * Railway Database Connection Fix Utility
 * 
 * This utility will:
 * 1. Test all possible hostname combinations for MySQL connection
 * 2. Try different connection methods (PDO, mysqli, socket)
 * 3. Implement a working solution if it finds one
 * 4. Create a Laravel configuration override if needed
 */

// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Railway Database Connection Fix</h1>";

// Function to create a badge
function badge($text, $type = 'info') {
    $colors = [
        'info' => '#17a2b8',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545',
    ];
    $color = $colors[$type] ?? $colors['info'];
    return "<span style='display:inline-block;padding:3px 8px;background:{$color};color:white;border-radius:4px;font-size:12px;'>{$text}</span>";
}

// Get Railway environment variables
$mysql_host = getenv('MYSQLHOST');
$mysql_port = getenv('MYSQLPORT');
$mysql_database = getenv('MYSQLDATABASE');
$mysql_user = getenv('MYSQLUSER');
$mysql_password = getenv('MYSQLPASSWORD');

// Get public URL if available
$mysql_public_url = getenv('MYSQL_PUBLIC_URL');
$public_host = '';
$public_port = '';

// Parse public URL if available
if ($mysql_public_url) {
    // Format: mysql://user:pass@hostname:port/database
    $parsed = parse_url($mysql_public_url);
    if ($parsed) {
        $public_host = $parsed['host'] ?? '';
        $public_port = $parsed['port'] ?? '';
    }
}

echo "<h2>Environment Variables</h2>";
echo "<p>MYSQLHOST: " . ($mysql_host ?: badge("Not set", "danger")) . "</p>";
echo "<p>MYSQLPORT: " . ($mysql_port ?: badge("Not set", "warning")) . "</p>";
echo "<p>MYSQLDATABASE: " . ($mysql_database ?: badge("Not set", "danger")) . "</p>";
echo "<p>MYSQLUSER: " . ($mysql_user ?: badge("Not set", "danger")) . "</p>";
echo "<p>MYSQLPASSWORD: " . ($mysql_password ? badge("Set (hidden)", "success") : badge("Not set", "danger")) . "</p>";

if ($mysql_public_url) {
    echo "<p>MYSQL_PUBLIC_URL: " . badge("Set", "success") . "</p>";
    echo "<p>Public Host: " . ($public_host ?: badge("Not parsed", "warning")) . "</p>";
    echo "<p>Public Port: " . ($public_port ?: badge("Not parsed", "warning")) . "</p>";
}

// Define potential hostnames to try
$hostnames = [
    $mysql_host,
    $public_host, // Add the public hostname from URL
    'yamabiko.proxy.rlwy.net', // Specific hostname from your configuration
    'mysql',
    'db',
    'database',
    'mysql-service',
    'mysql.railway.internal',
    'mysql.internal',
    'localhost',
    '127.0.0.1'
];

// Filter out empty or null hostnames
$hostnames = array_filter($hostnames);
// Remove duplicates
$hostnames = array_unique($hostnames);

// Define potential ports to try
$ports = [
    $mysql_port,
    $public_port, // Add the public port from URL
    '52501', // Specific port from your configuration
    '3306',
];
$ports = array_filter($ports);
$ports = array_unique($ports);

// Try to connect to each hostname with each port
echo "<h2>Connection Tests</h2>";
$successful_connection = null;
$successful_hostname = null;
$successful_port = null;

// First, try with matching hostname/port combinations
if ($public_host && $public_port) {
    echo "<h3>Testing Public Connection: " . htmlspecialchars($public_host) . ":" . htmlspecialchars($public_port) . "</h3>";
    if (tryConnection($public_host, $public_port, $mysql_database, $mysql_user, $mysql_password)) {
        $successful_hostname = $public_host;
        $successful_port = $public_port;
        echo "<p>" . badge("Success", "success") . " Public connection successful!</p>";
    }
}

// If public connection failed, try each hostname with each port
if (!$successful_hostname) {
    foreach ($hostnames as $hostname) {
        foreach ($ports as $port) {
            echo "<h3>Testing " . htmlspecialchars($hostname) . ":" . htmlspecialchars($port) . "</h3>";
            if (tryConnection($hostname, $port, $mysql_database, $mysql_user, $mysql_password)) {
                $successful_hostname = $hostname;
                $successful_port = $port;
                echo "<p>" . badge("Success", "success") . " Connection successful!</p>";
                break 2; // Exit both loops
            }
        }
    }
}

// If socket-based connection might work, try that too
if (!$successful_hostname) {
    echo "<h3>Testing Unix Socket Connection</h3>";
    
    $socket_paths = [
        '/var/run/mysqld/mysqld.sock',
        '/tmp/mysql.sock',
        '/var/mysql/mysql.sock'
    ];
    
    foreach ($socket_paths as $socket) {
        try {
            if (!file_exists($socket)) {
                echo "<p>Socket {$socket} does not exist.</p>";
                continue;
            }
            
            echo "<p>Trying socket: {$socket}</p>";
            $dsn = "mysql:unix_socket={$socket};dbname=" . ($mysql_database ?: 'railway');
            $conn = new PDO($dsn, $mysql_user, $mysql_password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "<p>" . badge("Success", "success") . " Socket connection successful!</p>";
            
            $successful_hostname = "socket:{$socket}";
            $successful_port = "0";
            
            break;
        } catch (PDOException $e) {
            echo "<p>" . badge("Failed", "danger") . " Socket connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// If we found a working connection, set up the application
if ($successful_hostname) {
    echo "<h2>" . badge("Success", "success") . " Found Working Connection!</h2>";
    echo "<p>Successfully connected to MySQL at: <strong>" . htmlspecialchars($successful_hostname) . ":" . htmlspecialchars($successful_port) . "</strong></p>";
    
    // Create configuration solutions
    echo "<h2>Implementing Solutions</h2>";
    
    // 1. Create database bootstrap file
    $bootstrap_file = __DIR__ . '/db_bootstrap.php';
    $bootstrap_content = "<?php
// Auto-generated database configuration for Railway
// Created by db-connect-fix.php on " . date('Y-m-d H:i:s') . "

// Force database configuration through environment
\$_ENV['DB_CONNECTION'] = 'mysql';
\$_ENV['DB_HOST'] = '{$successful_hostname}';
\$_ENV['DB_PORT'] = '{$successful_port}';
\$_ENV['DB_DATABASE'] = '" . ($mysql_database ?: 'railway') . "';
\$_ENV['DB_USERNAME'] = '{$mysql_user}';
\$_ENV['DB_PASSWORD'] = '{$mysql_password}';

// Also set them with putenv
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST={$successful_hostname}');
putenv('DB_PORT={$successful_port}');
putenv('DB_DATABASE=" . ($mysql_database ?: 'railway') . "');
putenv('DB_USERNAME={$mysql_user}');
putenv('DB_PASSWORD={$mysql_password}');

// Configure global database settings to override Laravel's configuration loading
\$GLOBALS['db_config_override'] = [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => '',
            'host' => '{$successful_hostname}',
            'port' => {$successful_port},
            'database' => '" . ($mysql_database ?: 'railway') . "',
            'username' => '{$mysql_user}',
            'password' => '{$mysql_password}',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],
    ],
];
";

    if (file_put_contents($bootstrap_file, $bootstrap_content)) {
        echo "<p>" . badge("Success", "success") . " Created database bootstrap file at {$bootstrap_file}</p>";
    } else {
        echo "<p>" . badge("Failed", "danger") . " Failed to create database bootstrap file. Check permissions.</p>";
    }
    
    // 2. Try to modify the index.php file to include our bootstrap
    $index_file = __DIR__ . '/index.php';
    if (file_exists($index_file)) {
        // Back up the original file if no backup exists
        $backup_file = $index_file . '.bak';
        if (!file_exists($backup_file)) {
            if (copy($index_file, $backup_file)) {
                echo "<p>" . badge("Success", "success") . " Created backup of index.php at {$backup_file}</p>";
            } else {
                echo "<p>" . badge("Warning", "warning") . " Could not create a backup of index.php</p>";
            }
        }
        
        // Get the content and modify it to include our bootstrap
        $index_content = file_get_contents($index_file);
        
        // Check if our bootstrap is already included
        if (strpos($index_content, 'db_bootstrap.php') === false) {
            // Inject our bootstrap include at the start
            $modified_content = preg_replace('/^<\?php/', "<?php\n// Auto-generated bootstrap include for Railway\nrequire_once __DIR__ . '/db_bootstrap.php';\n", $index_content);
            
            if ($modified_content !== $index_content && file_put_contents($index_file, $modified_content)) {
                echo "<p>" . badge("Success", "success") . " Modified index.php to include database bootstrap</p>";
            } else {
                echo "<p>" . badge("Failed", "danger") . " Failed to modify index.php. Trying alternative approach...</p>";
                
                // Alternative approach: Create a separate file that will be required by the index.php
                $prepend_file = __DIR__ . '/prepend.php';
                $prepend_content = "<?php\n// This file is loaded via auto_prepend_file directive\nrequire_once __DIR__ . '/db_bootstrap.php';\n";
                
                if (file_put_contents($prepend_file, $prepend_content)) {
                    echo "<p>" . badge("Success", "success") . " Created prepend file at {$prepend_file}</p>";
                    echo "<p>Add <code>auto_prepend_file=/var/www/html/public/prepend.php</code> to your PHP configuration.</p>";
                } else {
                    echo "<p>" . badge("Failed", "danger") . " Failed to create prepend file.</p>";
                }
            }
        } else {
            echo "<p>" . badge("Info", "info") . " Bootstrap already included in index.php</p>";
        }
    } else {
        echo "<p>" . badge("Warning", "warning") . " Could not find index.php file at {$index_file}</p>";
    }
    
    // 3. Create a custom database config file
    $config_dir = __DIR__ . '/../config';
    if (is_dir($config_dir)) {
        $db_config_file = $config_dir . '/database.php';
        
        // Back up original file if it exists
        if (file_exists($db_config_file)) {
            $db_config_backup = $db_config_file . '.bak';
            if (!file_exists($db_config_backup) && copy($db_config_file, $db_config_backup)) {
                echo "<p>" . badge("Success", "success") . " Created backup of database.php at {$db_config_backup}</p>";
            }
        }
        
        // Create new database config file that uses our global config
        $db_config_content = "<?php\n\n// Generated by Railway connection fix tool\n\n";
        $db_config_content .= "// Use the global connection configuration if available\n";
        $db_config_content .= "if (isset(\$GLOBALS['db_config_override'])) {\n";
        $db_config_content .= "    return \$GLOBALS['db_config_override'];\n";
        $db_config_content .= "}\n\n";
        $db_config_content .= "// Fallback configuration\n";
        $db_config_content .= "return [\n";
        $db_config_content .= "    'default' => env('DB_CONNECTION', 'mysql'),\n";
        $db_config_content .= "    'connections' => [\n";
        $db_config_content .= "        'mysql' => [\n";
        $db_config_content .= "            'driver' => 'mysql',\n";
        $db_config_content .= "            'url' => env('DATABASE_URL'),\n";
        $db_config_content .= "            'host' => env('DB_HOST', '{$successful_hostname}'),\n";
        $db_config_content .= "            'port' => env('DB_PORT', {$successful_port}),\n";
        $db_config_content .= "            'database' => env('DB_DATABASE', '" . ($mysql_database ?: 'railway') . "'),\n";
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
        
        if (file_put_contents($db_config_file, $db_config_content)) {
            echo "<p>" . badge("Success", "success") . " Created custom database configuration at {$db_config_file}</p>";
        } else {
            echo "<p>" . badge("Failed", "danger") . " Failed to create database configuration file. Check permissions.</p>";
        }
    } else {
        echo "<p>" . badge("Warning", "warning") . " Could not find config directory at {$config_dir}</p>";
    }
    
    // 4. Create a direct database connection file for Laravel
    $direct_file = __DIR__ . '/direct-db-connect.php';
    $direct_content = "<?php\n\n// Direct database connection script for Laravel\n\n";
    $direct_content .= "try {\n";
    $direct_content .= "    \$pdo = new PDO('mysql:host={$successful_hostname};port={$successful_port};dbname=" . ($mysql_database ?: 'railway') . "', '{$mysql_user}', '{$mysql_password}');\n";
    $direct_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
    $direct_content .= "    echo \"Connected to database successfully!\\n\";\n";
    $direct_content .= "    \$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);\n";
    $direct_content .= "    echo \"Found \" . count(\$tables) . \" tables.\\n\";\n";
    $direct_content .= "} catch (PDOException \$e) {\n";
    $direct_content .= "    echo \"Connection failed: \" . \$e->getMessage() . \"\\n\";\n";
    $direct_content .= "}\n";
    
    if (file_put_contents($direct_file, $direct_content)) {
        echo "<p>" . badge("Success", "success") . " Created direct connection test script at {$direct_file}</p>";
    } else {
        echo "<p>" . badge("Failed", "danger") . " Failed to create direct connection script.</p>";
    }
    
    // 5. Update .env file if possible
    $env_file = '/var/www/html/.env';
    if (file_exists($env_file) && is_writable($env_file)) {
        // Read current .env file
        $env_content = file_get_contents($env_file);
        
        // Update database settings
        $env_content = preg_replace('/DB_HOST=.*/', "DB_HOST={$successful_hostname}", $env_content);
        $env_content = preg_replace('/DB_PORT=.*/', "DB_PORT={$successful_port}", $env_content);
        $env_content = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE=" . ($mysql_database ?: 'railway'), $env_content);
        $env_content = preg_replace('/DB_USERNAME=.*/', "DB_USERNAME={$mysql_user}", $env_content);
        $env_content = preg_replace('/DB_PASSWORD=.*/', "DB_PASSWORD={$mysql_password}", $env_content);
        
        if (file_put_contents($env_file, $env_content)) {
            echo "<p>" . badge("Success", "success") . " Updated .env file with working connection information</p>";
        } else {
            echo "<p>" . badge("Failed", "danger") . " Failed to update .env file.</p>";
        }
    } else {
        echo "<p>" . badge("Warning", "warning") . " Could not update .env file (not found or not writable)</p>";
    }
    
    // 6. Create bootstrap script for Railway app restart
    $bootstrap_update_file = __DIR__ . '/bootstrap_update.sh';
    $bootstrap_update_content = "#!/bin/bash\n\n";
    $bootstrap_update_content .= "# Update bootstrap.sh with working database configuration\n";
    $bootstrap_update_content .= "cat > /usr/local/bin/bootstrap_db_config.php << 'EOF'\n";
    $bootstrap_update_content .= "<?php\n";
    $bootstrap_update_content .= "// Auto-generated database configuration for bootstrap.sh\n";
    $bootstrap_update_content .= "// Created by db-connect-fix.php on " . date('Y-m-d H:i:s') . "\n";
    $bootstrap_update_content .= "\$_ENV['DB_CONNECTION'] = 'mysql';\n";
    $bootstrap_update_content .= "\$_ENV['DB_HOST'] = '{$successful_hostname}';\n";
    $bootstrap_update_content .= "\$_ENV['DB_PORT'] = '{$successful_port}';\n";
    $bootstrap_update_content .= "\$_ENV['DB_DATABASE'] = '" . ($mysql_database ?: 'railway') . "';\n";
    $bootstrap_update_content .= "\$_ENV['DB_USERNAME'] = '{$mysql_user}';\n";
    $bootstrap_update_content .= "\$_ENV['DB_PASSWORD'] = '{$mysql_password}';\n";
    $bootstrap_update_content .= "EOF\n\n";
    $bootstrap_update_content .= "echo \"Created bootstrap database config file\"\n";
    $bootstrap_update_content .= "echo \"Restart your application for changes to take effect\"\n";
    
    if (file_put_contents($bootstrap_update_file, $bootstrap_update_content)) {
        chmod($bootstrap_update_file, 0755); // Make executable
        echo "<p>" . badge("Success", "success") . " Created bootstrap update script at {$bootstrap_update_file}</p>";
    } else {
        echo "<p>" . badge("Failed", "danger") . " Failed to create bootstrap update script.</p>";
    }
    
    echo "<h2>Next Steps</h2>";
    echo "<p>Your database connection has been configured to use <strong>" . htmlspecialchars($successful_hostname) . ":" . htmlspecialchars($successful_port) . "</strong>.</p>";
    echo "<ol>";
    echo "<li>Restart your application to apply changes</li>";
    echo "<li>Test your application to ensure the database connection is working</li>";
    echo "<li>If you still experience issues, try manually updating your codebase with the detected settings</li>";
    echo "</ol>";
} else {
    echo "<h2>" . badge("Failed", "danger") . " No Working Connection Found</h2>";
    echo "<p>Could not connect to the MySQL database using any of the attempted hostnames or methods.</p>";
    
    echo "<h3>Troubleshooting Steps</h3>";
    echo "<ol>";
    echo "<li>Verify that the MySQL service is running in your Railway project</li>";
    echo "<li>Make sure the MySQL service is linked to your application service in Railway</li>";
    echo "<li>Check that the environment variables are correctly set in your Railway dashboard</li>";
    echo "<li>Try redeploying your application after confirming the above steps</li>";
    echo "</ol>";
    
    echo "<h3>Manual Database Configuration</h3>";
    echo "<p>If you know the correct database connection information, you can manually set it up:</p>";
    echo "<pre>";
    echo "// Add to the beginning of public/index.php\n";
    echo "&lt;?php\n";
    echo "// Database configuration override\n";
    echo "\$_ENV['DB_CONNECTION'] = 'mysql';\n";
    echo "\$_ENV['DB_HOST'] = 'yamabiko.proxy.rlwy.net'; // Use public hostname\n";
    echo "\$_ENV['DB_PORT'] = '52501'; // Use public port\n";
    echo "\$_ENV['DB_DATABASE'] = 'railway';\n";
    echo "\$_ENV['DB_USERNAME'] = 'root';\n";
    echo "\$_ENV['DB_PASSWORD'] = 'your-password'; // Use actual password\n";
    echo "</pre>";
}

/**
 * Tries to connect to a database with the given parameters
 * 
 * @param string $hostname Database hostname
 * @param string $port Database port
 * @param string $database Database name
 * @param string $username Database username
 * @param string $password Database password
 * @return bool True if connection successful, false otherwise
 */
function tryConnection($hostname, $port, $database, $username, $password) {
    try {
        $dsn = "mysql:host={$hostname};port={$port};dbname=" . ($database ?: 'railway');
        echo "<p>DSN: " . htmlspecialchars($dsn) . "</p>";
        
        $conn = new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test the connection with a simple query
        $tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>" . badge("Success", "success") . " Connection successful with PDO!</p>";
        echo "<p>Tables found: " . count($tables) . "</p>";
        
        if (count($tables) > 0) {
            echo "<ul>";
            foreach (array_slice($tables, 0, 5) as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            if (count($tables) > 5) {
                echo "<li>... and " . (count($tables) - 5) . " more</li>";
            }
            echo "</ul>";
        }
        
        return true;
    } catch (PDOException $e) {
        echo "<p>" . badge("Failed", "danger") . " PDO connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        
        // Try mysqli as an alternative
        try {
            echo "<p>Trying mysqli connection...</p>";
            $mysqli = new mysqli($hostname, $username, $password, $database, $port);
            
            if ($mysqli->connect_error) {
                throw new Exception($mysqli->connect_error);
            }
            
            echo "<p>" . badge("Success", "success") . " Connection successful with mysqli!</p>";
            $mysqli->close();
            return true;
        } catch (Exception $e) {
            echo "<p>" . badge("Failed", "danger") . " mysqli connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            return false;
        }
    }
}
?> 