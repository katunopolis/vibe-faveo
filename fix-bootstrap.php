<?php
/**
 * Faveo Helpdesk Bootstrap Fixer
 * This script fixes common issues with Faveo running on Railway
 */

echo "Faveo Bootstrap Fixer\n";
echo "=====================\n\n";

// Functions
function checkAndFixPermissions($path, $recursive = false) {
    echo "Checking permissions for {$path}...\n";
    
    if (!file_exists($path)) {
        echo "  WARNING: Path {$path} does not exist! Creating it...\n";
        if (!mkdir($path, 0775, true)) {
            echo "  ERROR: Could not create {$path}!\n";
            return false;
        }
    }
    
    // Make sure directory is writable
    if (!is_writable($path)) {
        echo "  Fixing permissions for {$path}...\n";
        chmod($path, 0775);
        
        // Check if it worked
        if (!is_writable($path)) {
            echo "  ERROR: Could not make {$path} writable!\n";
            return false;
        }
    } else {
        echo "  {$path} is writable. ✓\n";
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
                    echo "  Fixing permissions for file {$fullPath}...\n";
                    chmod($fullPath, 0664);
                }
            }
        }
    }
    
    return true;
}

function checkAndFixBootstrapApp() {
    echo "Checking bootstrap/app.php...\n";
    
    $bootstrapAppPath = __DIR__ . '/../bootstrap/app.php';
    $indexPath = __DIR__ . '/index.php';
    
    // Check if bootstrap/app.php exists
    if (!file_exists($bootstrapAppPath)) {
        echo "  ERROR: bootstrap/app.php does not exist!\n";
        return false;
    }
    
    // Check if index.php exists
    if (!file_exists($indexPath)) {
        echo "  ERROR: index.php does not exist!\n";
        return false;
    }
    
    // Read index.php
    $indexContent = file_get_contents($indexPath);
    
    // Check if bootstrap/app.php is properly required
    if (strpos($indexContent, 'bootstrap/app.php') === false) {
        echo "  WARNING: bootstrap/app.php is not included in index.php. Fixing...\n";
        
        // Backup index.php
        file_put_contents($indexPath . '.bak', $indexContent);
        
        // Add the require statement for bootstrap/app.php at the correct position
        $fixedContent = preg_replace(
            '/(\$app = require_once __DIR__.\'\/\.\.\/bootstrap\/app\.php\';)/',
            '$1',
            $indexContent
        );
        
        if ($fixedContent === $indexContent) {
            // If preg_replace didn't find the pattern, we need to add it
            $fixedContent = preg_replace(
                '/(require __DIR__.\'\/\.\.\/vendor\/autoload\.php\';)/',
                '$1' . PHP_EOL . PHP_EOL . '$app = require_once __DIR__.\'/../bootstrap/app.php\';',
                $indexContent
            );
        }
        
        // Write fixed content back to index.php
        if (file_put_contents($indexPath, $fixedContent)) {
            echo "  Fixed index.php to include bootstrap/app.php. ✓\n";
        } else {
            echo "  ERROR: Could not write to index.php!\n";
            return false;
        }
    } else {
        echo "  bootstrap/app.php is properly included in index.php. ✓\n";
    }
    
    return true;
}

// Execute fixes

// 1. Check and fix storage directory permissions
echo "\n[1/4] Fixing storage directory permissions...\n";
checkAndFixPermissions(__DIR__ . '/../storage', true);

// 2. Check and fix bootstrap/cache directory permissions
echo "\n[2/4] Fixing bootstrap/cache directory permissions...\n";
checkAndFixPermissions(__DIR__ . '/../bootstrap/cache', true);

// 3. Check and fix bootstrap/app.php inclusion
echo "\n[3/4] Checking bootstrap/app.php inclusion...\n";
checkAndFixBootstrapApp();

// 4. Check for Laravel Facade Root errors
echo "\n[4/4] Checking for Laravel Facade Root errors...\n";
if (file_exists(__DIR__ . '/../bootstrap/app.php')) {
    echo "  bootstrap/app.php exists. Checking content...\n";
    
    // Create a bootstrap-app.php file that properly initializes the application container
    $bootstrapAppContent = file_get_contents(__DIR__ . '/../bootstrap/app.php');
    
    if (strpos($bootstrapAppContent, 'Facade::setFacadeApplication') === false) {
        echo "  NOTE: The bootstrap/app.php file does not contain Facade::setFacadeApplication.\n";
        echo "  This is not necessarily an error, but if you're seeing Facade Root errors, you may need to fix it.\n";
    } else {
        echo "  bootstrap/app.php contains Facade::setFacadeApplication. ✓\n";
    }
} else {
    echo "  ERROR: bootstrap/app.php does not exist!\n";
}

echo "\nFixes completed. If you're still having issues, please check bootstrap.sh and permanent-url-fix.sh.\n"; 