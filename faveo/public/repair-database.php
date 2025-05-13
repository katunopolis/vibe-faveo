<?php
// Database repair script for Faveo on Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Faveo Database Repair</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; border-radius: 4px; }
        p.error { color: #e74c3c; font-weight: bold; }
        p.success { color: #2ecc71; font-weight: bold; }
        p.warning { color: #f39c12; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
        ul { background: #f9f9f9; padding: 15px 15px 15px 35px; border-radius: 5px; }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <h1>Faveo Database Repair</h1>";

try {
    $dsn = 'mysql:host=mysql.railway.internal;port=3306;dbname=railway';
    $username = 'root';
    $password = getenv('MYSQLPASSWORD');
    
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>✓ Connected to database successfully!</p>";
} catch (PDOException $e) {
    die("<p class='error'>Error connecting to database: " . $e->getMessage() . "</p>");
}

// Check Faveo tables
echo "<h2>Checking Faveo Tables</h2>";

$required_tables = [
    'users', 'tickets', 'ticket_thread', 'settings', 'emails', 'templates'
];

$missing_tables = [];

foreach ($required_tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() === 0) {
        $missing_tables[] = $table;
    }
}

if (count($missing_tables) > 0) {
    echo "<p class='warning'>Missing tables: " . implode(', ', $missing_tables) . "</p>";
    echo "<p>Running Faveo installation commands...</p>";
    
    $commands = [
        'cd /var/www/html && php artisan migrate:reset --force',
        'cd /var/www/html && php artisan migrate --force',
        'cd /var/www/html && php artisan db:seed --force',
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
} else {
    echo "<p class='success'>✓ All required tables found.</p>";
}

// Show all tables
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<h2>Database Tables</h2>";
echo "<p>Total tables: " . count($tables) . "</p>";
echo "<div style='max-height: 300px; overflow-y: auto;'>";
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>$table</li>";
}
echo "</ul>";
echo "</div>";

// Check table structure
echo "<h2>Database Structure Check</h2>";

$structure_issues = [];

// Check users table structure
if (in_array('users', $tables)) {
    $required_columns = ['id', 'email', 'password', 'role', 'user_name', 'active'];
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $missing_columns = array_diff($required_columns, $columns);
    if (!empty($missing_columns)) {
        $structure_issues[] = "Users table is missing columns: " . implode(', ', $missing_columns);
    }
}

if (!empty($structure_issues)) {
    echo "<p class='warning'>Structure issues found:</p>";
    echo "<ul>";
    foreach ($structure_issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
    
    echo "<p>Attempting to fix database structure...</p>";
    
    // Run repair commands
    $commands = [
        'cd /var/www/html && php artisan migrate:refresh --force',
        'cd /var/www/html && php artisan db:seed --force'
    ];
    
    foreach ($commands as $command) {
        echo "<p>Running: <code>$command</code></p>";
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        echo "<pre>";
        echo implode("\n", $output);
        echo "</pre>";
    }
} else {
    echo "<p class='success'>✓ Database structure looks good.</p>";
}

// Check settings
if (in_array('settings', $tables)) {
    echo "<h2>Checking Settings</h2>";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    $settings_count = $stmt->fetchColumn();
    
    if ($settings_count == 0) {
        echo "<p class='warning'>No settings found. Running settings seed...</p>";
        
        $command = 'cd /var/www/html && php artisan db:seed --class=SettingsTableSeeder --force';
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        echo "<pre>";
        echo implode("\n", $output);
        echo "</pre>";
    } else {
        echo "<p class='success'>✓ Settings table has $settings_count records.</p>";
    }
}

echo "<h2>Next Steps</h2>";
echo "<ol>
    <li><a href='create-admin.php'>Create Admin User</a></li>
    <li><a href='fix-permissions.php'>Fix Permissions</a></li>
    <li><a href='/public'>Go to Faveo</a></li>
</ol>
</body>
</html>"; 