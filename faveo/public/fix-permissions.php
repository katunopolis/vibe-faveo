<?php
// Permission fixing script for Faveo on Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fixing Faveo Permissions</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; border-radius: 4px; }
        p.error { color: #e74c3c; font-weight: bold; }
        p.success { color: #2ecc71; font-weight: bold; }
        p.warning { color: #f39c12; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Fixing Faveo Permissions</h1>";

echo "<h2>File System Information</h2>";
echo "<pre>";
echo "Current User: " . exec('whoami') . "\n";
echo "User ID: " . posix_getuid() . "\n";
echo "Group ID: " . posix_getgid() . "\n";
echo "</pre>";

$web_user = 'www-data';
$app_path = '/var/www/html';

echo "<h2>Directory Ownership and Permissions</h2>";

// Check current permissions
$dirs_to_check = [
    "$app_path/storage",
    "$app_path/bootstrap/cache",
    "$app_path/public",
];

foreach ($dirs_to_check as $dir) {
    echo "<h3>$dir</h3>";
    
    if (!file_exists($dir)) {
        echo "<p class='error'>Directory does not exist!</p>";
        continue;
    }
    
    $stat = stat($dir);
    $owner = posix_getpwuid($stat['uid']);
    $group = posix_getgrgid($stat['gid']);
    
    echo "<p>Current owner: {$owner['name']} (UID: {$stat['uid']})</p>";
    echo "<p>Current group: {$group['name']} (GID: {$stat['gid']})</p>";
    echo "<p>Current permissions: " . substr(sprintf('%o', $stat['mode']), -4) . "</p>";
}

echo "<h2>Executing Permission Fixes</h2>";

$commands = [
    "chown -R www-data:www-data $app_path/storage",
    "chown -R www-data:www-data $app_path/bootstrap/cache",
    "chmod -R 775 $app_path/storage",
    "chmod -R 775 $app_path/bootstrap/cache",
    "find $app_path/storage -type d -exec chmod 775 {} \\;",
    "find $app_path/storage -type f -exec chmod 664 {} \\;",
    "mkdir -p $app_path/storage/app/public",
    "mkdir -p $app_path/storage/framework/cache/data",
    "mkdir -p $app_path/storage/framework/sessions",
    "mkdir -p $app_path/storage/framework/views",
    "php $app_path/artisan config:clear",
    "php $app_path/artisan cache:clear",
    "php $app_path/artisan route:clear",
    "php $app_path/artisan view:clear"
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

echo "<h2>Verifying Storage Directory Structure</h2>";

$required_dirs = [
    "$app_path/storage/app",
    "$app_path/storage/app/public",
    "$app_path/storage/framework",
    "$app_path/storage/framework/cache",
    "$app_path/storage/framework/cache/data",
    "$app_path/storage/framework/sessions",
    "$app_path/storage/framework/views",
    "$app_path/storage/logs",
    "$app_path/bootstrap/cache"
];

$all_exists = true;
foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        $all_exists = false;
        echo "<p class='error'>Missing directory: $dir</p>";
        
        // Try to create it
        echo "<p>Attempting to create directory...</p>";
        if (mkdir($dir, 0775, true)) {
            echo "<p class='success'>Created directory: $dir</p>";
        } else {
            echo "<p class='error'>Failed to create directory: $dir</p>";
        }
    } else {
        echo "<p class='success'>Directory exists: $dir</p>";
    }
}

if ($all_exists) {
    echo "<p class='success'>All required directories exist!</p>";
}

echo "<h2>Checking Laravel Cache Files</h2>";

$cache_files = [
    "$app_path/bootstrap/cache/config.php",
    "$app_path/bootstrap/cache/routes.php",
    "$app_path/bootstrap/cache/services.php"
];

foreach ($cache_files as $file) {
    if (file_exists($file)) {
        echo "<p>Found cache file: $file</p>";
        
        // Check permissions
        $stat = stat($file);
        $perms = substr(sprintf('%o', $stat['mode']), -4);
        
        if ($perms != '0664' && $perms != '0644') {
            echo "<p class='warning'>Cache file has unusual permissions: $perms</p>";
            echo "<p>Setting correct permissions...</p>";
            
            if (chmod($file, 0664)) {
                echo "<p class='success'>Set permissions to 0664</p>";
            } else {
                echo "<p class='error'>Failed to set permissions</p>";
            }
        }
    } else {
        echo "<p>Cache file not present: $file (This is normal if caches were cleared)</p>";
    }
}

echo "<h2>Next Steps</h2>";
echo "<p>Permissions have been updated. You should now be able to use Faveo without permission issues.</p>";
echo "<p><a href='/public'>Go to Faveo</a></p>";
echo "<p>If you still experience issues, try restarting the application.</p>";

echo "<h2>Navigation</h2>
<ul>
    <li><a href='run-migrations.php'>Run Migrations</a></li>
    <li><a href='repair-database.php'>Repair Database</a></li>
    <li><a href='create-admin.php'>Create Admin User</a></li>
    <li><a href='/public'>Go to Faveo</a></li>
</ul>
</body>
</html>"; 