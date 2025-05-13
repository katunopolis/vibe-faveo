FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    npm \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libc-client-dev \
    libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install \
    pdo_mysql \
    zip \
    gd \
    mbstring \
    exif \
    pcntl \
    bcmath \
    xml \
    imap

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Move Faveo files to the correct location
RUN mv faveo/* . && rm -rf faveo

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create .env file
RUN echo "APP_NAME=Faveo\n\
APP_ENV=local\n\
APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=\n\
APP_DEBUG=true\n\
APP_URL=http://localhost:8080\n\
\n\
LOG_CHANNEL=stack\n\
LOG_LEVEL=debug\n\
\n\
DB_CONNECTION=mysql\n\
DB_HOST=db\n\
DB_PORT=3306\n\
DB_DATABASE=faveo\n\
DB_USERNAME=faveo\n\
DB_PASSWORD=faveo_password\n\
\n\
BROADCAST_DRIVER=log\n\
CACHE_DRIVER=file\n\
FILESYSTEM_DISK=local\n\
QUEUE_CONNECTION=sync\n\
SESSION_DRIVER=file\n\
SESSION_LIFETIME=120\n\
\n\
MAIL_MAILER=smtp\n\
MAIL_HOST=mailpit\n\
MAIL_PORT=1025\n\
MAIL_USERNAME=null\n\
MAIL_PASSWORD=null\n\
MAIL_ENCRYPTION=null\n\
MAIL_FROM_ADDRESS=\"hello@example.com\"\n\
MAIL_FROM_NAME=\"\${APP_NAME}\"\n\
\n\
FCM_SERVER_KEY=\n\
FCM_SENDER_ID=" > .env

# Create bootstrap script
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
echo "Running Composer..."\n\
composer clearcache\n\
composer install --no-scripts --no-autoloader || true\n\
composer dump-autoload --optimize --no-scripts || true\n\
\n\
echo "Creating necessary directories..."\n\
mkdir -p /var/www/html/storage/framework/cache/data\n\
mkdir -p /var/www/html/storage/framework/sessions\n\
mkdir -p /var/www/html/storage/framework/views\n\
mkdir -p /var/www/html/storage/app/public\n\
\n\
echo "Setting permissions..."\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache\n\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache\n\
\n\
echo "Setting up Laravel..."\n\
# Make sure the .env file exists and has a key\n\
if [ ! -f /var/www/html/.env ]; then\n\
  echo "Creating .env file..."\n\
  cp /var/www/html/.env.example /var/www/html/.env || true\n\
fi\n\
\n\
# Ensure we have a key in the .env file\n\
if ! grep -q "^APP_KEY=" /var/www/html/.env || grep -q "^APP_KEY=$" /var/www/html/.env; then\n\
  echo "APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=" >> /var/www/html/.env\n\
fi\n\
\n\
# Try clearing caches with simple commands\n\
echo "Clearing Laravel caches..."\n\
php -r "if (file_exists(\"bootstrap/cache/config.php\")) @unlink(\"bootstrap/cache/config.php\");" || true\n\
php -r "if (file_exists(\"bootstrap/cache/routes.php\")) @unlink(\"bootstrap/cache/routes.php\");" || true\n\
php -r "if (is_dir(\"storage/framework/views\")) { \$files = glob(\"storage/framework/views/*.php\"); if (\$files) { array_map(\"unlink\", \$files); }}" || true\n\
\n\
# Create health check file for Railway\n\
echo "<?php echo \"OK\"; ?>" > /var/www/html/public/health.php\n\
\n\
# Handle Railway environment\n\
if [ -n "$RAILWAY_ENVIRONMENT" ]; then\n\
  echo "Running in Railway environment..."\n\
  # Set database connection using Railway environment variables\n\
  sed -i "s/DB_HOST=.*/DB_HOST=${DB_HOST:-db}/" /var/www/html/.env || true\n\
  sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE:-faveo}/" /var/www/html/.env || true\n\
  sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME:-faveo}/" /var/www/html/.env || true\n\
  sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD:-faveo_password}/" /var/www/html/.env || true\n\
  # Set trusted proxies for Railway\n\
  sed -i "s/APP_URL=.*/APP_URL=${APP_URL:-http:\/\/localhost}/" /var/www/html/.env || true\n\
  # Use PORT from Railway if available\n\
  if [ -n "$PORT" ]; then\n\
    echo "Setting up Apache for port $PORT..."\n\
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf || true\n\
    sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf || true\n\
  fi\n\
else\n\
  # Local development environment settings\n\
  sed -i "s/DB_HOST=.*/DB_HOST=db/" /var/www/html/.env || true\n\
  sed -i "s/DB_DATABASE=.*/DB_DATABASE=faveo/" /var/www/html/.env || true\n\
  sed -i "s/DB_USERNAME=.*/DB_USERNAME=faveo/" /var/www/html/.env || true\n\
  sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=faveo_password/" /var/www/html/.env || true\n\
fi\n\
\n\
echo "Starting Apache..."\n\
apache2-foreground\n\
' > /usr/local/bin/bootstrap.sh && \
    chmod +x /usr/local/bin/bootstrap.sh

# Install Node dependencies
RUN npm install && npm run prod || true

# Set final permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Use bootstrap script as entry point
CMD ["/usr/local/bin/bootstrap.sh"] 