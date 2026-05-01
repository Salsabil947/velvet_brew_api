FROM php:8.2-apache

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable rewrite safely
RUN apt-get update && apt-get install -y apache2-utils \
    && a2enmod rewrite

# Copy files
COPY . /var/www/html/

# Allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
