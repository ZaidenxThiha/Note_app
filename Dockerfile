FROM php:8.1-apache

# Install PDO MySQL extension, git, zip, and unzip
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set ServerName to suppress the warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy application code
COPY public/ /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install Ratchet and PHPMailer
RUN composer require cboden/ratchet phpmailer/phpmailer

# Expose ports (Apache on 80, WebSocket on 8080)
EXPOSE 80
EXPOSE 8080

# Start Apache (Composer installation will be handled in docker-compose.yml)
CMD ["apache2-foreground"]