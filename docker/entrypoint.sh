#!/usr/bin/env bash
set -e

# The platform (Railway/Render) provides the port to listen on.
: "${PORT:=8080}"

# Cache config (env vars are available now) and run migrations on boot.
php artisan config:cache
php artisan migrate --force

# Serve the app. PHP's built-in server (via artisan) avoids Apache/MPM issues
# and reliably binds the platform port; fine for a capstone-scale deployment.
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
