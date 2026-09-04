#!/usr/bin/env bash
set -euo pipefail

STACK_DIR="/home/ubuntu/leilao-radar"
cd "${STACK_DIR}"

CONF_DIR="$(cd "$(dirname "$0")" && pwd)/nginx"
HTTP_SRC="${CONF_DIR}/radar.angelocupertino.com.br.http.conf"
SSL_SRC="${CONF_DIR}/radar.angelocupertino.com.br.conf"
DST="/etc/nginx/sites-available/radar.angelocupertino.com.br"
CERT="/etc/letsencrypt/live/radar.angelocupertino.com.br/fullchain.pem"

if command -v sudo >/dev/null 2>&1; then
  if [ -f "${CERT}" ] && [ -f "${SSL_SRC}" ]; then
    sudo cp "${SSL_SRC}" "${DST}"
  elif [ -f "${HTTP_SRC}" ]; then
    sudo cp "${HTTP_SRC}" "${DST}"
  fi

  sudo ln -sf "${DST}" /etc/nginx/sites-enabled/radar.angelocupertino.com.br
  sudo nginx -t
  sudo systemctl reload nginx
  echo "Host nginx reloaded."
fi

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose restart queue scheduler

echo "Post-deploy tasks completed."
