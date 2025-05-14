FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    mariadb-client \
    sudo \
    procps \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY faveo /var/www/html/

# Copy health check file
COPY health.php /var/www/html/public/health.php

# Copy bootstrap file
COPY bootstrap-complete.sh /usr/local/bin/bootstrap.sh
RUN chmod +x /usr/local/bin/bootstrap.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Apache config
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Set entrypoint to bootstrap.sh
ENTRYPOINT ["/usr/local/bin/bootstrap.sh"]

# Expose port 80
EXPOSE 80 