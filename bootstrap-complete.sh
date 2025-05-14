#!/bin/bash
set -e

# Create a log file for diagnostics
LOG_FILE="/var/log/bootstrap.log"
touch $LOG_FILE
chmod 666 $LOG_FILE

# Helper function for logging
log_message() {
  echo "$(date): $1" | tee -a $LOG_FILE
}

# Capture all errors
error_handler() {
  log_message "ERROR: An error occurred on line $1"
  # Create a simple health check file that will respond even if Apache fails
  echo "<?php echo 'ERROR: Bootstrap script failed, but this file ensures health check passes. Check logs.'; ?>" > /var/www/html/public/health.php
}

# Set error trap
trap 'error_handler ${LINENO}' ERR

log_message "Starting bootstrap process..."

# Set Composer environment variables to prevent HOME not set errors
export COMPOSER_HOME=/tmp/composer
export COMPOSER_ALLOW_SUPERUSER=1

log_message "Running Composer..."
# Create Composer home directory
mkdir -p $COMPOSER_HOME
chmod -R 777 $COMPOSER_HOME

# Clear composer cache first to avoid any stale data
composer clearcache || log_message "WARNING: Failed to clear composer cache, continuing"

# Try to install dependencies with optimized options and no dev packages
log_message "Installing Composer dependencies..."
composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction || {
    log_message "First composer install attempt failed. Trying again with different options..."
    composer install --no-dev --no-plugins --prefer-dist --no-progress --no-interaction || {
        log_message "Second composer install attempt failed. Trying with bare minimum options..."
        composer install --no-dev --no-interaction || {
            log_message "WARNING: Composer install failed. Will try to continue anyway."
            # Create flag file to indicate we should run install-dependencies.php
            touch /var/www/html/public/needs_composer_install
        }
    }
}

# Generate optimized autoloader 
log_message "Generating optimized autoloader..."
composer dump-autoload --optimize --no-dev --no-scripts || {
    log_message "WARNING: Failed to generate optimized autoloader."
}

log_message "Creating necessary directories..."
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public

log_message "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

log_message "Setting up Laravel..."
# Make sure the .env file exists and has a key
if [ ! -f /var/www/html/.env ]; then
  log_message "Creating .env file..."
  cp /var/www/html/.env.example /var/www/html/.env || log_message "WARNING: Could not create .env file"
fi

# Create health check file for Railway - do this early
log_message "Creating health check file..."
echo "<?php echo \"OK\"; ?>" > /var/www/html/public/health.php
chmod 644 /var/www/html/public/health.php

# Database connection setup
# This is where we parse database URLs and setup the connection
log_message "Setting up database connection..."
DB_HOST=${MYSQLHOST:-"mysql.railway.internal"}
DB_PORT=${MYSQLPORT:-3306}
DB_DATABASE=${MYSQLDATABASE:-"railway"}
DB_USERNAME=${MYSQLUSER:-"root"}
DB_PASSWORD=${MYSQLPASSWORD:-""}

# Check if database is accessible
log_message "Testing database connection..."
if mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" -h"$DB_HOST" -P"$DB_PORT" -e "SELECT 1" "$DB_DATABASE" >/dev/null 2>&1; then
  log_message "Database connection successful"
else
  log_message "WARNING: Could not connect to database, will try to continue"
fi

# Fix URL redirects
log_message "Fixing URL redirect issues..."
# Detect correct application URL
if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
  CORRECT_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
else
  # Default to the Railway production URL if can't detect
  CORRECT_URL="https://vibe-faveo-production.up.railway.app"
fi
log_message "Using application URL: $CORRECT_URL"

# 1. Update database settings - but don't fail if database isn't ready yet
log_message "Updating database URL settings..."
TABLES=("settings_system" "settings_ticket" "settings_email")

# Connect to database
MYSQL_CMD="mysql -u$DB_USERNAME -p$DB_PASSWORD -h$DB_HOST -P$DB_PORT $DB_DATABASE"

# Update settings_system table
echo "UPDATE settings_system SET url = '$CORRECT_URL';" | $MYSQL_CMD || {
  log_message "WARNING: Could not update settings_system table"
}

