FROM php:8.2-apache

# Install SQLite extension (já vem com PHP, só precisa habilitar)
RUN apt-get update && apt-get install -y libsqlite3-dev && \
    docker-php-ext-install pdo pdo_sqlite && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Create data directory for SQLite and set permissions
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html
