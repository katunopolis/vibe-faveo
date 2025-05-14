<?php
/**
 * Comprehensive URL Redirect Fix for Faveo
 * 
 * This script fixes redirection issues by modifying all URL-related settings
 * in the database, config files, and cache files.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutes

// Security check - prevent unauthorized access
$password = $_POST['auth_password'] ?? $_GET['key'] ?? '';
$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
$authorized = ($password === $stored_password);

// Store results
$results = [];
$message = '';
$success = false;

// Function to connect to the database using various methods
function get_database_connection() {
    try {
        // Try Railway's internal networking first
        $host = 'mysql.railway.internal';
        $port = getenv('MYSQLPORT') ?: '3306';
        $database = getenv('MYSQLDATABASE') ?: 'railway';
        $username = getenv('MYSQLUSER') ?: 'root';
        $password = getenv('MYSQLPASSWORD') ?: '';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database}";
        $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // If internal connection fails, try external connection
        try {
            // This uses the known external hostname from the project structure doc
            $host = 'yamabiko.proxy.rlwy.net';
            $port = '52501'; // This might change, so ideally we'd get it from env
            $database = 'railway';
            $username = 'root';
            $password = getenv('MYSQLPASSWORD') ?: '';
            
            $dsn = "mysql:host={$host};port={$port};dbname={$database}";
            $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            // If all attempts fail, throw the exception
            throw $e;
        }
    }
}

// Auto-detect Railway URL
function detect_url() {
    // Try to get from environment first
    $env_url = getenv('APP_URL');
    if ($env_url && !strpos($env_url, 'localhost') && !strpos($env_url, '8080')) {
        return rtrim($env_url, '/');
    }
    
    // Otherwise detect from current request
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 
                    $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $detected_url = $protocol . $_SERVER['HTTP_HOST'];
        $detected_url = preg_replace('/:[0-9]+/', '', $detected_url); // Remove any port
        return rtrim($detected_url, '/');
    }
    
    // Default fallback
    return 'https://vibe-faveo-production.up.railway.app';
}

// Function to clear Laravel cache directories
function clear_laravel_cache() {
    $results = [];
    
    // Clear various Laravel caches
    $cache_directories = [
        '/var/www/html/bootstrap/cache/*.php',
        '/var/www/html/storage/framework/cache/data/*',
        '/var/www/html/storage/framework/views/*.php',
    ];
    
    foreach ($cache_directories as $pattern) {
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $results[] = "Deleted cache file: " . basename($file);
                    } else {
                        $results[] = "Failed to delete: " . basename($file);
                    }
                }
            }
        }
    }
    
    return $results;
}

// Function to update the .env file
function update_env_file($new_url) {
    $env_path = '/var/www/html/.env';
    
    if (!file_exists($env_path)) {
        return [
            'success' => false,
            'message' => '.env file not found at ' . $env_path
        ];
    }
    
    $env_content = file_get_contents($env_path);
    
    // Update APP_URL
    $env_content = preg_replace('/APP_URL=.*/m', "APP_URL=$new_url", $env_content);
    
    // Ensure APP_URL exists
    if (!preg_match('/APP_URL=/m', $env_content)) {
        $env_content .= "\nAPP_URL=$new_url\n";
    }
    
    if (file_put_contents($env_path, $env_content)) {
        return [
            'success' => true,
            'message' => '.env file updated successfully with URL: ' . $new_url
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to write to .env file'
        ];
    }
}

// Perform the fixes
$fix_results = [];
$detected_url = detect_url();
$current_db_url = 'Not checked';
$settings_updated = false;

