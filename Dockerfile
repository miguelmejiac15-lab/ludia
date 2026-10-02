# Ludia — imagen para el servidor.
#
# Se construye en GitHub Actions, NO en la EC2: la máquina es una t3.small con
# 2 GB de RAM compartidos con otro sitio en producción, y compilar las
# extensiones de PHP ahí dentro competiría por memoria con un sitio que está
# atendiendo visitas. Actions construye, publica la imagen y el servidor solo
# se la descarga.
#
# PHP 8.2 porque es lo que hay en el XAMPP de desarrollo (8.2.12): estrenar
# versión de PHP el día del despliegue es buscarse dos problemas a la vez.

FROM php:8.2-apache

# ---------------------------------------------------------------------------
# Extensiones
#
# `zip` y `gd` NO son opcionales: la plantilla de carga masiva lee .xlsx (que
# es un zip), la exportación a SCORM escribe uno, y gd es quien vuelve a
# dibujar cada imagen subida —que es lo que impide que un .php disfrazado de
# foto sobreviva al reguardado—. Si faltan, esas tres cosas fallan en el
# servidor y funcionan en local, que es el peor sitio donde esconder un fallo.
# ---------------------------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
    ; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql opcache; \
    apt-get purge -y --auto-remove; \
    rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Apache
#
# `AllowOverride All` es el ajuste más importante de este archivo.
#
# El proyecto protege cinco carpetas con .htaccess: config, docs, includes y
# sql van con «Require all denied», y uploads apaga el intérprete de PHP y solo
# deja servir imágenes. La imagen oficial viene con AllowOverride None, así que
# SIN esta línea esos cinco archivos se ignoran y las protecciones desaparecen
# sin que nada avise: lo que alguien suba a uploads pasaría a ser ejecutable.
# ---------------------------------------------------------------------------
RUN set -eux; \
    printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        '' \
        '# No anunciar la versión de Apache ni de PHP en las cabeceras.' \
        'ServerTokens Prod' \
        'ServerSignature Off' \
        > /etc/apache2/conf-available/ludia.conf; \
    a2enconf ludia; \
    a2enmod headers expires

COPY docker/php.ini /usr/local/etc/php/conf.d/ludia.ini

# ---------------------------------------------------------------------------
# El código
# ---------------------------------------------------------------------------
WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

# La configuración del servidor lee variables de entorno. Se pone ENCIMA de
# config/config.php porque es el archivo que `bootstrap.php` busca primero;
# así el resto del proyecto no se entera de que está en un contenedor.
RUN set -eux; \
    mv /var/www/html/config/config.env.php /var/www/html/config/config.php; \
    chown www-data:www-data /var/www/html/config/config.php; \
    chmod 640 /var/www/html/config/config.php; \
    mkdir -p /var/www/html/uploads; \
    chown -R www-data:www-data /var/www/html/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/") === false ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
