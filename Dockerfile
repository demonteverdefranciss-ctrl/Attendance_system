# Laravel 12 + Inertia React — production image (works on Railway, Render, Fly, etc.)
FROM php:8.2-apache

# --- System deps + PHP extensions ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip ca-certificates gnupg libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath \
    && rm -rf /var/lib/apt/lists/*

# mod_php requires exactly one MPM (prefork). Forcefully remove the event/worker
# module links (a2dismod is unreliable here) to avoid
# "AH00534: apache2: Configuration error: More than one MPM loaded."
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite

# --- Node.js 22 LTS (build front-end assets) ---
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Serve Laravel's public/ as the Apache document root ---
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy the app and install dependencies + build assets.
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && ASSET_URL= npm run build \
    && npm prune --omit=dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