if ($authorized && isset($_POST['fix_url'])) {
    $new_url = isset($_POST['new_url']) && !empty($_POST['new_url']) 
        ? trim($_POST['new_url'])
        : $detected_url;
    
    // Make sure URL doesn't have port or trailing slash
    $new_url = preg_replace('/:[0-9]+/', '', $new_url);
    $new_url = rtrim($new_url, '/');
    
    // 1. Update the database setting
    try {
        $pdo = get_database_connection();
        
        // Check if settings_system table exists
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('settings_system', $tables)) {
            // Get current URL setting
            $stmt = $pdo->query("SELECT url FROM settings_system LIMIT 1");
            $current_db_url = $stmt->fetchColumn();
            
            // Update URL in database
            $stmt = $pdo->prepare("UPDATE settings_system SET url = :url");
            $result = $stmt->execute([':url' => $new_url]);
            if ($result) {
                $fix_results[] = "✅ Database URL updated from '{$current_db_url}' to '{$new_url}'";
                $settings_updated = true;
            } else {
                $fix_results[] = "❌ Failed to update URL in database";
            }
        } else {
            $fix_results[] = "⚠️ settings_system table not found in database";
        }
        
        // Check if settings_ticket table exists and has a url field
        if (in_array('settings_ticket', $tables)) {
            try {
                // Get schema to check if url column exists
                $columns = $pdo->query("DESCRIBE settings_ticket")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('url', $columns)) {
                    $stmt = $pdo->prepare("UPDATE settings_ticket SET url = :url");
                    $result = $stmt->execute([':url' => $new_url]);
                    if ($result) {
                        $fix_results[] = "✅ Ticket settings URL updated to '{$new_url}'";
                    }
                }
            } catch (PDOException $e) {
                // Ignore errors if the column doesn't exist
            }
        }
        
        // Check if settings_email table exists and has a email_fetching_host field
        if (in_array('settings_email', $tables)) {
            try {
                $columns = $pdo->query("DESCRIBE settings_email")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('email_fetching_host', $columns)) {
                    $stmt = $pdo->prepare("UPDATE settings_email SET email_fetching_host = :url WHERE email_fetching_host LIKE '%localhost%' OR email_fetching_host LIKE '%:8080%'");
                    $result = $stmt->execute([':url' => $new_url]);
                    if ($result && $stmt->rowCount() > 0) {
                        $fix_results[] = "✅ Email settings host URL updated to '{$new_url}'";
                    }
                }
            } catch (PDOException $e) {
                // Ignore errors if the column doesn't exist
            }
        }
        
        // Check for any other tables that might have URLs
        foreach ($tables as $table) {
            try {
                // Only check tables that might have URL settings
                if (strpos($table, 'settings') === 0 || strpos($table, 'system') !== false) {
                    $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Look for likely URL columns
                    $url_columns = array_filter($columns, function($col) {
                        return strpos($col, 'url') !== false || 
                               strpos($col, 'host') !== false || 
                               strpos($col, 'website') !== false;
                    });
                    
                    foreach ($url_columns as $column) {
                        // Only update columns with localhost or port 8080 values
                        $stmt = $pdo->prepare("UPDATE `$table` SET `$column` = :url WHERE `$column` LIKE '%localhost%' OR `$column` LIKE '%:8080%'");
                        $result = $stmt->execute([':url' => $new_url]);
                        if ($result && $stmt->rowCount() > 0) {
                            $fix_results[] = "✅ Updated URL in $table.$column to '{$new_url}'";
                        }
                    }
                }
            } catch (PDOException $e) {
                // Skip tables with errors
            }
        }
    } catch (PDOException $e) {
        $fix_results[] = "❌ Database error: " . $e->getMessage();
    }
    
    // 2. Update the .env file
    $env_result = update_env_file($new_url);
    if ($env_result['success']) {
        $fix_results[] = "✅ " . $env_result['message'];
    } else {
        $fix_results[] = "❌ " . $env_result['message'];
    }
    
    // 3. Clear Laravel caches
    $cache_results = clear_laravel_cache();
    foreach ($cache_results as $result) {
        if (strpos($result, 'Failed') === false) {
            $fix_results[] = "✅ " . $result;
        } else {
            $fix_results[] = "❌ " . $result;
        }
    }
    
    // 4. Update bootstrap file to override APP_URL
    $bootstrap_file = '/var/www/html/public/db_bootstrap.php';
    if (file_exists($bootstrap_file)) {
        $bootstrap_content = file_get_contents($bootstrap_file);
        
        // Add APP_URL to bootstrap file
        $app_url_code = "\n// Override APP_URL for proper redirects\n\$_ENV['APP_URL'] = '{$new_url}';\nputenv('APP_URL={$new_url}');\n";
        
        if (strpos($bootstrap_content, "APP_URL") === false) {
            // Add APP_URL if it doesn't exist
            $bootstrap_content .= $app_url_code;
            if (file_put_contents($bootstrap_file, $bootstrap_content)) {
                $fix_results[] = "✅ Added APP_URL override to bootstrap file";
            } else {
                $fix_results[] = "❌ Failed to write to bootstrap file";
            }
        } else {
            // Update existing APP_URL value
            $bootstrap_content = preg_replace('/\$_ENV\[\'APP_URL\'\]\s*=\s*[^\n]+/', "\$_ENV['APP_URL'] = '{$new_url}'", $bootstrap_content);
            $bootstrap_content = preg_replace('/putenv\(\'APP_URL=[^\)]+\)/', "putenv('APP_URL={$new_url}')", $bootstrap_content);
            if (file_put_contents($bootstrap_file, $bootstrap_content)) {
                $fix_results[] = "✅ Updated APP_URL in bootstrap file";
            } else {
                $fix_results[] = "❌ Failed to update bootstrap file";
            }
        }
    } else {
        // Create a new minimal bootstrap file
        $bootstrap_content = "<?php\n// Set URL and prevent redirects\n\$_ENV['APP_URL'] = '{$new_url}';\nputenv('APP_URL={$new_url}');\n";
        if (file_put_contents($bootstrap_file, $bootstrap_content)) {
            $fix_results[] = "✅ Created new bootstrap file with APP_URL";
        } else {
            $fix_results[] = "❌ Failed to create bootstrap file";
        }
    }
    
    // 5. Force settings environment variables
    $config_override_file = '/var/www/html/config/app.php';
    if (file_exists($config_override_file)) {
        try {
            // Create a temporary override file
            $override_content = "<?php\n// URL override for app config\nreturn ['url' => '{$new_url}'];\n";
            $override_file = '/var/www/html/config/override-url.php';
            
            if (file_put_contents($override_file, $override_content)) {
                $fix_results[] = "✅ Created URL config override file";
            } else {
                $fix_results[] = "❌ Failed to create config override file";
            }
        } catch (Exception $e) {
            $fix_results[] = "❌ Error creating config override: " . $e->getMessage();
        }
    }
    
    // 6. Set the success flag based on results
    $success = $settings_updated && strpos(implode('', $fix_results), '❌') === false;
    
    // 7. Add some general guidance
    $fix_results[] = "";
    $fix_results[] = "⚠️ Important: You may need to restart the application to fully apply these changes.";
    $fix_results[] = "⚠️ Try clearing your browser cache or using incognito mode to test the changes.";
}

