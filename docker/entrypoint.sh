#!/usr/bin/env bash
set -e

# Railway/Render inject PORT. Default for local docker runs.
: "${PORT:=8080}"

echo "[entrypoint] Waiting for MySQL (max ~60s)..."
db_ready=0
for i in $(seq 1 30); do
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
  echo "[entrypoint] Database not ready ($i/30)..."
  sleep 2
done

if [ "$db_ready" -ne 1 ]; then
  echo "[entrypoint] ERROR: MySQL unreachable. Check Railway MySQL service is running and DB_* variables."
  exit 1
fi

php artisan config:cache

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

# Keep schedule-based auto open/close running (attendance:manage-sessions every minute).
echo "[entrypoint] Starting Laravel scheduler (schedule:work)..."
php artisan schedule:work >> /tmp/laravel-scheduler.log 2>&1 &

echo "[entrypoint] Starting php artisan serve on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
