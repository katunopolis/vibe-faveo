<?php
/**
 * Alternative Index File for Faveo
 * 
 * This file provides an alternative way to bootstrap the Laravel application
 * while bypassing potential facade root issues.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define LARAVEL_START
define('LARAVEL_START', microtime(true));

// First include our facade fix
$app = require_once __DIR__ . '/facade-fix.php';

// Verify the application is properly initialized
if (!$app || !($app instanceof Illuminate\Foundation\Application)) {
    die("Failed to initialize Laravel application. Please check facade-fix.php.");
}

try {
    // Access the HTTP kernel
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    // Handle the request
    $response = $kernel->handle(
        $request = Illuminate\Http\Request::capture()
    );
    
    // Send the response
    $response->send();
    
    // Terminate the kernel
    $kernel->terminate($request, $response);
} catch (Exception $e) {
    // Display error details
    echo "<h1>Application Error</h1>";
    echo "<p>Error message: " . $e->getMessage() . "</p>";
    echo "<p>Error code: " . $e->getCode() . "</p>";
    echo "<p>File: " . $e->getFile() . " on line " . $e->getLine() . "</p>";
    echo "<h2>Stack Trace:</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    
    // Suggest solutions
    echo "<h2>Possible Solutions</h2>";
    echo "<ul>";
    echo "<li>Check <a href='db-test.php'>database connection</a></li>";
    echo "<li>Run <a href='fix-permissions.php'>permission fixer</a></li>";
    echo "<li>Try <a href='memory-only-fix.php'>memory-only fix</a></li>";
    echo "<li>Try <a href='direct-migration.php'>direct database migration</a> if you haven't already</li>";
    echo "</ul>";
} 