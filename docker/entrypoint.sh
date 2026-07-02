#!/usr/bin/env bash
set -e

# The platform (Railway/Render) provides the port to listen on.
: "${PORT:=80}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Cache config (env vars are available now) and run migrations on boot.
php artisan config:cache
php artisan migrate --force

exec apache2-foreground
