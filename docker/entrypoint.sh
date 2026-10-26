#!/bin/sh
set -eu
if [ "${APP_ENV:-local}" != "local" ]; then
  echo 'このコンテナはローカルデモ専用です。' >&2
  exit 1
fi
export APP_ENV=local
export DB_CONNECTION=sqlite
export DB_DATABASE="${DB_DATABASE:-/app/storage/app/invoiceflow.sqlite}"
mkdir -p /app/storage/app /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs
if [ -z "${APP_KEY:-}" ]; then
  if [ ! -s /app/storage/app/.demo-key ]; then
    umask 077
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > /app/storage/app/.demo-key
  fi
  export APP_KEY="$(cat /app/storage/app/.demo-key)"
fi
if [ ! -f "$DB_DATABASE" ]; then touch "$DB_DATABASE"; fi
php artisan migrate --force --no-interaction
if [ "${SEED_DEMO:-false}" = "true" ]; then php artisan db:seed --class=DemoSeeder --force --no-interaction; fi
exec php artisan serve --host=0.0.0.0 --port=8000
