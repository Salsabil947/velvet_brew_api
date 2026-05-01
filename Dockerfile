FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# 🔥 FIX: disable other MPMs and enable only prefork
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

RUN a2enmod rewrite

COPY . /var/www/html/

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
