FROM php:8.2-cli

COPY . /var/www/html

RUN apt-get update && apt-get upgrade -y
RUN apt update \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && mkdir /var/www/html/logs && chown www-data:www-data /var/www/html/logs

WORKDIR /var/www/html/

CMD [ "php", "/var/www/html/app.php" ]