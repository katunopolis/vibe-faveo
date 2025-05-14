<?php
/**
 * Health Check Endpoint for Faveo
 * Redirects to the utils/health/health.php script or handles the request directly
 */

// For Railway health checks, always return HTTP 200
header('Content-Type: text/plain');
echo "OK";
http_response_code(200);

// If detailed diagnostics requested, include the comprehensive diagnostics script
if (isset($_GET['diagnostics']) || isset($_GET['debug'])) {
    // Check if the utils directory exists
    if (file_exists(__DIR__ . '/utils/health/diagnostics.php')) {
        include_once __DIR__ . '/utils/health/diagnostics.php';
    } else {
        echo "\n\nDetailed diagnostics not available. Utils directory not configured.";
    }
}

exit(0);
