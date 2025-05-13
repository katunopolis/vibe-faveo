#!/bin/bash
set -e

echo "Running Composer..."
composer clearcache
composer install --no-scripts --no-autoloader || true
composer dump-autoload --optimize --no-scripts || true

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

# Ensure we have a key in the .env file
if ! grep -q "^APP_KEY=" /var/www/html/.env || grep -q "^APP_KEY=$" /var/www/html/.env; then
  echo "APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=" >> /var/www/html/.env
fi

# Try clearing caches with simple commands
echo "Clearing Laravel caches..."
php -r "if (file_exists(\"bootstrap/cache/config.php\")) @unlink(\"bootstrap/cache/config.php\");" || true
php -r "if (file_exists(\"bootstrap/cache/routes.php\")) @unlink(\"bootstrap/cache/routes.php\");" || true
php -r "if (is_dir(\"storage/framework/views\")) { \$files = glob(\"storage/framework/views/*.php\"); if (\$files) { array_map(\"unlink\", \$files); }}" || true

# Create health check file for Railway
echo "<?php echo \"OK\"; ?>" > /var/www/html/public/health.php

# Handle Railway environment
if [ -n "$RAILWAY_ENVIRONMENT" ]; then
  echo "Running in Railway environment..."
  # Debug MySQL environment variables
  echo "MySQL Environment Variables:"
  echo "MYSQLHOST: ${MYSQLHOST:-not set}"
  echo "MYSQLPORT: ${MYSQLPORT:-not set}"
  echo "MYSQLDATABASE: ${MYSQLDATABASE:-not set}"
  echo "MYSQLUSER: ${MYSQLUSER:-not set}"
  echo "MYSQLPASSWORD: ${MYSQLPASSWORD:+is set}"
  
  # Check if MySQL variables are available
  if [ -n "$MYSQLHOST" ] && [ -n "$MYSQLUSER" ] && [ -n "$MYSQLPASSWORD" ]; then
    echo "MySQL environment variables found. Using them for database configuration."
    # Set database connection using Railway environment variables
    sed -i "s/DB_HOST=.*/DB_HOST=${MYSQLHOST}/" /var/www/html/.env || true
    sed -i "s/DB_PORT=.*/DB_PORT=${MYSQLPORT:-3306}/" /var/www/html/.env || true
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=${MYSQLDATABASE:-railway}/" /var/www/html/.env || true
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=${MYSQLUSER}/" /var/www/html/.env || true
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${MYSQLPASSWORD}/" /var/www/html/.env || true
    
    # Test database connection
    echo "Testing database connection..."
    if php -r "try { new PDO('mysql:host=$MYSQLHOST;port=${MYSQLPORT:-3306};dbname=${MYSQLDATABASE:-railway}', '$MYSQLUSER', '$MYSQLPASSWORD'); echo 'Connection successful!'; } catch (PDOException \$e) { echo 'Connection failed: ' . \$e->getMessage(); exit(1); }"; then
      echo "Database connection successful! Will run migrations."
      # Create a simple database migration script
      echo "<?php
      require_once __DIR__ . '/../vendor/autoload.php';
      try {
        \$conn = new PDO('mysql:host=$MYSQLHOST;port=${MYSQLPORT:-3306};dbname=${MYSQLDATABASE:-railway}', '$MYSQLUSER', '$MYSQLPASSWORD');
        \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo 'Connected to database. Running migrations...\n';
        system('cd /var/www/html && php artisan migrate --force');
        echo 'Migrations completed.\n';
      } catch(PDOException \$e) {
        echo 'Error running migrations: ' . \$e->getMessage();
      }
      ?>" > /var/www/html/public/run-migrations-now.php
      
      # Run migrations
      echo "Running migrations..."
      php /var/www/html/public/run-migrations-now.php
    else
      echo "Database connection failed. Skipping migrations."
    fi
  else
    echo "WARNING: MySQL environment variables not found. Using fallback values."
    # Use fallback values
    sed -i "s/DB_HOST=.*/DB_HOST=mysql.railway.internal/" /var/www/html/.env || true
    sed -i "s/DB_PORT=.*/DB_PORT=3306/" /var/www/html/.env || true
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=railway/" /var/www/html/.env || true
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=root/" /var/www/html/.env || true
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=/" /var/www/html/.env || true
  fi
  # Set trusted proxies for Railway
  sed -i "s/APP_URL=.*/APP_URL=${APP_URL:-http:\/\/localhost}/" /var/www/html/.env || true
  # Set Apache ServerName to suppress the warning
  echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
  a2enconf servername
  # Use PORT from Railway if available
  if [ -n "$PORT" ]; then
    echo "Setting up Apache for port $PORT..."
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf || true
    sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf || true
  fi
else
  # Local development environment settings
  sed -i "s/DB_HOST=.*/DB_HOST=db/" /var/www/html/.env || true
  sed -i "s/DB_DATABASE=.*/DB_DATABASE=faveo/" /var/www/html/.env || true
  sed -i "s/DB_USERNAME=.*/DB_USERNAME=faveo/" /var/www/html/.env || true
  sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=faveo_password/" /var/www/html/.env || true
fi

echo "Starting Apache..."
apache2-foreground 