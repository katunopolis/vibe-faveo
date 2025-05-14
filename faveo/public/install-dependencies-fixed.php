<?php
/**
 * Fixed Dependency Installer for Faveo
 * 
 * This script sets the necessary environment variables and then
 * installs Composer dependencies.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security check - prevent unauthorized access
$password = $_POST['password'] ?? $_GET['key'] ?? '';
$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
$authorized = ($password === $stored_password);

// Increase execution time limit as composer might take a while
set_time_limit(300); // 5 minutes

// Store results
$results = [];
$errors = [];
$success = false;

// Set the environment variables
putenv("COMPOSER_HOME=/tmp/composer");
putenv("COMPOSER_ALLOW_SUPERUSER=1");

// Function to run a command and capture output
function run_command($command) {
    // Make directory for composer
    if (!is_dir('/tmp/composer')) {
        mkdir('/tmp/composer', 0777, true);
    }
    
    // Ensure environment variables in command context
    $command = "export COMPOSER_HOME=/tmp/composer && export COMPOSER_ALLOW_SUPERUSER=1 && $command";
    
    $output = [];
    $return_var = 0;
    
    // Execute the command
    exec($command . ' 2>&1', $output, $return_var);
    
    return [
        'output' => $output,
        'status' => $return_var,
        'success' => ($return_var === 0)
    ];
}

// Actually run the installation if authorized and requested
if ($authorized && isset($_POST['install'])) {
    // Create composer home directory
    if (!is_dir('/tmp/composer')) {
        mkdir('/tmp/composer', 0777, true);
    }
    
    // 1. Clear composer cache
    $clear_cache = run_command('cd /var/www/html && composer clearcache');
    $results[] = ['title' => 'Clear Composer Cache', 'result' => $clear_cache];
    
    // 2. Install dependencies without dev packages
    $install = run_command('cd /var/www/html && composer install --no-dev --optimize-autoloader');
    $results[] = ['title' => 'Install Dependencies', 'result' => $install];
    
    // 3. Generate optimized autoloader
    $optimize = run_command('cd /var/www/html && composer dump-autoload --optimize');
    $results[] = ['title' => 'Optimize Autoloader', 'result' => $optimize];
    
    // Check overall success
    $success = $clear_cache['success'] && $install['success'] && $optimize['success'];
    
    // Optional: Clear config cache
    if ($success && isset($_POST['clear_cache'])) {
        $cache_result = run_command('cd /var/www/html && php artisan config:clear');
        $results[] = ['title' => 'Clear Config Cache', 'result' => $cache_result];
    }
    
    // Remove the "needs_composer_install" flag file if installation was successful
    if ($success && file_exists(__DIR__ . '/needs_composer_install')) {
        if (unlink(__DIR__ . '/needs_composer_install')) {
            $results[] = ['title' => 'Remove Installation Flag', 'result' => [
                'output' => ['Successfully removed the "needs_composer_install" flag.'],
                'status' => 0,
                'success' => true
            ]];
        }
    }
}

// Check if vendor directory exists and is populated
$vendor_exists = is_dir('/var/www/html/vendor');
$autoload_exists = file_exists('/var/www/html/vendor/autoload.php');
$missing_dependencies = !$autoload_exists;

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Dependency Installer (Fixed)</title>
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
        .command-output {
            max-height: 200px;
            overflow-y: auto;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Dependency Installer (Fixed)</h1>
        
        <div class="box">
            <h2>Environment Status</h2>
            <ul>
                <li>Vendor Directory: <?php echo $vendor_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></li>
                <li>Autoloader: <?php echo $autoload_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></li>
                <li>Dependencies: <?php echo $missing_dependencies 
                    ? '<span class="error">✗ Need installation</span>' 
                    : '<span class="success">✓ Already installed</span>'; ?></li>
                <li>COMPOSER_HOME: <?php echo getenv('COMPOSER_HOME') ? '<span class="success">✓ Set to ' . getenv('COMPOSER_HOME') . '</span>' : '<span class="error">✗ Not set</span>'; ?></li>
                <li>COMPOSER_ALLOW_SUPERUSER: <?php echo getenv('COMPOSER_ALLOW_SUPERUSER') ? '<span class="success">✓ Set</span>' : '<span class="error">✗ Not set</span>'; ?></li>
            </ul>
        </div>
        
        <?php if (!$authorized): ?>
        <div class="box">
            <h2>Authentication Required</h2>
            <p>Please enter the admin password to access this installer.</p>
            <form method="post">
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Authenticate</button>
            </form>
        </div>
        <?php else: ?>
        
        <?php if (!empty($results)): ?>
        <div class="box">
            <h2>Installation Results</h2>
            <?php foreach ($results as $result): ?>
                <h3><?php echo htmlspecialchars($result['title']); ?></h3>
                <pre class="command-output"><?php 
                    if (isset($result['result']['output']) && is_array($result['result']['output'])) {
                        echo htmlspecialchars(implode("\n", $result['result']['output']));
                    } 
                ?></pre>
                <p>Status: <span class="<?php echo $result['result']['success'] ? 'success' : 'error'; ?>">
                    <?php echo $result['result']['success'] ? '✓ Success' : '✗ Failed (code: ' . $result['result']['status'] . ')'; ?>
                </span></p>
            <?php endforeach; ?>
            
            <h3>Overall Status</h3>
            <p class="<?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $success ? '✓ All commands completed successfully!' : '✗ Some commands failed. Check the output above for details.'; ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Installation Actions</h2>
            <form method="post">
                <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
                <div class="form-group">
                    <input type="checkbox" id="clear_cache" name="clear_cache" checked>
                    <label for="clear_cache">Clear Laravel config cache after installation</label>
                </div>
                <button type="submit" name="install">Install Dependencies</button>
            </form>
        </div>
        
        <div class="box">
            <h2>Navigation</h2>
            <div class="actions">
                <a href="diagnose-facade.php">Diagnostic Tool</a>
                <a href="fix-url.php">Fix URL Settings</a>
                <a href="fix-permissions.php">Fix Permissions</a>
                <a href="/public">Go to Faveo</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 