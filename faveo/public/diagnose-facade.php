<?php
/**
 * Facade Root Diagnostic Tool
 * 
 * This script diagnoses and attempts to fix the "facade root has not been set" error.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Store diagnostic results
$diagnostic_results = [];

// After loading the diagnostic results, add this check
$composer_failed = file_exists(__DIR__ . '/needs_composer_install');

// Start HTML output
function start_html() {
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Facade Root Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; }
        h1, h2, h3 { color: #336699; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .test-result { margin-bottom: 10px; padding: 10px; border-radius: 4px; }
        .test-success { background-color: #dff0d8; border: 1px solid #d0e9c6; }
        .test-error { background-color: #f2dede; border: 1px solid #ebccd1; }
        .test-warning { background-color: #fcf8e3; border: 1px solid #faf2cc; }
        .test-info { background-color: #d9edf7; border: 1px solid #bce8f1; }
        .actions { margin-top: 20px; }
        .actions a { display: inline-block; margin-right: 10px; padding: 8px 15px; background: #336699; color: white; text-decoration: none; border-radius: 4px; }
        .actions a:hover { background: #264d73; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Facade Root Diagnostic Tool</h1>';

    // At the beginning of the HTML output, after the first box
    if ($composer_failed) {
        echo "<div class='test-result test-error'>";
        echo "<strong>⚠ Composer Installation Failed During Deployment</strong>";
        echo "<div class='details'>The bootstrap script detected a failure during Composer dependency installation. Please use the Dependency Installer to fix this issue.</div>";
        echo "</div>";
        
        echo "<div class='actions' style='margin-bottom: 20px;'>";
        echo "<a href='install-dependencies.php' style='background: #ff6600; font-weight: bold;'>Run Dependency Installer Now</a>";
        echo "</div>";
    }
}

// End HTML output
function end_html() {
    echo '    </div>
</body>
</html>';
}

// Format result with appropriate styling
function format_result($status, $message, $details = '') {
    $class = 'test-' . $status;
    $icon = ($status === 'success') ? '✓' : (($status === 'error') ? '✗' : (($status === 'warning') ? '⚠' : 'ℹ'));
    
    echo "<div class='test-result $class'>";
    echo "<strong>$icon $message</strong>";
    if (!empty($details)) {
        echo "<div class='details'>" . nl2br(htmlspecialchars($details)) . "</div>";
    }
    echo "</div>";
}

// Check if a class exists
function check_class($class_name) {
    global $diagnostic_results;
    
    if (class_exists($class_name)) {
        $diagnostic_results[] = ['status' => 'success', 'message' => "Class $class_name exists", 'details' => ''];
        return true;
    } else {
        $diagnostic_results[] = ['status' => 'error', 'message' => "Class $class_name does not exist", 'details' => ''];
        return false;
    }
}

// Check if a file exists and is readable
function check_file($file_path, $description) {
    global $diagnostic_results;
    
    $file_exists = file_exists($file_path);
    $readable = $file_exists && is_readable($file_path);
    
    if ($file_exists && $readable) {
        $diagnostic_results[] = ['status' => 'success', 'message' => "$description exists and is readable", 'details' => "Path: $file_path"];
        return true;
    } else {
        $status = !$file_exists ? 'error' : 'warning';
        $message = !$file_exists ? "$description does not exist" : "$description exists but is not readable";
        $diagnostic_results[] = ['status' => $status, 'message' => $message, 'details' => "Path: $file_path"];
        return false;
    }
}

// Function to test if Laravel is loaded
function test_laravel_loaded() {
    global $diagnostic_results;
    
    $required_classes = [
        'Illuminate\Foundation\Application',
        'Illuminate\Support\Facades\Facade',
        'Illuminate\Contracts\Http\Kernel',
        'Illuminate\Http\Request'
    ];
    
    $all_loaded = true;
    
    foreach ($required_classes as $class) {
        if (!check_class($class)) {
            $all_loaded = false;
        }
    }
    
    if ($all_loaded) {
        $diagnostic_results[] = ['status' => 'success', 'message' => 'Laravel framework appears to be properly loaded', 'details' => ''];
    } else {
        $diagnostic_results[] = ['status' => 'error', 'message' => 'Some critical Laravel classes are missing', 'details' => 'This indicates a problem with the autoloader or missing vendor files.'];
    }
    
    return $all_loaded;
}

// Function to test bootstrap/app.php
function test_bootstrap_file() {
    global $diagnostic_results;
    
    $bootstrap_path = realpath(__DIR__ . '/../bootstrap/app.php');
    
    if (check_file($bootstrap_path, 'Laravel bootstrap file')) {
        $bootstrap_content = file_get_contents($bootstrap_path);
        
        if (strpos($bootstrap_content, 'new Illuminate\Foundation\Application') !== false) {
            $diagnostic_results[] = ['status' => 'success', 'message' => 'Bootstrap file contains expected Application initialization', 'details' => ''];
            return true;
        } else {
            $diagnostic_results[] = ['status' => 'warning', 'message' => 'Bootstrap file might be invalid or corrupted', 'details' => 'The file does not contain expected application initialization code.'];
            return false;
        }
    }
    
    return false;
}

// Function to test facade initialization
function test_facade_initialization() {
    global $diagnostic_results;
    
    // First, ensure we have the Application class
    if (!class_exists('Illuminate\Foundation\Application')) {
        $diagnostic_results[] = ['status' => 'error', 'message' => 'Cannot test facade initialization', 'details' => 'The Application class is not available.'];
        return false;
    }
    
    // Try to create a new application instance
    try {
        $app = new Illuminate\Foundation\Application(realpath(__DIR__ . '/..'));
        $diagnostic_results[] = ['status' => 'success', 'message' => 'Successfully created Application instance', 'details' => ''];
        
        // Try to set it as the facade root
        if (class_exists('Illuminate\Support\Facades\Facade')) {
            try {
                Illuminate\Support\Facades\Facade::setFacadeApplication($app);
                $diagnostic_results[] = ['status' => 'success', 'message' => 'Successfully set facade root', 'details' => ''];
                
                // Try to use a facade
                try {
                    // Use a simple facade that doesn't require a lot of setup
                    $app_env = Illuminate\Support\Facades\App::environment();
                    $diagnostic_results[] = ['status' => 'success', 'message' => 'Successfully used App facade', 'details' => "Environment: $app_env"];
                    return true;
                } catch (Exception $e) {
                    $diagnostic_results[] = ['status' => 'warning', 'message' => 'Failed to use App facade', 'details' => "Error: " . $e->getMessage()];
                }
            } catch (Exception $e) {
                $diagnostic_results[] = ['status' => 'error', 'message' => 'Failed to set facade root', 'details' => "Error: " . $e->getMessage()];
            }
        } else {
            $diagnostic_results[] = ['status' => 'error', 'message' => 'Facade class not found', 'details' => 'The Illuminate\Support\Facades\Facade class is not available.'];
        }
    } catch (Exception $e) {
        $diagnostic_results[] = ['status' => 'error', 'message' => 'Failed to create Application instance', 'details' => "Error: " . $e->getMessage()];
    }
    
    return false;
}

// Function to check index.php for bootstrap include
function test_index_file() {
    global $diagnostic_results;
    
    $index_path = __DIR__ . '/index.php';
    
    if (check_file($index_path, 'Main index file')) {
        $index_content = file_get_contents($index_path);
        
        if (strpos($index_content, 'bootstrap-app.php') !== false) {
            $diagnostic_results[] = ['status' => 'success', 'message' => 'Index file includes bootstrap-app.php', 'details' => ''];
            return true;
        } else {
            $diagnostic_results[] = ['status' => 'warning', 'message' => 'Index file does not include bootstrap-app.php', 'details' => 'This may be causing the facade root issue.'];
            return false;
        }
    }
    
    return false;
}

// Function to check storage permissions
function test_storage_permissions() {
    global $diagnostic_results;
    
    $storage_path = realpath(__DIR__ . '/../storage');
    
    if (check_file($storage_path, 'Storage directory')) {
        if (is_writable($storage_path)) {
            $diagnostic_results[] = ['status' => 'success', 'message' => 'Storage directory is writable', 'details' => ''];
            
            // Check key storage subdirectories
            $storage_subdirs = [
                'app' => $storage_path . '/app',
                'framework' => $storage_path . '/framework',
                'logs' => $storage_path . '/logs',
                'framework/cache' => $storage_path . '/framework/cache',
                'framework/sessions' => $storage_path . '/framework/sessions',
                'framework/views' => $storage_path . '/framework/views'
            ];
            
            $subdirs_ok = true;
            
            foreach ($storage_subdirs as $name => $path) {
                if (!file_exists($path)) {
                    $diagnostic_results[] = ['status' => 'warning', 'message' => "Storage subdirectory $name does not exist", 'details' => 'This may cause issues with Laravel functionality.'];
                    $subdirs_ok = false;
                } else if (!is_writable($path)) {
                    $diagnostic_results[] = ['status' => 'warning', 'message' => "Storage subdirectory $name is not writable", 'details' => 'This may cause issues with Laravel functionality.'];
                    $subdirs_ok = false;
                }
            }
            
            if ($subdirs_ok) {
                $diagnostic_results[] = ['status' => 'success', 'message' => 'All storage subdirectories exist and are writable', 'details' => ''];
            }
            
            return $subdirs_ok;
        } else {
            $diagnostic_results[] = ['status' => 'error', 'message' => 'Storage directory is not writable', 'details' => 'This will cause issues with Laravel functionality.'];
            return false;
        }
    }
    
    return false;
}

// Function to check bootstrap cache permissions
function test_bootstrap_cache_permissions() {
    global $diagnostic_results;
    
    $bootstrap_cache_path = realpath(__DIR__ . '/../bootstrap/cache');
    
    if (check_file($bootstrap_cache_path, 'Bootstrap cache directory')) {
        if (is_writable($bootstrap_cache_path)) {
            $diagnostic_results[] = ['status' => 'success', 'message' => 'Bootstrap cache directory is writable', 'details' => ''];
            return true;
        } else {
            $diagnostic_results[] = ['status' => 'error', 'message' => 'Bootstrap cache directory is not writable', 'details' => 'This may prevent Laravel from caching configurations.'];
            return false;
        }
    } else {
        // Try to create the directory
        $bootstrap_path = realpath(__DIR__ . '/../bootstrap');
        if (is_writable($bootstrap_path)) {
            if (mkdir($bootstrap_path . '/cache', 0755, true)) {
                $diagnostic_results[] = ['status' => 'success', 'message' => 'Successfully created bootstrap cache directory', 'details' => ''];
                return true;
            } else {
                $diagnostic_results[] = ['status' => 'error', 'message' => 'Failed to create bootstrap cache directory', 'details' => 'Error: ' . error_get_last()['message']];
                return false;
            }
        } else {
            $diagnostic_results[] = ['status' => 'error', 'message' => 'Bootstrap directory is not writable', 'details' => 'Cannot create the cache directory.'];
            return false;
        }
    }
    
    return false;
}

// Function to check environment configuration
function test_environment_config() {
    global $diagnostic_results;
    
    $env_path = realpath(__DIR__ . '/../.env');
    
    if (check_file($env_path, '.env file')) {
        $env_content = file_get_contents($env_path);
        
        // Check for critical environment variables
        $critical_vars = [
            'APP_KEY' => 'Application encryption key',
            'DB_CONNECTION' => 'Database connection type',
            'DB_HOST' => 'Database host',
            'DB_DATABASE' => 'Database name',
            'DB_USERNAME' => 'Database username'
        ];
        
        $missing_vars = [];
        
        foreach ($critical_vars as $var => $description) {
            if (strpos($env_content, "$var=") === false) {
                $missing_vars[] = "$var ($description)";
            }
        }
        
        if (empty($missing_vars)) {
            $diagnostic_results[] = ['status' => 'success', 'message' => 'All critical environment variables are defined', 'details' => ''];
            return true;
        } else {
            $diagnostic_results[] = ['status' => 'warning', 'message' => 'Some critical environment variables are missing', 'details' => 'Missing variables: ' . implode(', ', $missing_vars)];
            return false;
        }
    } else {
        // Check if we have environment variables set through other means
        $app_key = getenv('APP_KEY');
        $db_connection = getenv('DB_CONNECTION');
        
        if ($app_key && $db_connection) {
            $diagnostic_results[] = ['status' => 'success', 'message' => 'Environment variables are set through system environment', 'details' => 'No .env file required.'];
            return true;
        } else {
            $diagnostic_results[] = ['status' => 'warning', 'message' => 'No .env file and environment variables may not be set', 'details' => 'This might cause issues with application configuration.'];
            return false;
        }
    }
    
    return false;
}

// Function to apply fixes
function apply_fixes() {
    global $diagnostic_results;
    
    $fixes_applied = [];
    
    // Fix 1: Patch index.php to include bootstrap-app.php
    $index_path = __DIR__ . '/index.php';
    if (file_exists($index_path) && is_writable($index_path)) {
        $index_content = file_get_contents($index_path);
        
        if (strpos($index_content, 'bootstrap-app.php') === false) {
            $bootstrap_include = "// Bootstrap the Laravel application environment\nrequire __DIR__.'/bootstrap-app.php';";
            $new_content = preg_replace('/<\?php/', "<?php\n$bootstrap_include\n", $index_content, 1);
            
            if (file_put_contents($index_path, $new_content) !== false) {
                $fixes_applied[] = 'Patched index.php to include bootstrap-app.php';
            } else {
                $diagnostic_results[] = ['status' => 'error', 'message' => 'Failed to patch index.php', 'details' => 'Error: ' . error_get_last()['message']];
            }
        }
    }
    
    // Fix 2: Create storage directories if missing
    $storage_path = realpath(__DIR__ . '/../storage');
    if ($storage_path && is_writable($storage_path)) {
        $storage_subdirs = [
            'app/public',
            'framework/cache',
            'framework/sessions',
            'framework/views',
            'logs'
        ];
        
        foreach ($storage_subdirs as $dir) {
            $full_path = $storage_path . '/' . $dir;
            if (!file_exists($full_path)) {
                if (mkdir($full_path, 0755, true)) {
                    $fixes_applied[] = "Created storage directory: $dir";
                }
            }
        }
    }
    
    // Fix 3: Create bootstrap/cache directory if missing
    $bootstrap_path = realpath(__DIR__ . '/../bootstrap');
    $bootstrap_cache_path = "$bootstrap_path/cache";
    if ($bootstrap_path && is_writable($bootstrap_path) && !file_exists($bootstrap_cache_path)) {
        if (mkdir($bootstrap_cache_path, 0755, true)) {
            $fixes_applied[] = "Created bootstrap/cache directory";
        }
    }
    
    // Fix 4: Clear configuration cache
    $config_cached_path = "$bootstrap_cache_path/config.php";
    if (file_exists($config_cached_path)) {
        if (unlink($config_cached_path)) {
            $fixes_applied[] = "Cleared configuration cache";
        }
    }
    
    // Fix 5: Enable error reporting in index.php
    if (file_exists($index_path) && is_writable($index_path)) {
        $index_content = file_get_contents($index_path);
        
        if (strpos($index_content, 'ini_set(\'display_errors\'') === false) {
            $error_reporting = "\n// Enable error reporting for debugging\nini_set('display_errors', 1);\nini_set('display_startup_errors', 1);\nerror_reporting(E_ALL);\n";
            $new_content = preg_replace('/<\?php/', "<?php$error_reporting", $index_content, 1);
            
            if (file_put_contents($index_path, $new_content) !== false) {
                $fixes_applied[] = 'Enabled error reporting in index.php';
            }
        }
    }
    
    return $fixes_applied;
}

// Start the diagnostic process
start_html();

echo "<div class='box'>";
echo "<h2>Step 1: Testing Laravel Framework</h2>";

// Run the tests
$laravel_loaded = test_laravel_loaded();
$bootstrap_ok = test_bootstrap_file();
$facade_ok = test_facade_initialization();
$index_ok = test_index_file();
$storage_ok = test_storage_permissions();
$bootstrap_cache_ok = test_bootstrap_cache_permissions();
$env_ok = test_environment_config();

// Display test results
foreach ($diagnostic_results as $result) {
    format_result($result['status'], $result['message'], $result['details']);
}

echo "</div>";

// Apply fixes if needed
if (!$index_ok || !$storage_ok || !$bootstrap_cache_ok) {
    echo "<div class='box'>";
    echo "<h2>Step 2: Applying Fixes</h2>";
    
    $fixes = apply_fixes();
    
    if (!empty($fixes)) {
        echo "<p>The following fixes were applied:</p>";
        echo "<ul>";
        foreach ($fixes as $fix) {
            echo "<li class='success'>" . htmlspecialchars($fix) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>No fixes were applied. Manual intervention may be required.</p>";
    }
    
    echo "</div>";
}

// Provide next steps
echo "<div class='box'>";
echo "<h2>Next Steps</h2>";
echo "<p>Based on the diagnostic results, here are the recommended next steps:</p>";
echo "<ol>";

if (!$laravel_loaded) {
    echo "<li class='error'>Laravel classes are missing. <strong><a href='install-dependencies.php'>Run the Dependency Installer</a></strong> to install Composer dependencies.</li>";
} else if (!$bootstrap_ok) {
    echo "<li class='error'>Check that bootstrap/app.php contains the proper Laravel application initialization code.</li>";
}

if (!$index_ok) {
    echo "<li class='error'>Edit index.php to include bootstrap-app.php at the beginning of the file.</li>";
}

if (!$storage_ok) {
    echo "<li class='error'>Fix permissions on the storage directory and create missing subdirectories.</li>";
}

if (!$bootstrap_cache_ok) {
    echo "<li class='error'>Create the bootstrap/cache directory with proper permissions.</li>";
}

if (!$env_ok) {
    echo "<li class='error'>Ensure proper environment configuration through .env file or system environment variables.</li>";
}

echo "<li>After making these changes, try to access the application again.</li>";
echo "</ol>";

echo "<div class='actions'>";
if (!$laravel_loaded) {
    echo "<a href='install-dependencies.php' style='background: #ff6600;'>Install Dependencies</a>";
}
echo "<a href='/public/'>Go to Main Application</a>";
echo "<a href='/public/fix-bootstrap.php'>Run Bootstrap Fixer</a>";
echo "<a href='/public/alt-index.php'>Try Alternative Index</a>";
echo "</div>";
echo "</div>";

// Provide technical details
echo "<div class='box'>";
echo "<h2>Technical Details</h2>";
echo "<p>The 'facade root has not been set' error occurs when Laravel tries to use a facade (static helper) before the application container is properly initialized. This typically happens when:</p>";
echo "<ol>";
echo "<li>The application container is not created before a facade is used</li>";
echo "<li>The application container is not properly set as the facade root</li>";
echo "<li>The bootstrapping process is interrupted or incomplete</li>";
echo "</ol>";
echo "<p>The fixes provided by this tool aim to address these issues by:</p>";
echo "<ol>";
echo "<li>Ensuring proper bootstrapping sequence in index.php</li>";
echo "<li>Verifying critical directories and permissions</li>";
echo "<li>Clearing cached configurations that might be corrupted</li>";
echo "<li>Enabling detailed error reporting for better diagnostics</li>";
echo "</ol>";
echo "</div>";

end_html(); 