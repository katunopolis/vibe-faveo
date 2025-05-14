<?php
/**
 * Laravel Bootstrap Fix for Faveo
 * 
 * This script fixes Laravel bootstrap issues, including:
 * - Permissions on key directories
 * - Clearing bootstrap cache
 * - Fixing bootstrap/app.php if needed
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
 * Fix directory permissions for Laravel
 */
function fix_permissions() {
    $results = [];
    
    $dirs = [
        '/var/www/html/storage' => '775',
        '/var/www/html/storage/framework' => '775',
        '/var/www/html/storage/framework/sessions' => '775',
        '/var/www/html/storage/framework/views' => '775',
        '/var/www/html/storage/framework/cache' => '775',
        '/var/www/html/storage/logs' => '775',
        '/var/www/html/bootstrap/cache' => '775',
        '/var/www/html/public' => '755',
    ];
    
    // Check and create missing directories
    foreach ($dirs as $dir => $perm) {
        if (!file_exists($dir)) {
            if (mkdir($dir, octdec($perm), true)) {
                $results[] = "Created directory: $dir with permissions $perm";
            } else {
                $results[] = "Failed to create directory: $dir";
            }
        }
        
        // Set permissions
        if (file_exists($dir)) {
            if (chmod($dir, octdec($perm))) {
                $results[] = "Set permissions $perm on: $dir";
            } else {
                $results[] = "Failed to set permissions on: $dir";
            }
            
            // Try to set owner to www-data
            if (function_exists('posix_getpwnam')) {
                $www_data = posix_getpwnam('www-data');
                if ($www_data && isset($www_data['uid'])) {
                    if (chown($dir, $www_data['uid'])) {
                        $results[] = "Set owner to www-data on: $dir";
                    }
                }
            }
        }
    }
    
    return $results;
}

/**
 * Clear Laravel bootstrap cache
 */
function clear_bootstrap_cache() {
    $results = [];
    
    $files = glob('/var/www/html/bootstrap/cache/*.php');
    foreach ($files as $file) {
        if (unlink($file)) {
            $results[] = "Deleted cache file: " . basename($file);
        } else {
            $results[] = "Failed to delete cache file: " . basename($file);
        }
    }
    
    return $results;
}

/**
 * Check and fix bootstrap/app.php
 */
