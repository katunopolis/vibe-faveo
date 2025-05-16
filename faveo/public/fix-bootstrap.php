<?php
/**
 * Faveo Helpdesk Bootstrap Fixer
 * This script fixes common issues with Faveo running on Railway
 */

// Check if this is a web request
$isWebRequest = php_sapi_name() !== 'cli';

// Set content type for web requests
if ($isWebRequest) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
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
            <h2>Diagnostic Results</h2>';
} else {
    echo "Faveo Bootstrap Fixer\n";
    echo "=====================\n\n";
}

$messages = [];
$successes = [];
$errors = [];

// Functions
function checkAndFixPermissions($path, $recursive = false) {
    global $messages, $successes, $errors, $isWebRequest;
    
    $messages[] = "Checking permissions for {$path}...";
    
    if (!file_exists($path)) {
        $messages[] = "WARNING: Path {$path} does not exist! Creating it...";
        if (!mkdir($path, 0775, true)) {
            $errors[] = "Could not create {$path}!";
            return false;
        }
    }
    
    // Make sure directory is writable
    if (!is_writable($path)) {
        $messages[] = "Fixing permissions for {$path}...";
        chmod($path, 0775);
        
        // Check if it worked
        if (!is_writable($path)) {
            $errors[] = "Could not make {$path} writable!";
            return false;
        } else {
            $successes[] = "Fixed permissions for: " . basename($path);
        }
    } else {
        $messages[] = "{$path} is writable. ✓";
    }
    
    // Handle recursive permission fixing
    if ($recursive && is_dir($path)) {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                checkAndFixPermissions($fullPath, true);
            } else {
                // Check and fix file permissions
                if (!is_readable($fullPath) || !is_writable($fullPath)) {
                    $messages[] = "Fixing permissions for file {$fullPath}...";
                    chmod($fullPath, 0664);
                }
            }
        }
    }
    
    return true;
}

function checkAndFixBootstrapApp() {
    global $messages, $successes, $errors, $isWebRequest;
    
    $messages[] = "Checking bootstrap-app.php inclusion...";
    
    $bootstrapAppPath = __DIR__ . '/bootstrap-app.php';
    $indexPath = __DIR__ . '/index.php';
    
    // Check if bootstrap-app.php exists
    if (!file_exists($bootstrapAppPath)) {
        $errors[] = "bootstrap-app.php does not exist in public directory!";
        return false;
    }
    
    // Check if index.php exists
    if (!file_exists($indexPath)) {
        $errors[] = "index.php does not exist!";
        return false;
    }
    
    // Read index.php
    $indexContent = file_get_contents($indexPath);
    
    // Check if bootstrap-app.php is properly required
    if (strpos($indexContent, 'bootstrap-app.php') === false) {
        $messages[] = "WARNING: bootstrap-app.php is not included in index.php. Fixing...";
        
        // Backup index.php
        file_put_contents($indexPath . '.bak', $indexContent);
        
        // Add the require statement for bootstrap-app.php at the beginning
        $fixedContent = "<?php\nrequire __DIR__.'/bootstrap-app.php';\n" . substr($indexContent, 5);
        
        // Write fixed content back to index.php
        if (file_put_contents($indexPath, $fixedContent)) {
            $successes[] = "Fixed index.php to include bootstrap-app.php";
        } else {
            $errors[] = "Could not write to index.php!";
            return false;
        }
    } else {
        $successes[] = "index.php already includes bootstrap-app.php";
    }
    
    return true;
}

// Execute fixes

// 1. Check and fix storage directory permissions
$messages[] = "Fixing storage directory permissions...";
checkAndFixPermissions(__DIR__ . '/../storage', true);

// 2. Check and fix bootstrap/cache directory permissions
$messages[] = "Fixing bootstrap/cache directory permissions...";
checkAndFixPermissions(__DIR__ . '/../bootstrap/cache', true);

// 3. Check and fix bootstrap-app.php inclusion
$messages[] = "Checking bootstrap-app.php inclusion...";
checkAndFixBootstrapApp();

// Output results
if ($isWebRequest) {
    if (count($successes) > 0) {
        echo '<h3 class="success">Successful Actions:</h3>';
        echo '<ul>';
        foreach ($successes as $success) {
            echo '<li class="success">' . htmlspecialchars($success) . '</li>';
        }
        echo '</ul>';
    }
    
    if (count($errors) > 0) {
        echo '<h3 class="error">Errors:</h3>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li class="error">' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
    }
    
    echo '<div class="overall-status">';
    if (count($errors) == 0) {
        echo '<h3 class="success">✓ All fixes applied successfully.</h3>';
    } else {
        echo '<h3 class="error">✗ Some fixes failed. Please check the errors above.</h3>';
    }
    echo '</div>';
    
    echo '</div>';
    
    echo '<div class="box">
            <h2>Next Steps</h2>
            <ul>';
    
    if (count($errors) == 0) {
        echo '<li>Try accessing the <a href="/public">main application</a> again.</li>';
    } else {
        echo '<li>Fix the errors listed above and try again.</li>';
    }
    
    echo '<li>If you\'re still seeing issues, try the <a href="diagnose-facade.php">diagnostic tool</a> for more detailed analysis.</li>
                <li>You may need to <a href="install-dependencies.php">reinstall dependencies</a> if you\'re missing vendor files.</li>
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
</html>';
} else {
    echo "\nResults:\n";
    echo "========\n\n";
    
    echo "Successes:\n";
    foreach ($successes as $success) {
        echo "✓ " . $success . "\n";
    }
    
    echo "\nErrors:\n";
    if (count($errors) > 0) {
        foreach ($errors as $error) {
            echo "✗ " . $error . "\n";
        }
    } else {
        echo "No errors! All fixes applied successfully.\n";
    }
    
    echo "\nFixes completed. If you're still having issues, please check index.php and bootstrap-app.php.\n";
} 