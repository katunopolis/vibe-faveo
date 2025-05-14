<?php
/**
 * This script fixes common issues with Laravel bootstrapping.
 *
 * It helps solve the "facade root has not been set" error by:
 * 1. Creating necessary directories
 * 2. Clearing cached configurations
 * 3. Patching index.php to include bootstrap-app.php
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define common paths
$laravel_root = realpath(__DIR__ . '/..');
$bootstrap_file = $laravel_root . '/bootstrap/app.php';
$index_file = __DIR__ . '/index.php';
$storage_dir = $laravel_root . '/storage';
$cache_dir = $laravel_root . '/bootstrap/cache';
$bootstrap_app_file = __DIR__ . '/bootstrap-app.php';

// Check if bootstrap-app.php exists, create if not
if (!file_exists($bootstrap_app_file)) {
    file_put_contents($bootstrap_app_file, '<?php
/**
 * This file creates a proper Laravel application instance and sets it as the facade root.
 * Include this at the beginning of your index.php file to prevent "facade root has not been set" errors.
 */

// Set display errors for debugging
ini_set(\'display_errors\', 1);
ini_set(\'display_startup_errors\', 1);
error_reporting(E_ALL);

// Define common paths
$laravel_root = realpath(__DIR__ . "/..");
$bootstrap_file = $laravel_root . "/bootstrap/app.php";
$storage_dir = $laravel_root . "/storage";
$cache_dir = $laravel_root . "/bootstrap/cache";

// Create critical directories if they don\'t exist
if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0755, true);
}

$storage_subdirs = [
    "/framework/cache/data",
    "/framework/sessions",
    "/framework/views",
    "/logs",
    "/app/public"
];

foreach ($storage_subdirs as $subdir) {
    $dir = $storage_dir . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Attempt to create Laravel application instance
try {
    // Check if bootstrap file exists
    if (file_exists($bootstrap_file)) {
        // Load bootstrap file
        $app = require_once $bootstrap_file;
        
        // Manually set the facade root
        if (class_exists("Illuminate\\Support\\Facades\\Facade")) {
            Illuminate\\Support\\Facades\\Facade::setFacadeApplication($app);
            
            // Initialize key facades
            if (class_exists("Illuminate\\Support\\Facades\\App")) {
                Illuminate\\Support\\Facades\\App::getFacadeRoot();
            }
            
            if (class_exists("Illuminate\\Support\\Facades\\Config")) {
                Illuminate\\Support\\Facades\\Config::getFacadeRoot();
            }
        }
    }
} catch (Exception $e) {
    // Don\'t throw errors here as we\'re trying to fix errors
    // Just continue with normal bootstrap process
}

// Setup environment variables for database connection using already defined connections
$database_host = getenv("DB_HOST") ?: "localhost";
$database_port = getenv("DB_PORT") ?: "3306";
$database_name = getenv("DB_DATABASE") ?: "faveo";
$database_user = getenv("DB_USERNAME") ?: "root";
$database_pass = getenv("DB_PASSWORD") ?: "";

// Set DB environment variables
$_ENV["DB_CONNECTION"] = "mysql";
$_ENV["DB_HOST"] = $database_host;
$_ENV["DB_PORT"] = $database_port;
$_ENV["DB_DATABASE"] = $database_name;
$_ENV["DB_USERNAME"] = $database_user;
$_ENV["DB_PASSWORD"] = $database_pass;

putenv("DB_CONNECTION=mysql");
putenv("DB_HOST=" . $database_host);
putenv("DB_PORT=" . $database_port);
putenv("DB_DATABASE=" . $database_name);
putenv("DB_USERNAME=" . $database_user);
putenv("DB_PASSWORD=" . $database_pass);

// Prevent further errors by cleaning cached configuration
$cache_files = glob($cache_dir . "/*.php");
foreach ($cache_files as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}

// Return true to indicate inclusion was successful
return true;
');
}

// Results array to store success/failure messages
$results = [
    'success' => [],
    'error' => []
];

// Create critical directories
if (!is_dir($storage_dir)) {
    if (mkdir($storage_dir, 0755, true)) {
        $results['success'][] = "Created storage directory";
    } else {
        $results['error'][] = "Failed to create storage directory";
    }
}

$storage_subdirs = [
    "/framework/cache/data",
    "/framework/sessions",
    "/framework/views",
    "/logs",
    "/app/public"
];

