#!/usr/bin/env bash
set -e

# Railway/Render inject PORT. Default for local docker runs.
: "${PORT:=8080}"

echo "[entrypoint] Waiting for MySQL..."
db_ready=0
for i in $(seq 1 40); do
  if php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = getenv("DB_PORT") ?: "3306";
    $db   = getenv("DB_DATABASE") ?: "";
    $user = getenv("DB_USERNAME") ?: "";
    $pass = getenv("DB_PASSWORD") ?: "";
    try {
      new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_TIMEOUT => 3,
      ]);
      exit(0);
    } catch (Throwable $e) {
      exit(1);
    }
  '; then
    echo "[entrypoint] Database is ready."
    db_ready=1
    break
  fi
  echo "[entrypoint] Database not ready ($i/40)..."
  sleep 2
done

if [ "$db_ready" -ne 1 ]; then
  echo "[entrypoint] ERROR: MySQL unreachable. Check Railway MySQL service is running and DB_* variables."
  exit 1
fi

# Cache config (env vars are available now).
php artisan config:cache

# Migrate with retries — a single blip should not leave the container dead forever.
migrate_ok=0
for i in $(seq 1 5); do
  if php artisan migrate --force; then
    migrate_ok=1
    break
  fi
  echo "[entrypoint] migrate failed (attempt $i/5), retrying..."
  sleep 3
done
if [ "$migrate_ok" -ne 1 ]; then
  echo "[entrypoint] ERROR: migrations failed after retries."
  exit 1
fi

# Prefer Apache over `php artisan serve` — the built-in server is single-threaded
# and often hangs/crashes under concurrent teacher UI + recognition polling.
echo "[entrypoint] Binding Apache to port ${PORT}"
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:80>#<VirtualHost *:${PORT}>#" /etc/apache2/sites-available/000-default.conf

# Keep memory use low on Railway hobby plans.
cat >/etc/apache2/conf-available/railway-limits.conf <<'EOF'
# Cap prefork workers so the container does not OOM on small plans.
ServerLimit 4
StartServers 1
MinSpareServers 1
MaxSpareServers 2
MaxRequestWorkers 16
MaxConnectionsPerChild 200
EOF
a2enconf railway-limits >/dev/null

exec apache2-foreground
