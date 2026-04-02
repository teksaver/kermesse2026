FROM php:8.4-apache

# Enable Apache modules required for Symfony
RUN a2enmod rewrite

# Install required system packages and PHP extensions
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) intl pdo pdo_mysql zip

# Update DocumentRoot to point to /var/www/html/httpdocs instead of /var/www/html
RUN sed -i 's!/var/www/html!/var/www/html/httpdocs!g' /etc/apache2/sites-available/000-default.conf

# Give proper permissions
RUN chown -R www-data:www-data /var/www/html