function fix_bootstrap_app() {
    $app_file = '/var/www/html/bootstrap/app.php';
    
    if (!file_exists($app_file)) {
        return [
            'success' => false,
            'message' => 'bootstrap/app.php not found'
        ];
    }
    
    $content = file_get_contents($app_file);
    $modified = false;
    
    // Check and fix the storage path
    if (strpos($content, "->useStoragePath(env('STORAGE_PATH'))") === false) {
        // Add storage path configuration
        $content = str_replace(
            '$app = new Illuminate\Foundation\Application(',
            '$app = new Illuminate\Foundation\Application(
    realpath(__DIR__.\'/../\')
);

// Set custom storage path from environment variable if present
if (env(\'STORAGE_PATH\')) {
    $app->useStoragePath(env(\'STORAGE_PATH\'))',
            $content
        );
        $modified = true;
    }
    
    // Fix any runtime configuration if needed
    if (strpos($content, 'if (env(\'RUNTIME_CONFIG\')') === false) {
        // Add runtime configuration support
        $content = str_replace(
            'return $app;',
            '// Apply any runtime configurations
if (env(\'RUNTIME_CONFIG\') && file_exists(env(\'RUNTIME_CONFIG\'))) {
    include env(\'RUNTIME_CONFIG\');
}

return $app;',
            $content
        );
        $modified = true;
    }
    
    // Save the file if modified
    if ($modified) {
        if (file_put_contents($app_file, $content)) {
            return [
                'success' => true,
                'message' => 'bootstrap/app.php updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to write to bootstrap/app.php'
            ];
        }
    }
    
    return [
        'success' => true,
        'message' => 'bootstrap/app.php already configured correctly'
    ];
}

// Process the form
if ($authorized && isset($_POST['fix_bootstrap'])) {
    try {
        // 1. Fix permissions
        $permission_results = fix_permissions();
        $results = array_merge($results, array_map(function($r) { return "📂 " . $r; }, $permission_results));
        
        // 2. Clear bootstrap cache
        $cache_results = clear_bootstrap_cache();
        $results = array_merge($results, array_map(function($r) { return "🗑️ " . $r; }, $cache_results));
        
        // 3. Fix bootstrap app
        $app_result = fix_bootstrap_app();
        $results[] = ($app_result['success'] ? "✅ " : "❌ ") . $app_result['message'];
        
        // Set success status
        $success = true;
        $message = "Bootstrap configuration has been fixed successfully.";
        
    } catch (Exception $e) {
        $success = false;
        $message = "Error: " . $e->getMessage();
        $results[] = "❌ " . $e->getMessage();
    }
}

// Check current settings for display
$current_status = [];

if ($authorized) {
    // Check key directories
    $dirs = [
        '/var/www/html/storage',
        '/var/www/html/storage/framework/sessions',
        '/var/www/html/storage/framework/views',
        '/var/www/html/storage/framework/cache',
        '/var/www/html/bootstrap/cache',
    ];
    
    foreach ($dirs as $dir) {
        if (file_exists($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            $writable = is_writable($dir);
            $current_status[$dir] = [
                'exists' => true,
                'permissions' => $perms,
                'writable' => $writable
            ];
        } else {
            $current_status[$dir] = [
                'exists' => false
            ];
        }
    }
    
    // Check bootstrap/app.php
    $app_file = '/var/www/html/bootstrap/app.php';
    if (file_exists($app_file)) {
        $content = file_get_contents($app_file);
        $current_status['bootstrap_app'] = [
            'exists' => true,
            'size' => filesize($app_file),
            'has_storage_path' => strpos($content, "->useStoragePath") !== false,
            'has_runtime_config' => strpos($content, 'RUNTIME_CONFIG') !== false
        ];
    } else {
        $current_status['bootstrap_app'] = [
            'exists' => false
        ];
    }
    
    // Check bootstrap cache files
    $cache_files = glob('/var/www/html/bootstrap/cache/*.php');
    $current_status['cache_files'] = count($cache_files);
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Bootstrap Fixer</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
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
        <h1>Faveo Bootstrap Fixer</h1>
        
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
            <h2>Current Bootstrap Status</h2>
            
            <h3>Directory Permissions</h3>
            <table>
                <tr>
                    <th>Directory</th>
                    <th>Status</th>
                    <th>Permissions</th>
                    <th>Writable</th>
                </tr>
                <?php foreach ($current_status as $dir => $status): ?>
                <?php if (is_array($status) && $dir !== 'bootstrap_app'): ?>
                <tr>
                    <td><?php echo htmlspecialchars($dir); ?></td>
                    <td class="<?php echo $status['exists'] ? 'success' : 'error'; ?>">
                        <?php echo $status['exists'] ? 'Exists' : 'Missing'; ?>
                    </td>
                    <td><?php echo $status['exists'] ? $status['permissions'] : '-'; ?></td>
                    <td class="<?php echo ($status['exists'] && $status['writable']) ? 'success' : 'error'; ?>">
                        <?php echo ($status['exists'] && $status['writable']) ? 'Yes' : 'No'; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </table>
            
            <h3>Bootstrap Configuration</h3>
            <?php if (isset($current_status['bootstrap_app'])): ?>
            <?php $bootstrap_app = $current_status['bootstrap_app']; ?>
            <p>bootstrap/app.php: <span class="<?php echo $bootstrap_app['exists'] ? 'success' : 'error'; ?>">
                <?php echo $bootstrap_app['exists'] ? 'Exists' : 'Missing'; ?>
            </span></p>
            
            <?php if ($bootstrap_app['exists']): ?>
            <ul>
                <li>Custom Storage Path: <span class="<?php echo $bootstrap_app['has_storage_path'] ? 'success' : 'warning'; ?>">
                    <?php echo $bootstrap_app['has_storage_path'] ? 'Configured' : 'Not Configured'; ?>
                </span></li>
                <li>Runtime Configuration: <span class="<?php echo $bootstrap_app['has_runtime_config'] ? 'success' : 'warning'; ?>">
                    <?php echo $bootstrap_app['has_runtime_config'] ? 'Configured' : 'Not Configured'; ?>
                </span></li>
            </ul>
            <?php endif; ?>
            <?php endif; ?>
            
            <p>Cache Files: <?php echo $current_status['cache_files']; ?></p>
        </div>
        
        <div class="box">
            <h2>Fix Bootstrap Configuration</h2>
            <p>This tool will fix directory permissions, clear cache files, and update bootstrap/app.php if needed.</p>
            
            <form method="post">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                <button type="submit" name="fix_bootstrap" value="1">Fix Bootstrap Configuration</button>
            </form>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html> 