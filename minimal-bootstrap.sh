#!/bin/bash
# Minimal bootstrap script for Faveo on Railway

# Create a log file for diagnostics
LOG_FILE="/var/log/bootstrap.log"
touch $LOG_FILE
chmod 666 $LOG_FILE

# Helper function for logging
log_message() {
  echo "$(date): $1" | tee -a $LOG_FILE
}

log_message "Starting minimal bootstrap process..."

# Create health check file immediately
log_message "Creating health check file..."
echo "<?php echo 'OK'; http_response_code(200);" > /var/www/html/public/health.php
chmod 644 /var/www/html/public/health.php

# Create necessary directories
log_message "Creating necessary directories..."
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public

# Set permissions
log_message "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create minimal Apache config
log_message "Creating minimal Apache config..."
cat > /etc/apache2/sites-available/000-default.conf << EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public
    
    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Enable the site
a2ensite 000-default

# Make sure .env exists
if [ ! -f /var/www/html/.env ]; then
  log_message "Creating .env file..."
  cp /var/www/html/.env.example /var/www/html/.env || log_message "WARNING: Could not create .env file"
fi

# List files in public dir
log_message "Files in public directory:"
ls -la /var/www/html/public >> $LOG_FILE

# Start Apache
log_message "Starting Apache..."
apache2-foreground 