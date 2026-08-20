FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y \
    python3 \
    python3-pip \
    supervisor \
    && docker-php-ext-install pdo pdo_mysql \
    && pip3 install --break-system-packages \
    PyMySQL \
    pyserial \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY public/ /var/www/html/

COPY python/ /opt/oficina/python/

COPY supervisord.conf /etc/supervisor/conf.d/oficina.conf

RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-n"]