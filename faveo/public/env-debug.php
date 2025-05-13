<?php
// .env debugging script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>.env File Debugging</h1>";

// Try to find and read the .env file
$envLocations = [
    __DIR__ . '/../.env',             // Normal Laravel location
    __DIR__ . '/../../.env',          // One level up
    '/var/www/html/.env',             // Absolute path inside container
    '/app/.env'                       // Another common container path
];

echo "<h2>Searching for .env file</h2>";
echo "<ul>";
foreach ($envLocations as $path) {
    echo "<li>Checking $path: ";
    if (file_exists($path)) {
        echo "<span style='color:green'>Found</span>";
        $envPath = $path;
        echo " (size: " . filesize($path) . " bytes)";
    } else {
        echo "<span style='color:red'>Not found</span>";
    }
    echo "</li>";
}
echo "</ul>";

// If found, show details about the .env file
if (isset($envPath)) {
    echo "<h2>.env File Details</h2>";
    echo "<p>Path: $envPath</p>";
    echo "<p>Size: " . filesize($envPath) . " bytes</p>";
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($envPath)), -4) . "</p>";
    echo "<p>Owner: " . posix_getpwuid(fileowner($envPath))['name'] . "</p>";
    echo "<p>Last modified: " . date("Y-m-d H:i:s", filemtime($envPath)) . "</p>";
    
    echo "<h3>File Contents</h3>";
    echo "<pre>";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        // Mask passwords
        if (strpos($line, "PASSWORD") !== false) {
            $parts = explode('=', $line, 2);
            if (count($parts) > 1) {
                echo htmlspecialchars($parts[0] . "=***MASKED***") . "\n";
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        } else {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
    
    // Check if the file can be updated
    echo "<h3>File Write Test</h3>";
    if (is_writable($envPath)) {
        echo "<p style='color:green'>✓ The .env file is writable!</p>";
        
        // Try writing to it
        try {
            $testContent = "\n# Test line added at " . date("Y-m-d H:i:s");
            file_put_contents($envPath, $testContent, FILE_APPEND);
            echo "<p style='color:green'>✓ Successfully wrote test content to the file</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Error writing to file: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red'>✗ The .env file is NOT writable</p>";
    }
} else {
    echo "<h2 style='color:red'>No .env file found in any of the searched locations</h2>";
    
    // Let's try to create one
    echo "<h3>Attempting to create .env file</h3>";
    $createPath = '/var/www/html/.env';
    try {
        $content = "APP_NAME=Faveo\n";
        $content .= "DB_CONNECTION=mysql\n";
        $content .= "DB_HOST=" . (getenv('MYSQLHOST') ?: 'mysql.railway.internal') . "\n";
        $content .= "DB_PORT=" . (getenv('MYSQLPORT') ?: '3306') . "\n";
        $content .= "DB_DATABASE=" . (getenv('MYSQLDATABASE') ?: 'railway') . "\n";
        $content .= "DB_USERNAME=" . (getenv('MYSQLUSER') ?: 'root') . "\n";
        $content .= "DB_PASSWORD=" . (getenv('MYSQLPASSWORD') ?: '') . "\n";
        
        $result = file_put_contents($createPath, $content);
        if ($result !== false) {
            echo "<p style='color:green'>✓ Successfully created new .env file at $createPath</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to create .env file</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Error creating .env file: " . $e->getMessage() . "</p>";
    }
}

// Find other .env files in common directories
echo "<h2>Searching for other .env files</h2>";
$searchDirs = [
    '/var/www', 
    '/app',
    '/var/www/html'
];

foreach ($searchDirs as $dir) {
    if (is_dir($dir)) {
        echo "<h3>Searching in $dir</h3>";
        $command = "find $dir -name '.env' -type f 2>/dev/null";
        $output = [];
        exec($command, $output);
        
        if (empty($output)) {
            echo "<p>No .env files found</p>";
        } else {
            echo "<ul>";
            foreach ($output as $file) {
                echo "<li>$file (size: " . filesize($file) . " bytes)</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>Directory $dir does not exist</p>";
    }
}

// Show environment variables
echo "<h2>Current Environment Variables</h2>";
echo "<pre>";
$envVars = getenv();
foreach ($envVars as $key => $value) {
    // Mask sensitive values
    if (stripos($key, 'PASSWORD') !== false || 
        stripos($key, 'SECRET') !== false || 
        stripos($key, 'KEY') !== false) {
        echo htmlspecialchars("$key = ***MASKED***") . "\n";
    } else {
        echo htmlspecialchars("$key = $value") . "\n";
    }
}
echo "</pre>";
?> 