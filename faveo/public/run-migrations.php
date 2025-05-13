<?php
/**
 * Laravel Migration Runner for Faveo
 */

// Start timing
$startTime = microtime(true);

// Remove security key requirement for easier setup
// We'll leave a note to remove this file after use for security

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Make sure we have a database connection
try {
    $dsn = 'mysql:host=mysql.railway.internal;port=3306;dbname=railway';
    $username = 'root';
    $password = getenv('MYSQLPASSWORD');
    
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✓ Connected to database successfully!</p>";
} catch (PDOException $e) {
    die("<p class='error'>Error connecting to database: " . $e->getMessage() . "</p>");
}

// Run the migration commands
echo "<h2>Running Migrations</h2>";

$commands = [
    'cd /var/www/html && php artisan migrate --force',
    'cd /var/www/html && php artisan db:seed --force',
    'cd /var/www/html && php artisan key:generate --force',
    'cd /var/www/html && php artisan config:cache',
    'cd /var/www/html && php artisan install:db',
    'cd /var/www/html && php artisan install:faveo'
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