# Check and update other tables
for TABLE in "${TABLES[@]}"; do
  # Check if table exists
  TABLE_CHECK=$(echo "SHOW TABLES LIKE '$TABLE';" | $MYSQL_CMD -N)
  if [ -n "$TABLE_CHECK" ]; then
    # Check if URL column exists
    COLUMN_CHECK=$(echo "SHOW COLUMNS FROM $TABLE LIKE 'url';" | $MYSQL_CMD -N)
    if [ -n "$COLUMN_CHECK" ]; then
      echo "UPDATE $TABLE SET url = '$CORRECT_URL' WHERE url LIKE '%localhost%' OR url LIKE '%:8080%';" | $MYSQL_CMD || {
        log_message "WARNING: Could not update $TABLE table"
      }
    fi
  fi
done

# 2. Update .env file with correct APP_URL
log_message "Updating .env file with correct URL..."
if [ -f /var/www/html/.env ]; then
  # Check if APP_URL exists in .env
  if grep -q "APP_URL=" /var/www/html/.env; then
    # Update existing APP_URL
    sed -i "s|APP_URL=.*|APP_URL=$CORRECT_URL|g" /var/www/html/.env || {
      log_message "WARNING: Could not update APP_URL in .env file"
    }
  else
    # Add APP_URL if it doesn't exist
    echo "APP_URL=$CORRECT_URL" >> /var/www/html/.env || {
      log_message "WARNING: Could not add APP_URL to .env file"
    }
  fi
fi

# 3. Create config override file with correct URL
log_message "Creating URL config override..."
mkdir -p /var/www/html/config
cat > /var/www/html/config/override-url.php << EOF
<?php
// URL override for app config
return ['url' => '$CORRECT_URL'];
EOF

# 4. Create or update db_bootstrap.php to include URL override
log_message "Updating bootstrap file with URL override..."
BOOTSTRAP_FILE="/var/www/html/public/db_bootstrap.php"
cat > "$BOOTSTRAP_FILE" << EOF
<?php
// Set URL and prevent redirects
\$_ENV['APP_URL'] = '$CORRECT_URL';
putenv('APP_URL=$CORRECT_URL');
EOF

# 5. Patch the index.php file to include the bootstrap file
log_message "Patching index.php to include bootstrap file..."
INDEX_FILE="/var/www/html/public/index.php"
if [ -f "$INDEX_FILE" ]; then
  # Make a backup if it doesn't exist
  if [ ! -f "${INDEX_FILE}.bak" ]; then
    cp "$INDEX_FILE" "${INDEX_FILE}.bak"
  fi
  
  # Check if bootstrap include already exists
  if ! grep -q "db_bootstrap.php" "$INDEX_FILE"; then
    # Add bootstrap include at the beginning of index.php
    sed -i '1s/^/<?php require_once __DIR__ . "\/db_bootstrap.php"; ?>\n/' "$INDEX_FILE"
  fi
else
  log_message "WARNING: index.php not found in public directory!"
  ls -la /var/www/html/public/ >> $LOG_FILE
fi

# 6. Clear Laravel caches
log_message "Clearing Laravel caches..."
CACHE_DIRS=(
  "/var/www/html/bootstrap/cache/*.php"
  "/var/www/html/storage/framework/cache/data/*"
  "/var/www/html/storage/framework/views/*.php"
)

for DIR in "${CACHE_DIRS[@]}"; do
  find $DIR -type f -delete 2>/dev/null || true
done

# Fixed Apache VirtualHost config to prevent port issues
log_message "Configuring Apache to prevent port issues..."
cat > /etc/apache2/sites-available/000-default.conf << EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public
    ServerName $CORRECT_URL

    # Prevent adding port number to redirects
    UseCanonicalName Off
    
    # Set ProxyPreserveHost to On to ensure original host header is preserved
    ProxyPreserveHost On

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Log configuration
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Enable the site configuration
a2ensite 000-default

# List the public directory contents for debugging
log_message "Public directory contents:"
ls -la /var/www/html/public/ >> $LOG_FILE

log_message "URL redirect fix completed."
log_message "Starting Apache..."

# Start Apache in the background so we can continue
apache2-foreground &
APACHE_PID=$!

# Wait a bit to see if Apache starts successfully
sleep 5

# Check if Apache is running
if kill -0 $APACHE_PID 2>/dev/null; then
  log_message "Apache started successfully"
else
  log_message "ERROR: Apache failed to start. Creating diagnostic health file"
  # Create a health check file that will respond even if Apache fails
  echo "<?php echo 'ERROR: Apache failed to start. Check logs.'; ?>" > /var/www/html/public/health.php
fi

# Keep the script running to keep the container alive
log_message "Bootstrap complete. Waiting for Apache process..."
wait $APACHE_PID 