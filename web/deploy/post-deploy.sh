#!/usr/bin/env bash
set -euo pipefail

STACK_DIR="/home/ubuntu/leilao-radar"
cd "${STACK_DIR}"

CONF_DIR="$(cd "$(dirname "$0")" && pwd)/nginx"
DOMAIN="radar.verifycar.com.br"
OLD_DOMAIN="radar.angelocupertino.com.br"
HTTP_SRC="${CONF_DIR}/${DOMAIN}.http.conf"
SSL_SRC="${CONF_DIR}/${DOMAIN}.conf"
DST="/etc/nginx/sites-available/${DOMAIN}"
CERT="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
EMAIL="${CERTBOT_EMAIL:-contato.verifycar@gmail.com}"

if command -v sudo >/dev/null 2>&1; then
  # /etc/letsencrypt/live is root-only; test the cert with sudo or every deploy
  # silently falls back to the HTTP vhost and HTTPS starts serving another site's expired cert.
  if ! sudo test -f "${CERT}" && [ -f "${HTTP_SRC}" ]; then
    sudo cp "${HTTP_SRC}" "${DST}"
    sudo ln -sf "${DST}" "/etc/nginx/sites-enabled/${DOMAIN}"
    sudo rm -f "/etc/nginx/sites-enabled/${OLD_DOMAIN}"
    sudo nginx -t
    sudo systemctl reload nginx
    echo "Issuing TLS certificate for ${DOMAIN}..."
    sudo certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect || \
      sudo certbot certonly --webroot -w /var/www/html -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" || true
  fi

  if sudo test -f "${CERT}" && [ -f "${SSL_SRC}" ]; then
    sudo cp "${SSL_SRC}" "${DST}"
  elif [ -f "${HTTP_SRC}" ]; then
    sudo cp "${HTTP_SRC}" "${DST}"
  fi

  sudo ln -sf "${DST}" "/etc/nginx/sites-enabled/${DOMAIN}"
  sudo rm -f "/etc/nginx/sites-enabled/${OLD_DOMAIN}"
  sudo nginx -t
  sudo systemctl reload nginx
  echo "Host nginx reloaded for ${DOMAIN}."
fi

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose restart queue scheduler

echo "Post-deploy tasks completed."
