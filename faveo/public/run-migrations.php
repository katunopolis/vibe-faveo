<?php
/**
 * Laravel Migration Runner for Faveo
 */

// Start timing
$startTime = microtime(true);

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include our db-direct-config helper
$SKIP_HEADER = true; // Skip HTML output from the helper
try {
    // First try the simplified version that is compatible with older PHP versions
    if (file_exists(__DIR__ . '/db-fixed.php')) {
        $db_config = include __DIR__ . '/db-fixed.php';
    } else {
        // Fall back to the original version
        $db_config = include __DIR__ . '/db-direct-config.php';
    }
    
    if (!$db_config['success']) {
        die("<p class='error'>Error connecting to database: " . $db_config['message'] . "</p>");
    }
} catch (Exception $e) {
    die("<p class='error'>Failed to load database configuration: " . $e->getMessage() . "</p>");
}

// Define application path
$appPath = realpath(__DIR__ . '/..');

// Output header
echo "<html><head><title>Faveo Database Migration</title>";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
    h1, h2 { color: #336699; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .step { margin-bottom: 20px; padding: 10px; border-left: 4px solid #ccc; }
    .step h3 { margin-top: 0; }
</style>";
echo "</head><body>";
echo "<h1>Faveo Database Setup</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
    h1, h2 { color: #333; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; border-radius: 4px; }
    p.error { color: #e74c3c; font-weight: bold; }
    p.success { color: #2ecc71; font-weight: bold; }
    code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
</style>";

// Security warning
echo "<div style='background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border-radius: 5px;'>
<strong>Security Notice:</strong> This script allows database initialization without authentication. 
For security reasons, please delete this file after you've successfully set up your Faveo installation.
</div>";

// Database connection info
echo "<h2>Database Connection Information</h2>";
if (isset($db_config['connection']['host'])) {
    // New format from db-fixed.php
    echo "<p>Connected to <strong>{$db_config['connection']['host']}:{$db_config['connection']['port']}</strong> as <strong>{$db_config['connection']['username']}</strong></p>";
    echo "<p>Database: <strong>{$db_config['connection']['database']}</strong></p>";
    echo "<p>Connection source: <strong>{$db_config['connection']['method']}</strong></p>";
} else {
    // Old format from db-direct-config.php
    echo "<p>Connected to <strong>{$db_config['connection']['host']}:{$db_config['connection']['port']}</strong> as <strong>{$db_config['connection']['username']}</strong></p>";
    echo "<p>Database: <strong>{$db_config['connection']['database']}</strong></p>";
    echo "<p>Connection source: <strong>{$db_config['connection']['source']}</strong></p>";
}

// Run the migration commands
echo "<h2>Running Migrations</h2>";

// First try direct PHP approach
try {
    // Include the bootstrap helper for Laravel
    if (file_exists($db_config['bootstrap_file'])) {
        echo "<p>Including database bootstrap file: {$db_config['bootstrap_file']}</p>";
        require_once $db_config['bootstrap_file'];
    }
    
    // Check if bootstrap/autoload.php exists
    if (!file_exists($appPath . '/bootstrap/autoload.php')) {
        throw new Exception("Could not find bootstrap/autoload.php, trying direct approach");
    }
    
    // Bootstrap the Laravel application
    require $appPath . '/bootstrap/autoload.php';

    // Standard direct migration/seeding approach - bypassing artisan to avoid facade errors
    echo "<h3>Direct Database Actions (bypassing artisan)</h3>";

    echo "<p>Loading Laravel application...</p>";
    $app = require_once $appPath . '/bootstrap/app.php';
    
    // Access the kernel directly
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    
    // Generate app key
    echo "<p>Generating application key...</p>";
    $keyResult = $kernel->call('key:generate', ['--force' => true]);
    echo "<pre>Result: $keyResult</pre>";
    echo "<p class='success'>Application key generated successfully</p>";
    
    // Running migrations
    echo "<p>Running migrations...</p>";
    $migrateResult = $kernel->call('migrate', ['--force' => true]);
    echo "<pre>Result: $migrateResult</pre>";
    echo "<p class='success'>Migrations ran successfully</p>";
    
    // Seed the database
    echo "<p>Seeding database...</p>";
    $seedResult = $kernel->call('db:seed', ['--force' => true]);
    echo "<pre>Result: $seedResult</pre>";
    echo "<p class='success'>Database seeded successfully</p>";
    
    // Cache config
    echo "<p>Caching configuration...</p>";
    $cacheResult = $kernel->call('config:cache');
    echo "<pre>Result: $cacheResult</pre>";
    echo "<p class='success'>Configuration cached successfully</p>";
    
    // Faveo-specific install commands
    echo "<p>Installing Faveo database...</p>";
    $installDbResult = $kernel->call('install:db');
    echo "<pre>Result: $installDbResult</pre>";
    echo "<p class='success'>Faveo database installed successfully</p>";
    
    echo "<p>Installing Faveo...</p>";
    $installFaveoResult = $kernel->call('install:faveo');
    echo "<pre>Result: $installFaveoResult</pre>";
    echo "<p class='success'>Faveo installed successfully</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>Error during Laravel bootstrap: " . $e->getMessage() . "</p>";
    echo "<p class='warning'>Falling back to direct command execution...</p>";
    
    // Fallback to direct command execution
    echo "<h3>Executing Artisan Commands Directly</h3>";
    
    // Ensure the database config file exists
    if (file_exists($db_config['bootstrap_file'])) {
        echo "<p>Bootstrap file exists and will be used by the commands</p>";
    } else {
        echo "<p class='warning'>Bootstrap file not found, command execution may fail</p>";
    }
    
    // Add PHP memory limit option to prevent OOM issues
    $commands = [
        'cd /var/www/html && php -d memory_limit=-1 artisan migrate --force',
        'cd /var/www/html && php -d memory_limit=-1 artisan db:seed --force',
        'cd /var/www/html && php -d memory_limit=-1 artisan key:generate --force',
        'cd /var/www/html && php -d memory_limit=-1 artisan config:cache',
        'cd /var/www/html && php -d memory_limit=-1 artisan install:db',
        'cd /var/www/html && php -d memory_limit=-1 artisan install:faveo'
    ];

    foreach ($commands as $command) {
        echo "<p>Running: <code>$command</code></p>";
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        echo "<pre>";
        echo implode("\n", $output);
        echo "</pre>";
        
        if ($return_var !== 0) {
            echo "<p class='error'>Command failed with code $return_var</p>";
        } else {
            echo "<p class='success'>Command executed successfully</p>";
        }
    }
}

echo "<h2>Checking Tables</h2>";

try {
    // Get PDO from the correct location depending on which helper was used
    $pdo = isset($db_config['pdo']) ? $db_config['pdo'] : $db_config['connection']['pdo'];
    
    if (!$pdo) {
        throw new Exception("No active database connection available");
    }
    
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Tables found: " . count($tables) . "</p>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>No tables found. Migrations may have failed.</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>Error checking tables: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>If migrations completed successfully, you should continue with these steps:</p>";
echo "<ol>
    <li><a href='repair-database.php'>Run Database Repair</a></li>
    <li><a href='create-admin.php'>Create Admin User</a></li>
    <li><a href='fix-permissions.php'>Fix Permissions</a></li>
    <li><a href='/public'>Go to Faveo</a></li>
</ol>";

// Final status
echo "<div class='step'>";
echo "<h2>Migration Complete</h2>";
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);
echo "<p>Execution time: $executionTime seconds</p>";

echo "</body></html>"; 