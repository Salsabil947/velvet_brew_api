FROM php:8.2


RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite

COPY . /app
WORKDIR /app

CMD php -S 0.0.0.0:$PORT
