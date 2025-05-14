#!/bin/bash
set -e

# Set Composer environment variables to prevent HOME not set errors
export COMPOSER_HOME=/tmp/composer
export COMPOSER_ALLOW_SUPERUSER=1

echo "Running Composer..."
# Create Composer home directory
mkdir -p $COMPOSER_HOME
chmod -R 777 $COMPOSER_HOME

# Clear composer cache first to avoid any stale data
composer clearcache

# Try to install dependencies with optimized options and no dev packages
echo "Installing Composer dependencies..."
composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction || {
    echo "First composer install attempt failed. Trying again with different options..."
    composer install --no-dev --no-plugins --prefer-dist --no-progress --no-interaction || {
        echo "Second composer install attempt failed. Trying with bare minimum options..."
        composer install --no-dev --no-interaction || {
            echo "WARNING: Composer install failed. Will try to continue anyway."
            # Create flag file to indicate we should run install-dependencies.php
            touch /var/www/html/public/needs_composer_install
        }
    }
}

# Generate optimized autoloader 
echo "Generating optimized autoloader..."
composer dump-autoload --optimize --no-dev --no-scripts || {
    echo "WARNING: Failed to generate optimized autoloader."
}

echo "Creating necessary directories..."
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public

echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Setting up Laravel..."
# Make sure the .env file exists and has a key
if [ ! -f /var/www/html/.env ]; then
  echo "Creating .env file..."
  cp /var/www/html/.env.example /var/www/html/.env || true
fi

# Create health check file for Railway
echo "<?php echo \"OK\"; ?>" > /var/www/html/public/health.php

# Fix bootstrap issues
echo "Running bootstrap fixes..."
# Source the URL fix script
if [ -f "/var/www/html/public/fix-bootstrap.php" ]; then
  cd /var/www/html
  php public/fix-bootstrap.php
else
  echo "WARNING: fix-bootstrap.php not found, skipping bootstrap fixes"
fi

# Fix URL redirects
echo "Fixing URL redirect issues..."
# Source the URL fix script
if [ -f "/usr/local/bin/permanent-url-fix.sh" ]; then
  source /usr/local/bin/permanent-url-fix.sh
  fix_url_redirect
else
  echo "WARNING: permanent-url-fix.sh not found, skipping URL fix"
fi

echo "Starting Apache..."
apache2-foreground 