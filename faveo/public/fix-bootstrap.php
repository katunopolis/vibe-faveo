<?php
/**
 * Faveo Bootstrap Fixer
 * 
 * This script attempts to fix common bootstrap issues with Laravel in Faveo:
 * 1. Injects bootstrap-app.php into index.php if needed
 * 2. Creates facade-fix.php if not exists
 * 3. Creates alt-index.php as an alternative entry point
 * 4. Fixes permissions on critical files
 */

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define success messages
$success_messages = [];
$error_messages = [];

// Check if we're running on the production server
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false);

// Function to patch files
function patch_file($source_file, $pattern, $replacement, $file_description) {
    global $success_messages, $error_messages;
    
    if (!file_exists($source_file)) {
        $error_messages[] = "Error: $file_description file not found at: $source_file";
        return false;
    }
    
    if (!is_writable($source_file)) {
        $error_messages[] = "Error: $file_description file is not writable: $source_file";
        return false;
    }
    
    $content = file_get_contents($source_file);
    if (!$content) {
        $error_messages[] = "Error: Could not read $file_description file: $source_file";
        return false;
    }
    
    // Check if already patched
    if (strpos($content, $replacement) !== false) {
        $success_messages[] = "$file_description file is already patched.";
        return true;
    }
    
    // Apply the patch
    $new_content = preg_replace($pattern, $replacement, $content, 1);
    
    if ($new_content === null || $new_content === $content) {
        $error_messages[] = "Error: Failed to apply patch to $file_description file.";
        return false;
    }
    
    // Write the patched content
    if (file_put_contents($source_file, $new_content) === false) {
        $error_messages[] = "Error: Failed to write patched $file_description file.";
        return false;
    }
    
    $success_messages[] = "Successfully patched $file_description file.";
    return true;
}

// Function to create a file if it doesn't exist
function create_file_if_not_exists($target_file, $content, $file_description) {
    global $success_messages, $error_messages;
    
    if (file_exists($target_file)) {
        $success_messages[] = "$file_description file already exists.";
        return true;
    }
    
    $dir = dirname($target_file);
    if (!is_writable($dir)) {
        $error_messages[] = "Error: Directory is not writable: $dir";
        return false;
    }
    
    if (file_put_contents($target_file, $content) === false) {
        $error_messages[] = "Error: Failed to create $file_description file.";
        return false;
    }
    
    chmod($target_file, 0644); // Make file readable
    
    $success_messages[] = "Successfully created $file_description file.";
    return true;
}

// Check all scripts exist
$script_files = [
    'bootstrap-app.php',
    'facade-fix.php',
    'alt-index.php',
    'db-test.php',
    'memory-only-fix.php',
    'direct-migration.php',
    'run-migrations.php',
    'fix-permissions.php'
];

foreach ($script_files as $script) {
    if (!file_exists(__DIR__ . '/' . $script)) {
        $error_messages[] = "Warning: $script does not exist. Some functionality may not work.";
    }
}

// Fix 1: Patch index.php to include bootstrap-app.php
$index_file = __DIR__ . '/index.php';
$bootstrap_include = "// Bootstrap the Laravel application environment\nrequire __DIR__.'/bootstrap-app.php';";
$patched_index = patch_file(
    $index_file, 
    '/<\?php/', 
    "<?php\n$bootstrap_include\n", 
    'index.php'
);

// Fix 2: Test accessing the production site with the alternate index
$alt_index_url = str_replace('fix-bootstrap.php', 'alt-index.php', $_SERVER['REQUEST_URI']);
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

// Fix 3: Set permissions on critical directories
$storage_path = realpath(__DIR__ . '/../storage');
$bootstrap_path = realpath(__DIR__ . '/../bootstrap');

if ($storage_path) {
    if (is_writable($storage_path)) {
        $success_messages[] = "Storage directory is writable.";
        
        // Make sure critical subdirectories exist
        $storage_dirs = [
            'app',
            'app/public',
            'framework',
            'framework/cache',
            'framework/sessions',
            'framework/views',
            'logs'
        ];
        
        foreach ($storage_dirs as $dir) {
            $full_path = $storage_path . '/' . $dir;
            if (!file_exists($full_path)) {
                if (@mkdir($full_path, 0755, true)) {
                    $success_messages[] = "Created directory: $dir";
                } else {
                    $error_messages[] = "Failed to create directory: $dir";
                }
            }
        }
    } else {
        $error_messages[] = "Warning: Storage directory is not writable: $storage_path";
    }
}

if ($bootstrap_path) {
    if (is_writable($bootstrap_path)) {
        $success_messages[] = "Bootstrap directory is writable.";
        
        // Make sure cache directory exists
        $cache_path = $bootstrap_path . '/cache';
        if (!file_exists($cache_path)) {
            if (@mkdir($cache_path, 0755, true)) {
                $success_messages[] = "Created cache directory";
            } else {
                $error_messages[] = "Failed to create cache directory";
            }
        }
    } else {
        $error_messages[] = "Warning: Bootstrap directory is not writable: $bootstrap_path";
    }
}

// Display the results
$has_errors = !empty($error_messages);
$title = $has_errors ? "Bootstrap Fix (with errors)" : "Bootstrap Fix (success)";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
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
        h1 {
            color: #336699;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success-box {
            background-color: #f0fff0;
            border-color: #d0e9c6;
        }
        .error-box {
            background-color: #fff0f0;
            border-color: #ebccd1;
        }
        .success-list {
            color: #3c763d;
        }
        .error-list {
            color: #a94442;
        }
        ul {
            margin-top: 5px;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .actions {
            margin-top: 20px;
        }
        .actions a {
            display: inline-block;
            margin-right: 10px;
            padding: 8px 15px;
            background: #336699;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .actions a:hover {
            background: #264d73;
        }
        .warning {
            color: #8a6d3b;
            background-color: #fcf8e3;
            border-color: #faebcc;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Bootstrap Fixer</h1>
        
        <?php if ($is_production): ?>
        <div class="warning">
            <strong>Production Environment Detected!</strong> Fixes are being applied to the Railway production server.
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success_messages)): ?>
        <div class="box success-box">
            <h2>Success Messages</h2>
            <ul class="success-list">
                <?php foreach ($success_messages as $message): ?>
                <li><?php echo $message; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_messages)): ?>
        <div class="box error-box">
            <h2>Error Messages</h2>
            <ul class="error-list">
                <?php foreach ($error_messages as $message): ?>
                <li><?php echo $message; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Next Steps</h2>
            <p>Now that the bootstrap fixes have been applied, try the following:</p>
            <ol>
                <li>Try accessing the <a href="/public/">main application</a> to see if the facade root error is fixed.</li>
                <li>If you still see the facade root error, try the <a href="<?php echo $alt_index_url; ?>">alternative index</a>.</li>
                <li>If both options fail, check that all the diagnostic scripts exist and are accessible:</li>
            </ol>
            <div class="actions">
                <a href="db-test.php">Database Test</a>
                <a href="memory-only-fix.php">Memory Fix</a>
                <a href="direct-migration.php">Direct Migration</a>
                <a href="fix-permissions.php">Fix Permissions</a>
            </div>
        </div>
        
        <div class="box">
            <h2>Technical Details</h2>
            <p>The following fixes were attempted:</p>
            <ol>
                <li>Adding bootstrap-app.php include to index.php</li>
                <li>Verifying critical scripts exist</li>
                <li>Creating storage directories if missing</li>
                <li>Fixing directory permissions</li>
            </ol>
            <p>If you continue to experience issues, use the diagnostic tools above to troubleshoot further.</p>
        </div>
    </div>
</body>
</html> 