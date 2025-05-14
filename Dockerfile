FROM php:8.2-apache

# Set Composer environment variables to prevent HOME not set errors
ENV COMPOSER_HOME=/tmp/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

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

# Copy bootstrap script
COPY bootstrap.sh /usr/local/bin/bootstrap.sh
RUN chmod +x /usr/local/bin/bootstrap.sh

# Install Node dependencies
RUN npm install && npm run prod || true

# Set final permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Use bootstrap script as entry point
CMD ["/usr/local/bin/bootstrap.sh"] 