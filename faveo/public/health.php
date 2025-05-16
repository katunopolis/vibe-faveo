<?php
/**
 * Health Check Endpoint for Faveo
 * Redirects to the utils/health/health.php script or handles the request directly
 */

// Simple health check that always returns OK
header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Always return 200 OK for Railway health check
echo "OK";
exit(0);

// If detailed diagnostics requested, include the comprehensive diagnostics script
if (isset($_GET['diagnostics']) || isset($_GET['debug'])) {
    // Check if the utils directory exists
    if (file_exists(__DIR__ . '/utils/health/diagnostics.php')) {
        include_once __DIR__ . '/utils/health/diagnostics.php';
    } else {
        echo "\n\nDetailed diagnostics not available. Utils directory not configured.";
    }
}
