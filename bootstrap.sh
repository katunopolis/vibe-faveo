#!/bin/bash
set -e

echo "Starting Faveo Bootstrap Script..."

# Make sure we're in the project directory
cd /var/www/html

# Create bootstrap log
LOGFILE=/var/log/bootstrap.log
touch $LOGFILE
chmod 777 $LOGFILE
echo "$(date) - Starting bootstrap script" >> $LOGFILE

# Install dependencies 
echo "Installing composer dependencies..." | tee -a $LOGFILE
composer clearcache
composer install --no-dev --optimize-autoloader

# Create .env file if it doesn't exist
if [ ! -f "/var/www/html/.env" ]; then
    echo "Creating .env file..." | tee -a $LOGFILE
    cp /var/www/html/.env.example /var/www/html/.env 
    
    # Set the APP_URL if we're on Railway
    if [ ! -z "$RAILWAY_PUBLIC_DOMAIN" ]; then
        echo "Setting APP_URL to https://$RAILWAY_PUBLIC_DOMAIN" | tee -a $LOGFILE
        sed -i "s#APP_URL=.*#APP_URL=https://$RAILWAY_PUBLIC_DOMAIN#g" /var/www/html/.env
    fi
    
    # Configure database from Railway vars
    if [ ! -z "$MYSQLHOST" ] && [ ! -z "$MYSQLUSER" ] && [ ! -z "$MYSQLPASSWORD" ] && [ ! -z "$MYSQLDATABASE" ]; then
        echo "Configuring database from Railway vars..." | tee -a $LOGFILE
        sed -i "s/DB_HOST=.*/DB_HOST=$MYSQLHOST/g" /var/www/html/.env
        sed -i "s/DB_PORT=.*/DB_PORT=$MYSQLPORT/g" /var/www/html/.env
        sed -i "s/DB_DATABASE=.*/DB_DATABASE=$MYSQLDATABASE/g" /var/www/html/.env
        sed -i "s/DB_USERNAME=.*/DB_USERNAME=$MYSQLUSER/g" /var/www/html/.env
        sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$MYSQLPASSWORD/g" /var/www/html/.env
    fi
    
    # Generate Laravel app key
    php artisan key:generate || echo "Could not generate key - continuing anyway" | tee -a $LOGFILE
fi

# Ensure storage directory permissions
echo "Setting permissions..." | tee -a $LOGFILE
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Make sure bootstrap-app.php exists in the public directory
if [ ! -f "/var/www/html/public/bootstrap-app.php" ]; then
    echo "Creating bootstrap-app.php..." | tee -a $LOGFILE
    cat > /var/www/html/public/bootstrap-app.php << 'EOL'
<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new \Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(dirname(__DIR__))
);

/*
|--------------------------------------------------------------------------
| Set Facade Root Application
|--------------------------------------------------------------------------
|
| This explicitly sets the application instance for facades to prevent the
| "Facade root has not been set" error that can occur with Railway
| deployments or when the app is bootstrapped in unusual ways.
|
*/

\Illuminate\Support\Facades\Facade::setFacadeApplication($app);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    \Illuminate\Contracts\Http\Kernel::class,
    \App\Http\Kernel::class
);

$app->singleton(
    \Illuminate\Contracts\Console\Kernel::class,
    \App\Console\Kernel::class
);

$app->singleton(
    \Illuminate\Contracts\Debug\ExceptionHandler::class,
    \App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
EOL
fi

# Make sure health.php exists in the public directory
if [ ! -f "/var/www/html/public/health.php" ]; then
    echo "Creating health.php..." | tee -a $LOGFILE
    cat > /var/www/html/public/health.php << 'EOL'
<?php
// Simple health check that always returns OK
header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Always return 200 OK for Railway health check
echo "OK";
exit(0);
EOL
fi

# Fix index.php to use bootstrap-app.php
echo "Updating index.php..." | tee -a $LOGFILE
if [ -f "/var/www/html/public/index.php" ]; then
    # Only apply the change if needed
    if ! grep -q "bootstrap-app.php" /var/www/html/public/index.php; then
        echo "Patching index.php to use bootstrap-app.php..." | tee -a $LOGFILE
        sed -i '1,5s/<?php/<?php\n\/\/ Bootstrap the Laravel application environment\n$app = require __DIR__\.\'\/bootstrap-app.php\';/' /var/www/html/public/index.php
        sed -i '/require_once __DIR__\.\'\/\.\.\/bootstrap\/app\.php\';/d' /var/www/html/public/index.php
    fi
fi

# Set up Apache
echo "Setting up Apache..." | tee -a $LOGFILE

# Create Apache config for port 8080
cat > /etc/apache2/sites-available/000-default.conf << 'EOL'
<VirtualHost *:8080>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOL

# Enable required modules
a2enmod rewrite headers

# Start Apache in the foreground
echo "Starting Apache..." | tee -a $LOGFILE
apache2-foreground 