foreach ($storage_subdirs as $subdir) {
    $dir = $storage_dir . $subdir;
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            $results['success'][] = "Created directory: {$subdir}";
        } else {
            $results['error'][] = "Failed to create directory: {$subdir}";
        }
    }
}

if (!is_dir($cache_dir)) {
    if (mkdir($cache_dir, 0755, true)) {
        $results['success'][] = "Created bootstrap/cache directory";
    } else {
        $results['error'][] = "Failed to create bootstrap/cache directory";
    }
}

// Fix permissions
$dirs_to_fix = [
    $storage_dir,
    $cache_dir
];

foreach ($dirs_to_fix as $dir) {
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            $results['success'][] = "Fixed permissions for: " . basename($dir);
        } else {
            $results['error'][] = "Failed to fix permissions for: " . basename($dir);
        }
    }
}

// Clear cache files
$cache_files = glob($cache_dir . "/*.php");
foreach ($cache_files as $file) {
    if (is_file($file)) {
        if (unlink($file)) {
            $results['success'][] = "Cleared cache file: " . basename($file);
        } else {
            $results['error'][] = "Failed to clear cache file: " . basename($file);
        }
    }
}

// Check if index.php includes bootstrap-app.php
$patched = false;
if (file_exists($index_file)) {
    $index_content = file_get_contents($index_file);
    if (!strstr($index_content, 'bootstrap-app.php')) {
        // Backup the original file
        if (!file_exists($index_file . '.bak')) {
            copy($index_file, $index_file . '.bak');
            $results['success'][] = "Created backup of index.php";
        }
        
        // Add the bootstrap-app.php include at the beginning of the file
        $new_content = "<?php require_once __DIR__ . '/bootstrap-app.php'; ?>\n" . $index_content;
        
        if (file_put_contents($index_file, $new_content)) {
            $results['success'][] = "Patched index.php to include bootstrap-app.php";
            $patched = true;
        } else {
            $results['error'][] = "Failed to patch index.php";
        }
    } else {
        $results['success'][] = "index.php already includes bootstrap-app.php";
        $patched = true;
    }
} else {
    $results['error'][] = "index.php not found";
}

// Display the results in a formatted page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laravel Bootstrap Fixer</title>
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
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Laravel Bootstrap Fixer</h1>
        <p>This tool fixes common issues with Laravel bootstrapping, particularly the "facade root has not been set" error.</p>
        
        <div class="box">
            <h2>Diagnostic Results</h2>
            
            <?php if (count($results['success']) > 0): ?>
                <h3 class="success">Successful Actions:</h3>
                <ul>
                    <?php foreach ($results['success'] as $message): ?>
                        <li class="success"><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (count($results['error']) > 0): ?>
                <h3 class="error">Errors:</h3>
                <ul>
                    <?php foreach ($results['error'] as $message): ?>
                        <li class="error"><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div class="overall-status">
                <?php if (count($results['error']) === 0): ?>
                    <h3 class="success">✓ All fixes applied successfully.</h3>
                <?php else: ?>
                    <h3 class="error">✗ Some fixes failed. See errors above.</h3>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="box">
            <h2>Next Steps</h2>
            <ul>
                <?php if ($patched): ?>
                    <li>Try accessing the <a href="/public">main application</a> again.</li>
                <?php else: ?>
                    <li class="error">You need to manually add <code>require_once __DIR__ . '/bootstrap-app.php';</code> at the beginning of your index.php file.</li>
                <?php endif; ?>
                <li>If you're still seeing issues, try the <a href="diagnose-facade.php">diagnostic tool</a> for more detailed analysis.</li>
                <li>You may need to <a href="install-dependencies.php">reinstall dependencies</a> if you're missing vendor files.</li>
                <li>Check <a href="fix-permissions.php">file permissions</a> to ensure Laravel can write to important directories.</li>
            </ul>
        </div>
        
        <div class="box">
            <h2>Navigation</h2>
            <ul>
                <li><a href="diagnose-facade.php">Facade Diagnostic Tool</a></li>
                <li><a href="install-dependencies.php">Install Dependencies</a></li>
                <li><a href="fix-permissions.php">Fix Permissions</a></li>
                <li><a href="/public">Go to Application</a></li>
            </ul>
        </div>
    </div>
</body>
</html>