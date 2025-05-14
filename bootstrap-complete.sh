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

# Database connection setup
# This is where we parse database URLs and setup the connection
echo "Setting up database connection..."
DB_HOST=${MYSQLHOST:-"mysql.railway.internal"}
DB_PORT=${MYSQLPORT:-3306}
DB_DATABASE=${MYSQLDATABASE:-"railway"}
DB_USERNAME=${MYSQLUSER:-"root"}
DB_PASSWORD=${MYSQLPASSWORD:-""}

# Fix URL redirects
echo "Fixing URL redirect issues..."
# Detect correct application URL
if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
  CORRECT_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
else
  # Default to the Railway production URL if can't detect
  CORRECT_URL="https://vibe-faveo-production.up.railway.app"
fi
echo "Using application URL: $CORRECT_URL"

# 1. Update database settings
echo "Updating database URL settings..."
TABLES=("settings_system" "settings_ticket" "settings_email")

# Connect to database
MYSQL_CMD="mysql -u$DB_USERNAME -p$DB_PASSWORD -h$DB_HOST -P$DB_PORT $DB_DATABASE"

# Update settings_system table
echo "UPDATE settings_system SET url = '$CORRECT_URL';" | $MYSQL_CMD || {
  echo "WARNING: Could not update settings_system table"
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
        echo "WARNING: Could not update $TABLE table"
      }
    fi
  fi
done

# 2. Update .env file with correct APP_URL
echo "Updating .env file with correct URL..."
if [ -f /var/www/html/.env ]; then
  # Check if APP_URL exists in .env
  if grep -q "APP_URL=" /var/www/html/.env; then
    # Update existing APP_URL
    sed -i "s|APP_URL=.*|APP_URL=$CORRECT_URL|g" /var/www/html/.env || {
      echo "WARNING: Could not update APP_URL in .env file"
    }
  else
    # Add APP_URL if it doesn't exist
    echo "APP_URL=$CORRECT_URL" >> /var/www/html/.env || {
      echo "WARNING: Could not add APP_URL to .env file"
    }
  fi
fi

# 3. Create config override file with correct URL
echo "Creating URL config override..."
mkdir -p /var/www/html/config
cat > /var/www/html/config/override-url.php << EOF
<?php
// URL override for app config
return ['url' => '$CORRECT_URL'];
EOF

# 4. Create or update db_bootstrap.php to include URL override
echo "Updating bootstrap file with URL override..."
BOOTSTRAP_FILE="/var/www/html/public/db_bootstrap.php"
cat > "$BOOTSTRAP_FILE" << EOF
<?php
// Set URL and prevent redirects
\$_ENV['APP_URL'] = '$CORRECT_URL';
putenv('APP_URL=$CORRECT_URL');
EOF

# 5. Patch the index.php file to include the bootstrap file
echo "Patching index.php to include bootstrap file..."
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
fi

# 6. Clear Laravel caches
echo "Clearing Laravel caches..."
CACHE_DIRS=(
  "/var/www/html/bootstrap/cache/*.php"
  "/var/www/html/storage/framework/cache/data/*"
  "/var/www/html/storage/framework/views/*.php"
)

for DIR in "${CACHE_DIRS[@]}"; do
  find $DIR -type f -delete 2>/dev/null || true
done

# Fixed Apache VirtualHost config to prevent port issues
echo "Configuring Apache to prevent port issues..."
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

echo "URL redirect fix completed."
echo "Starting Apache..."
apache2-foreground 