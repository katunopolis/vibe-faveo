<?php
/**
 * Simple health check file for Railway deployment
 * 
 * This file will return a 200 OK response with "OK" text,
 * which allows Railway to verify that the application is running.
 */

// Disable error reporting for this health check
error_reporting(0);
ini_set('display_errors', 0);

// Set content type to plain text
header('Content-Type: text/plain');

// Return OK status
echo "OK";
exit(0); 