FROM php:8.2-cli-alpine

# Instalar dependencias para SQLite y extensiones necesarias
RUN apk add --no-cache sqlite-libs sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www

# Crear carpetas de almacenamiento y base de datos
RUN mkdir -p /var/www/uploads/products /var/www/database \
    && chmod -R 777 /var/www/uploads /var/www/database

# Copiar archivos del backend
COPY . /var/www

EXPOSE 8000

ENV UPLOAD_DIR=/var/www/uploads/products
ENV DB_PATH=/var/www/database/akami.sqlite

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