// Check current settings
$current_url = '';
$current_settings = null;
$env_app_url = getenv('APP_URL') ?: 'Not set';

if ($authorized) {
    try {
        $pdo = get_database_connection();
        
        // Get current settings
        $stmt = $pdo->query("SELECT * FROM settings_system LIMIT 1");
        $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_settings) {
            $current_url = $current_settings['url'];
            $current_db_url = $current_url;
        }
    } catch (PDOException $e) {
        $message = "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    }
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Comprehensive URL Redirect Fix for Faveo</title>
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
        }
        .error {
            color: red;
        }
        .warning {
            color: orange;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        button, input[type="submit"] {
            background: #336699;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover, input[type="submit"]:hover {
            background: #264d73;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        .results {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Comprehensive URL Redirect Fix for Faveo</h1>
        <p>This tool fixes URL redirection issues by updating all URL-related settings in the database, configuration files, and cache.</p>
        
        <?php if (!$authorized): ?>
        <div class="box">
            <h2>Authentication Required</h2>
            <p>Please enter the admin password to access this tool.</p>
            <form method="post">
                <div class="form-group">
                    <label for="auth_password">Password:</label>
                    <input type="password" id="auth_password" name="auth_password" required>
                </div>
                <button type="submit">Authenticate</button>
            </form>
        </div>
        <?php else: ?>
        
        <?php if (!empty($fix_results)): ?>
        <div class="box">
            <h2>Fix Results</h2>
            <div class="results">
                <?php foreach ($fix_results as $result): ?>
                    <div><?php echo htmlspecialchars($result); ?></div>
                <?php endforeach; ?>
            </div>
            <p class="<?php echo $success ? 'success' : 'warning'; ?>">
                <?php echo $success 
                    ? '✅ All fixes applied successfully. Try accessing the app now.' 
                    : '⚠️ Some fixes may not have been applied. Check the results above.'; ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Current URL Settings</h2>
            <p>Database URL: <strong><?php echo htmlspecialchars($current_db_url); ?></strong></p>
            <p>APP_URL Environment Variable: <strong><?php echo htmlspecialchars($env_app_url); ?></strong></p>
            <p>Auto-detected URL: <strong><?php echo htmlspecialchars($detected_url); ?></strong></p>
            
            <?php if (strpos($current_db_url, ':8080') !== false || strpos($current_db_url, 'localhost') !== false): ?>
            <p class="error">⚠️ Your current URL contains port 8080 or localhost, which is causing redirection issues.</p>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                <div class="form-group">
                    <label for="new_url">New URL (without port or trailing slash):</label>
                    <input type="text" id="new_url" name="new_url" value="<?php echo htmlspecialchars($detected_url); ?>" required>
                </div>
                <button type="submit" name="fix_url">Apply Comprehensive URL Fix</button>
            </form>
        </div>
        
        <div class="box">
            <h2>What This Tool Does</h2>
            <ol>
                <li>Updates URL in <strong>settings_system</strong> table</li>
                <li>Checks and updates URLs in other settings tables</li>
                <li>Updates the <strong>.env</strong> file's APP_URL setting</li>
                <li>Clears Laravel cache files to remove cached URLs</li>
                <li>Updates or creates bootstrap file to override APP_URL</li>
                <li>Creates config override file for app.url</li>
            </ol>
        </div>
        
        <div class="box">
            <h2>After Fix Steps</h2>
            <ol>
                <li>Try accessing the application at <a href="<?php echo htmlspecialchars($detected_url); ?>/public" target="_blank"><?php echo htmlspecialchars($detected_url); ?>/public</a></li>
                <li>If you still encounter issues, try clearing your browser cache or using incognito mode</li>
                <li>You may need to restart the application for all changes to take effect</li>
                <li>Check the database connection if you encounter database-related errors</li>
            </ol>
        </div>
        
        <div class="box">
            <h2>Navigation</h2>
            <ul>
                <li><a href="diagnose-facade.php">Diagnostic Tool</a></li>
                <li><a href="install-dependencies-fixed.php">Install Dependencies</a></li>
                <li><a href="fix-permissions.php">Fix Permissions</a></li>
                <li><a href="/public">Go to Faveo</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 