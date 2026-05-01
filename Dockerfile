FROM php:8.2-cli

# Install MySQL PDO
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable rewrite (safe)
RUN a2enmod rewrite || true

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Expose port (Railway uses 8080)
EXPOSE 8080

# Start PHP built-in server
CMD php -S 0.0.0.0:8080 -t .
RUN chown -R www-data:www-data /var/www/html
