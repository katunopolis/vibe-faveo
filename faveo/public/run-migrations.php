<?php
/**
 * Laravel Migration Runner for Faveo
 * 
 * WARNING: This file should be removed or password-protected after use
 */

// Start timing
$startTime = microtime(true);

// Basic security - require a key parameter
$securityKey = 'faveo2025'; // Change this to something more secure
if (!isset($_GET['key']) || $_GET['key'] !== $securityKey) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied. Please provide the correct key.';
    exit;
}

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
echo "<h1>Faveo Database Migration Tool</h1>";
echo "<p>This tool will run the database migrations for Faveo Helpdesk.</p>";

// Helper function to run shell commands
function runCommand($command, $workingDir = null) {
    $descriptorSpec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"],  // stderr
    ];
    
    $process = proc_open($command, $descriptorSpec, $pipes, $workingDir);
    
    if (is_resource($process)) {
        // Close stdin
        fclose($pipes[0]);
        
        // Read stdout
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        
        // Read stderr
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        // Close process
        $code = proc_close($process);
        
        return [
            'code' => $code,
            'output' => $output,
            'error' => $error
        ];
    }
    
    return [
        'code' => -1,
        'output' => '',
        'error' => 'Failed to execute command'
    ];
}

// Function to run a Laravel Artisan command
function runArtisan($command, $appPath) {
    echo "<div class='step'>";
    echo "<h3>Running: php artisan $command</h3>";
    
    try {
        // Check if artisan exists
        if (!file_exists($appPath . '/artisan')) {
            throw new Exception("Artisan file not found in $appPath");
        }
        
        // Set up the environment for artisan
        $output = [];
        $result = 0;
        $cmd = "php " . escapeshellarg($appPath . '/artisan') . " $command --no-interaction";
        echo "<pre>Executing: $cmd</pre>";
        
        $result = runCommand($cmd, $appPath);
        
        if ($result['code'] !== 0) {
            echo "<p class='error'>Command failed with exit code {$result['code']}</p>";
            echo "<pre>{$result['error']}</pre>";
            throw new Exception("Command failed: " . $result['error']);
        }
        
        echo "<pre>{$result['output']}</pre>";
        echo "<p class='success'>Command completed successfully!</p>";
        
        return true;
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        return false;
    }
    
    echo "</div>";
}

// Step 1: Display environment info
echo "<div class='step'>";
echo "<h2>Step 1: Environment Information</h2>";
echo "<pre>";
echo "Application Path: " . $appPath . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "</pre>";
echo "</div>";

// Step 2: Check database connection
echo "<div class='step'>";
echo "<h2>Step 2: Verify Database Connection</h2>";

try {
    // Load .env file manually
    if (file_exists($appPath . '/.env')) {
        $envFile = file_get_contents($appPath . '/.env');
        $lines = explode("\n", $envFile);
        
        $env = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || empty(trim($line)))
                continue;
                
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                // Remove quotes if present
                if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                }
                putenv("$key=$value");
                $env[$key] = $value;
            }
        }
        
        echo "<p>Found and loaded .env file</p>";
    } else {
        echo "<p class='error'>.env file not found at {$appPath}/.env</p>";
    }
    
    // Get database connection info from environment
    $dbConnection = getenv('DB_CONNECTION') ?: 'mysql';
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_DATABASE') ?: 'faveo';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    
    echo "<pre>";
    echo "DB Connection: $dbConnection\n";
    echo "DB Host: $dbHost\n";
    echo "DB Port: $dbPort\n";
    echo "DB Name: $dbName\n";
    echo "DB User: $dbUser\n";
    echo "DB Pass: " . str_repeat('*', strlen($dbPass)) . "\n";
    echo "</pre>";
    
    // Attempt database connection
    try {
        $dsn = "$dbConnection:host=$dbHost;port=$dbPort;dbname=$dbName";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<p class='success'>Successfully connected to database!</p>";
        
        // Check if any tables exist
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) > 0) {
            echo "<p class='warning'>Database already contains " . count($tables) . " tables.</p>";
            echo "<pre>Existing tables: " . implode(", ", $tables) . "</pre>";
            echo "<p class='warning'>Running migrations may modify existing data. Proceed with caution!</p>";
        } else {
            echo "<p>Database is empty. Ready for migrations.</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Database connection failed: " . $e->getMessage() . "</p>";
        throw $e;
    }
} catch (Exception $e) {
    echo "<p class='error'>Error checking database: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Run migrations if confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    echo "<div class='step'>";
    echo "<h2>Step 3: Running Migrations</h2>";
    
    // Run migrations
    $migrationSuccess = runArtisan("migrate --force", $appPath);
    
    if ($migrationSuccess) {
        echo "<h3>Running Seeds</h3>";
        runArtisan("db:seed --force", $appPath);
    }
    
    echo "</div>";
    
    // Final status
    echo "<div class='step'>";
    echo "<h2>Migration Complete</h2>";
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    echo "<p>Execution time: $executionTime seconds</p>";
    
    if ($migrationSuccess) {
        echo "<p class='success'>Database setup completed successfully!</p>";
        echo "<p><a href='/public' target='_blank'>Go to Faveo Application</a></p>";
        echo "<p class='warning'>For security reasons, please remove this file after successful setup.</p>";
    } else {
        echo "<p class='error'>Database setup encountered errors. Please check the logs above.</p>";
    }
    echo "</div>";
} else {
    // Show confirmation form
    echo "<div class='step'>";
    echo "<h2>Step 3: Run Migrations</h2>";
    echo "<p class='warning'>Warning: This will run database migrations for Faveo. Make sure you have a backup if you're running this on an existing database.</p>";
    echo "<p>Click the button below to proceed with migrations:</p>";
    echo "<form method='get'>";
    echo "<input type='hidden' name='key' value='" . htmlspecialchars($securityKey) . "'>";
    echo "<input type='hidden' name='confirm' value='yes'>";
    echo "<button type='submit' style='padding: 10px 20px; background-color: #336699; color: white; border: none; border-radius: 4px; cursor: pointer;'>Run Migrations</button>";
    echo "</form>";
    echo "</div>";
}

echo "</body></html>"; 