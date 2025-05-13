<?php
/**
 * Dependency Installer for Faveo
 * 
 * This script installs Composer dependencies when needed.
 * It's particularly useful for fresh deployments on Railway
 * where the vendor directory might not be properly initialized.
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

// Function to run a command and capture output
function run_command($command) {
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
    <title>Faveo Dependency Installer</title>
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
        <h1>Faveo Dependency Installer</h1>
        
        <div class="box">
            <h2>Environment Status</h2>
            <ul>
                <li>Vendor Directory: <?php echo $vendor_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></li>
                <li>Autoloader: <?php echo $autoload_exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></li>
                <li>Dependencies: <?php echo $missing_dependencies 
                    ? '<span class="error">✗ Need installation</span>' 
                    : '<span class="success">✓ Already installed</span>'; ?></li>
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
                <h3><?php echo $result['title']; ?></h3>
                <div class="command-output">
                    <pre><?php echo implode("\n", $result['result']['output']); ?></pre>
                </div>
                <p>Status: <?php echo $result['result']['success'] 
                    ? '<span class="success">✓ Success</span>' 
                    : '<span class="error">✗ Failed (code: ' . $result['result']['status'] . ')</span>'; ?></p>
            <?php endforeach; ?>
            
            <h3>Overall Status</h3>
            <p><?php echo $success 
                ? '<span class="success">✓ All commands completed successfully!</span>' 
                : '<span class="error">✗ Some commands failed. Check the output above for details.</span>'; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Install Dependencies</h2>
            <p>This will run Composer to install all the required dependencies for Faveo.</p>
            <form method="post">
                <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
                <div class="form-group">
                    <label><input type="checkbox" name="clear_cache" checked> Clear Laravel config cache after installation</label>
                </div>
                <input type="submit" name="install" value="Install Dependencies">
            </form>
        </div>
        
        <div class="box">
            <h2>Next Steps</h2>
            <p>After installing dependencies, you can continue with the Faveo setup:</p>
            <div class="actions">
                <a href="diagnose-facade.php">Run Facade Diagnostic</a>
                <a href="direct-migration.php">Set Up Database</a>
                <a href="fix-permissions.php">Fix Permissions</a>
                <a href="/public/">Go to Application</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 