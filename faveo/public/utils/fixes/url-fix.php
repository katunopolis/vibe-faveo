<?php
/**
 * Comprehensive URL Redirect Fix for Faveo
 * 
 * This script combines functionality from multiple URL fix scripts:
 * - Fixes database URLs
 * - Updates .env file
 * - Clears Laravel cache
 * - Updates configuration files
 * - Fixes URL redirects
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

/**
 * Database connection function that tries multiple connection methods
 */
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
            // Get connection details from environment if available
            $host = getenv('MYSQLHOST') ?: 'yamabiko.proxy.rlwy.net';
            $port = getenv('MYSQLPORT') ?: '52501';
            $database = getenv('MYSQLDATABASE') ?: 'railway';
            $username = getenv('MYSQLUSER') ?: 'root';
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

/**
 * Auto-detect the correct URL for the application
 */
function detect_url() {
    // Try to get from environment first
    $env_url = getenv('APP_URL');
    if ($env_url && !strpos($env_url, 'localhost') && !strpos($env_url, '8080')) {
        return rtrim($env_url, '/');
    }
    
    // Try to get from Railway environment variables
    $railway_domain = getenv('RAILWAY_PUBLIC_DOMAIN');
    if ($railway_domain) {
        return 'https://' . $railway_domain;
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

/**
 * Clear Laravel cache directories
 */
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

/**
 * Update the .env file with the new URL
 */
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

/**
 * Update all relevant database tables with new URL
 */
function update_database_urls($pdo, $new_url) {
    $results = [];
    
    // Get all tables in the database
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Update settings_system table
    if (in_array('settings_system', $tables)) {
        try {
            // Get current URL setting
            $stmt = $pdo->query("SELECT url FROM settings_system LIMIT 1");
            $current_db_url = $stmt->fetchColumn();
            
            // Update URL in database
            $stmt = $pdo->prepare("UPDATE settings_system SET url = :url");
            $result = $stmt->execute([':url' => $new_url]);
            if ($result) {
                $results[] = "✅ settings_system URL updated from '{$current_db_url}' to '{$new_url}'";
            } else {
                $results[] = "❌ Failed to update URL in settings_system";
            }
        } catch (PDOException $e) {
            $results[] = "❌ Error updating settings_system: " . $e->getMessage();
        }
    }
    
    // Update settings_ticket table
    if (in_array('settings_ticket', $tables)) {
        try {
            // Check if url column exists
            $columns = $pdo->query("DESCRIBE settings_ticket")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('url', $columns)) {
                $stmt = $pdo->prepare("UPDATE settings_ticket SET url = :url");
                $result = $stmt->execute([':url' => $new_url]);
                if ($result) {
                    $results[] = "✅ settings_ticket URL updated to '{$new_url}'";
                }
            }
        } catch (PDOException $e) {
            $results[] = "❌ Error updating settings_ticket: " . $e->getMessage();
        }
    }
    
    // Update settings_email table
    if (in_array('settings_email', $tables)) {
        try {
            // Check for relevant columns
            $columns = $pdo->query("DESCRIBE settings_email")->fetchAll(PDO::FETCH_COLUMN);
            $url_columns = array_intersect($columns, ['email_fetching_host', 'system_email']);
            
            if (!empty($url_columns)) {
                $updates = [];
                foreach ($url_columns as $column) {
                    $stmt = $pdo->prepare("UPDATE settings_email SET $column = :url");
                    $result = $stmt->execute([':url' => $new_url]);
                    if ($result) {
                        $updates[] = $column;
                    }
                }
                if (!empty($updates)) {
                    $results[] = "✅ settings_email URLs updated: " . implode(', ', $updates);
                }
            }
        } catch (PDOException $e) {
            $results[] = "❌ Error updating settings_email: " . $e->getMessage();
        }
    }
    
    return $results;
}

// Get the detected URL
$detected_url = detect_url();

// Process the form
if ($authorized && isset($_POST['fix_url'])) {
    $new_url = isset($_POST['new_url']) && !empty($_POST['new_url']) 
        ? trim($_POST['new_url'])
        : $detected_url;
    
    // Make sure URL doesn't have port or trailing slash
    $new_url = preg_replace('/:[0-9]+/', '', $new_url);
    $new_url = rtrim($new_url, '/');
    
    try {
        // 1. Update database URLs
        $pdo = get_database_connection();
        $db_results = update_database_urls($pdo, $new_url);
        $results = array_merge($results, $db_results);
        
        // 2. Update .env file
        $env_result = update_env_file($new_url);
        $results[] = $env_result['success'] 
            ? "✅ " . $env_result['message']
            : "❌ " . $env_result['message'];
        
        // 3. Clear Laravel cache
        $cache_results = clear_laravel_cache();
        $results = array_merge($results, array_map(function($r) { return "✅ " . $r; }, $cache_results));
        
        // Set success status
        $success = true;
        $message = "URL settings have been successfully updated to: $new_url";
        
    } catch (Exception $e) {
        $success = false;
        $message = "Error: " . $e->getMessage();
        $results[] = "❌ " . $e->getMessage();
    }
}

// Get current settings for display
$current_settings = [];
$current_url = 'Unknown';

if ($authorized) {
    try {
        $pdo = get_database_connection();
        
        // Get settings_system
        $stmt = $pdo->query("SELECT * FROM settings_system LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            $current_settings['system'] = $settings;
            $current_url = $settings['url'] ?? 'Not set';
        }
        
        // Get .env file APP_URL
        $env_path = '/var/www/html/.env';
        if (file_exists($env_path)) {
            $env_content = file_get_contents($env_path);
            preg_match('/APP_URL=(.*)/', $env_content, $matches);
            $current_settings['env_url'] = $matches[1] ?? 'Not set';
        }
    } catch (PDOException $e) {
        // Ignore database errors for display
    }
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo URL Fixer</title>
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
        <h1>Faveo URL Fixer</h1>
        
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
        
        <?php if (!empty($message)): ?>
        <div class="box">
            <h2>Result</h2>
            <p class="<?php echo $success ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></p>
            
            <?php if (!empty($results)): ?>
            <h3>Details:</h3>
            <ul class="results">
                <?php foreach ($results as $result): ?>
                <li><?php echo htmlspecialchars($result); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Current URL Settings</h2>
            <p>Current URL in database: <strong><?php echo htmlspecialchars($current_url); ?></strong></p>
            <?php if (isset($current_settings['env_url'])): ?>
            <p>Current URL in .env file: <strong><?php echo htmlspecialchars($current_settings['env_url']); ?></strong></p>
            <?php endif; ?>
            <p>Detected URL from browser: <strong><?php echo htmlspecialchars($detected_url); ?></strong></p>
        </div>
        
        <div class="box">
            <h2>Update URL Settings</h2>
            <p>This tool will update all URL references in the database and configuration files.</p>
            
            <form method="post">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                
                <div class="form-group">
                    <label for="new_url">New URL:</label>
                    <input type="text" id="new_url" name="new_url" value="<?php echo htmlspecialchars($detected_url); ?>" placeholder="https://your-app-url.com">
                    <small>Leave empty to use the detected URL: <?php echo htmlspecialchars($detected_url); ?></small>
                </div>
                
                <button type="submit" name="fix_url" value="1">Update All URL Settings</button>
            </form>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html> 