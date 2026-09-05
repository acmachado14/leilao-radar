#!/usr/bin/env bash
set -euo pipefail

STACK_DIR="/home/ubuntu/leilao-radar"
cd "${STACK_DIR}"

if [ ! -f .env ]; then
  echo "Missing ${STACK_DIR}/.env — copy .env.production.example and fill secrets first."
  exit 1
fi

if [ "${SKIP_PULL:-false}" = "true" ]; then
  echo "Using images already present on this host (SKIP_PULL=true)."
  docker compose up -d --wait
else
  docker compose pull
  docker compose up -d --wait
fi

docker compose exec -T app php artisan migrate --force
docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
docker compose exec -T app php artisan storage:link --force || true
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan radar:sync-lots || true

docker compose restart queue scheduler

docker image prune -f

echo "VerifyRadar stack is up."
