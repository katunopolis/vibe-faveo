<?php
/**
 * Database Setup Utility for Faveo
 * 
 * This script provides database configuration, testing, and setup functionality
 * for Faveo deployed on Railway.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security check - prevent unauthorized access
$password = $_POST['auth_password'] ?? $_GET['key'] ?? '';
$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
$authorized = ($password === $stored_password);

// Store results
$results = [];
$message = '';
$success = false;

/**
 * Get database connection configuration from environment
 */
function get_db_config() {
    return [
        'driver' => getenv('DB_CONNECTION') ?: 'mysql',
        'host' => getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'mysql.railway.internal',
        'port' => getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: getenv('MYSQLDATABASE') ?: 'railway',
        'username' => getenv('DB_USERNAME') ?: getenv('MYSQLUSER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => 'InnoDB',
    ];
}

/**
 * Test database connection
 */
function test_database_connection($config = null) {
    if ($config === null) {
        $config = get_db_config();
    }
    
    try {
        $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1");
        
        return [
            'success' => true,
            'message' => 'Successfully connected to the database'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Test if the database exists
 */
function test_database_exists($config = null) {
    if ($config === null) {
        $config = get_db_config();
    }
    
    try {
        // Try to connect to the server without specifying a database
        $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['database']}'");
        $database_exists = ($stmt->rowCount() > 0);
        
        return [
            'success' => $database_exists,
            'message' => $database_exists 
                ? "Database '{$config['database']}' exists" 
                : "Database '{$config['database']}' does not exist"
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database check failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Create the database if it doesn't exist
 */
function create_database($config = null) {
    if ($config === null) {
        $config = get_db_config();
    }
    
    try {
        // First check if database exists
        $db_exists = test_database_exists($config);
        if ($db_exists['success']) {
            return [
                'success' => true,
                'message' => 'Database already exists, skipping creation'
            ];
        }
        
        // Connect to server without database
        $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Create database
        $database_name = $config['database'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database_name` 
                    CHARACTER SET {$config['charset']} 
                    COLLATE {$config['collation']}");
        
        return [
            'success' => true,
            'message' => "Successfully created database '{$database_name}'"
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database creation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Update the .env file with database configuration
 */
function update_env_file($config = null) {
    if ($config === null) {
        $config = get_db_config();
    }
    
    $env_path = '/var/www/html/.env';
    
    if (!file_exists($env_path)) {
        return [
            'success' => false,
            'message' => '.env file not found at ' . $env_path
        ];
    }
    
    $env_content = file_get_contents($env_path);
    
    // Update database config
    $env_content = preg_replace('/DB_CONNECTION=.*/m', "DB_CONNECTION={$config['driver']}", $env_content);
    $env_content = preg_replace('/DB_HOST=.*/m', "DB_HOST={$config['host']}", $env_content);
    $env_content = preg_replace('/DB_PORT=.*/m', "DB_PORT={$config['port']}", $env_content);
    $env_content = preg_replace('/DB_DATABASE=.*/m', "DB_DATABASE={$config['database']}", $env_content);
    $env_content = preg_replace('/DB_USERNAME=.*/m', "DB_USERNAME={$config['username']}", $env_content);
    $env_content = preg_replace('/DB_PASSWORD=.*/m', "DB_PASSWORD={$config['password']}", $env_content);
    
    // Ensure DB_CONNECTION exists
    if (!preg_match('/DB_CONNECTION=/m', $env_content)) {
        $env_content .= "\nDB_CONNECTION={$config['driver']}\n";
    }
    
    // Ensure other DB_ variables exist
    $db_vars = ['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'];
    foreach ($db_vars as $var) {
        if (!preg_match("/DB_{$var}=/m", $env_content)) {
            $env_content .= "\nDB_{$var}=" . $config[strtolower($var)] . "\n";
        }
    }
    
    if (file_put_contents($env_path, $env_content)) {
        return [
            'success' => true,
            'message' => '.env file updated successfully with database config'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to write to .env file'
        ];
    }
}

/**
 * Run database migrations
 */
function run_migrations() {
    try {
        // Set working directory
        chdir('/var/www/html');
        
        // Run artisan migrate command
        $output = [];
        $return_var = 0;
        exec('php artisan migrate --force 2>&1', $output, $return_var);
        
        return [
            'success' => ($return_var === 0),
            'message' => implode("\n", $output)
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to run migrations: ' . $e->getMessage()
        ];
    }
}

// Get database config for display
$db_config = get_db_config();

// Process form actions
if ($authorized) {
    // Test connection
    if (isset($_POST['test_connection'])) {
        $test_result = test_database_connection();
        $success = $test_result['success'];
        $message = $test_result['message'];
    }
    
    // Create database
    if (isset($_POST['create_database'])) {
        $create_result = create_database();
        $success = $create_result['success'];
        $message = $create_result['message'];
    }
    
    // Update .env file
    if (isset($_POST['update_env'])) {
        $env_result = update_env_file();
        $success = $env_result['success'];
        $message = $env_result['message'];
    }
    
    // Run migrations
    if (isset($_POST['run_migrations'])) {
        $migration_result = run_migrations();
        $success = $migration_result['success'];
        $message = "Migration result: " . ($migration_result['success'] ? 'Success' : 'Failed') . 
                  "\n" . $migration_result['message'];
    }
    
    // Setup all (sequence of operations)
    if (isset($_POST['setup_all'])) {
        try {
            // 1. Create database if needed
            $create_result = create_database();
            $results[] = ($create_result['success'] ? '✅ ' : '❌ ') . $create_result['message'];
            
            // 2. Update .env file
            $env_result = update_env_file();
            $results[] = ($env_result['success'] ? '✅ ' : '❌ ') . $env_result['message'];
            
            // 3. Test connection
            $test_result = test_database_connection();
            $results[] = ($test_result['success'] ? '✅ ' : '❌ ') . $test_result['message'];
            
            if ($test_result['success']) {
                // 4. Run migrations
                $migration_result = run_migrations();
                $results[] = ($migration_result['success'] ? '✅ ' : '❌ ') . 
                            "Migration: " . ($migration_result['success'] ? 'Success' : 'Failed');
                if (!empty($migration_result['message'])) {
                    $results[] = $migration_result['message'];
                }
            }
            
            $success = $test_result['success'];
            $message = $success 
                ? "Database setup completed successfully" 
                : "Database setup encountered errors";
            
        } catch (Exception $e) {
            $success = false;
            $message = "Error during database setup: " . $e->getMessage();
            $results[] = '❌ ' . $e->getMessage();
        }
    }
}

// Get current database status
$connection_status = null;
if ($authorized) {
    $connection_status = test_database_connection();
    $database_exists = test_database_exists();
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1, h2, h3 {
            color: #336699;
        }
        .box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .warning {
            color: orange;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 300px;
        }
        button, input[type="submit"] {
            background: #336699;
            color: white;
            border: none;
            padding: 8px 15px;
            margin-right: 5px;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover, input[type="submit"]:hover {
            background: #264d73;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn-danger {
            background: #d9534f;
        }
        .btn-danger:hover {
            background: #c9302c;
        }
        .btn-primary {
            background: #336699;
        }
        .btn-success {
            background: #5cb85c;
        }
        .btn-success:hover {
            background: #449d44;
        }
        ul.results {
            padding-left: 20px;
        }
        ul.results li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Database Setup</h1>
        
        <?php if (!$authorized): ?>
        <div class="box">
            <h2>Authentication Required</h2>
            <p>Please enter the admin password to access this tool.</p>
            <form method="post">
                <div class="form-group">
                    <label for="auth_password">Password:</label>
                    <input type="password" id="auth_password" name="auth_password" required style="padding: 8px; width: 100%; box-sizing: border-box; margin-bottom: 10px;">
                </div>
                <button type="submit">Authenticate</button>
            </form>
        </div>
        <?php else: ?>
        
        <?php if (!empty($message)): ?>
        <div class="box">
            <h2>Result</h2>
            <p class="<?php echo $success ? 'success' : 'error'; ?>"><?php echo nl2br(htmlspecialchars($message)); ?></p>
            
            <?php if (!empty($results)): ?>
            <h3>Details:</h3>
            <ul class="results">
                <?php foreach ($results as $result): ?>
                <li><?php echo nl2br(htmlspecialchars($result)); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Database Configuration</h2>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Driver</td>
                    <td><?php echo htmlspecialchars($db_config['driver']); ?></td>
                </tr>
                <tr>
                    <td>Host</td>
                    <td><?php echo htmlspecialchars($db_config['host']); ?></td>
                </tr>
                <tr>
                    <td>Port</td>
                    <td><?php echo htmlspecialchars($db_config['port']); ?></td>
                </tr>
                <tr>
                    <td>Database</td>
                    <td><?php echo htmlspecialchars($db_config['database']); ?></td>
                </tr>
                <tr>
                    <td>Username</td>
                    <td><?php echo htmlspecialchars($db_config['username']); ?></td>
                </tr>
                <tr>
                    <td>Password</td>
                    <td>********</td>
                </tr>
            </table>
            
            <?php if ($connection_status): ?>
            <p>Connection Status: 
                <span class="<?php echo $connection_status['success'] ? 'success' : 'error'; ?>">
                    <?php echo $connection_status['success'] ? 'Connected' : 'Not Connected'; ?>
                </span>
            </p>
            <?php endif; ?>
            
            <?php if (isset($database_exists)): ?>
            <p>Database Exists: 
                <span class="<?php echo $database_exists['success'] ? 'success' : 'error'; ?>">
                    <?php echo $database_exists['success'] ? 'Yes' : 'No'; ?>
                </span>
            </p>
            <?php endif; ?>
        </div>
        
        <div class="box">
            <h2>Database Operations</h2>
            
            <form method="post" style="margin-bottom: 15px;">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                <button type="submit" name="test_connection" class="btn-primary">Test Connection</button>
                <button type="submit" name="create_database" class="btn-primary">Create Database</button>
                <button type="submit" name="update_env" class="btn-primary">Update .env File</button>
                <button type="submit" name="run_migrations" class="btn-danger">Run Migrations</button>
            </form>
            
            <h3>Complete Setup</h3>
            <p>Run all database setup steps in the correct order:</p>
            <form method="post">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                <button type="submit" name="setup_all" class="btn-success">Complete Database Setup</button>
            </form>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html> 