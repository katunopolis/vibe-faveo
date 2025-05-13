<?php
/**
 * Laravel Migration Runner for Faveo - Memory Only Version
 * This script uses memory-only configuration to avoid permission issues
 */

// Start timing
$startTime = microtime(true);

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Output header
echo "<html><head><title>Faveo Database Migration (Memory Only)</title>";
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
echo "<h1>Faveo Database Setup (Memory Only)</h1>";
echo "<p>This version uses in-memory configuration to avoid permission issues</p>";

// Security warning
echo "<div style='background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border-radius: 5px;'>
<strong>Security Notice:</strong> This script allows database initialization without authentication. 
For security reasons, please delete this file after you've successfully set up your Faveo installation.
</div>";

// Include our memory-only database helper
$SKIP_HEADER = true; // Skip HTML output from the helper
try {
    if (file_exists(__DIR__ . '/memory-only-fix.php')) {
        $db_config = include __DIR__ . '/memory-only-fix.php';
    } else {
        die("<p class='error'>memory-only-fix.php file not found. Please create it first.</p>");
    }
    
    if (!$db_config['success']) {
        die("<p class='error'>Error connecting to database: " . $db_config['message'] . "</p>");
    }
} catch (Exception $e) {
    die("<p class='error'>Failed to load database configuration: " . $e->getMessage() . "</p>");
}

// Define application path
$appPath = realpath(__DIR__ . '/..');

// Database connection info
echo "<h2>Database Connection Information</h2>";
echo "<p>Connected to <strong>{$db_config['connection']['host']}:{$db_config['connection']['port']}</strong> as <strong>{$db_config['connection']['username']}</strong></p>";
echo "<p>Database: <strong>{$db_config['connection']['database']}</strong></p>";
echo "<p>Connection source: <strong>{$db_config['connection']['method']}</strong></p>";

// Run the migration commands
echo "<h2>Running Migrations</h2>";

// Create excel.php stub to fix the Excel dependency issue
echo "<h3>1. Creating stub for Excel dependency</h3>";
$excel_config_dir = $appPath . '/config';
$excel_config_file = $excel_config_dir . '/excel.php';

if (is_dir($excel_config_dir) && file_exists($excel_config_file)) {
    // Safe patching strategy - create a temporary version
    $excel_config_backup = $excel_config_file . '.bak';
    if (!file_exists($excel_config_backup)) {
        if (is_writable($excel_config_dir)) {
            copy($excel_config_file, $excel_config_backup);
            echo "<p class='success'>Created backup of excel.php file</p>";
        } else {
            echo "<p class='warning'>Cannot create backup of excel.php (permission denied), will use direct commands</p>";
        }
    }
    
    // Try to patch the file if writable
    if (is_writable($excel_config_file)) {
        $excel_stub = '<?php
// Excel stub configuration file
return [
    "providers" => [],
    "exports" => [],
    "imports" => [],
    "extension_detector" => [],
    "cache" => [
        "enable" => true,
        "driver" => "memory",
        "settings" => ["memoryCacheSize" => "32MB"],
    ],
    "value_binder" => [
        "default" => "Maatwebsite\Excel\DefaultValueBinder",
    ],
    "temporary_files" => [
        "local_path" => sys_get_temp_dir(),
        "remote_disk" => null,
    ],
    "csv" => [
        "delimiter" => ",",
        "enclosure" => "\"",
        "line_ending" => "\n",
        "use_bom" => false,
        "include_separator_line" => false,
        "excel_compatibility" => false,
    ],
    "export" => [],
    "import" => [],
];';
        
        file_put_contents($excel_config_file, $excel_stub);
        echo "<p class='success'>Created Excel stub configuration file</p>";
    } else {
        echo "<p class='warning'>Cannot write to excel.php (permission denied), will use direct commands</p>";
    }
} else {
    echo "<p class='warning'>Excel config file not found or directory not writable, will use direct commands</p>";
}

// Install Excel dependency if composer is available
echo "<h3>2. Installing missing Excel dependency</h3>";
$cmd_excel_install = 'cd /var/www/html && composer require maatwebsite/excel';
echo "<p>Running: <code>$cmd_excel_install</code></p>";
$output = [];
$return_var = 0;
exec($cmd_excel_install, $output, $return_var);

echo "<pre>";
echo implode("\n", $output);
echo "</pre>";

if ($return_var !== 0) {
    echo "<p class='warning'>Could not install Excel dependency. Will use direct commands instead.</p>";
} else {
    echo "<p class='success'>Excel dependency installed successfully</p>";
}

// Run the migration commands without using Laravel
echo "<h3>3. Running Database Commands Directly</h3>";

// Use direct command execution to avoid Laravel bootstrapping issues
$commands = [
    'cd /var/www/html && php -d memory_limit=-1 artisan key:generate --force',
    'cd /var/www/html && php -d memory_limit=-1 artisan migrate --force',
    'cd /var/www/html && php -d memory_limit=-1 artisan db:seed --force',
    'cd /var/www/html && php -d memory_limit=-1 artisan config:clear',
    'cd /var/www/html && php -d memory_limit=-1 artisan cache:clear',
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

echo "<h2>Checking Tables</h2>";

try {
    $pdo = $db_config['pdo'];
    
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