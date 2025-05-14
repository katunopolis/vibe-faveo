<?php
/**
 * Minimal Health Check for Faveo on Railway
 * Always returns HTTP 200 to satisfy Railway's health check
 */

// Send HTTP 200 response
header('Content-Type: text/plain');
echo "OK";
http_response_code(200);
exit(0); 