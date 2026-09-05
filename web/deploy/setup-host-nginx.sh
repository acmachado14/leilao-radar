#!/usr/bin/env bash
set -euo pipefail

CONF_DIR="$(cd "$(dirname "$0")" && pwd)/nginx"
DOMAIN="radar.verifycar.com.br"
OLD_DOMAIN="radar.angelocupertino.com.br"
HTTP_SRC="${CONF_DIR}/${DOMAIN}.http.conf"
SSL_SRC="${CONF_DIR}/${DOMAIN}.conf"
DST="/etc/nginx/sites-available/${DOMAIN}"
EMAIL="${CERTBOT_EMAIL:-contato.verifycar@gmail.com}"

sudo cp "${HTTP_SRC}" "${DST}"
sudo ln -sf "${DST}" "/etc/nginx/sites-enabled/${DOMAIN}"
sudo rm -f "/etc/nginx/sites-enabled/${OLD_DOMAIN}"
sudo nginx -t
sudo systemctl reload nginx

if ! sudo test -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"; then
  echo "Issuing TLS certificate for ${DOMAIN}..."
  sudo certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect || \
  sudo certbot certonly --webroot -w /var/www/html -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}"
else
  echo "Certificate already exists for ${DOMAIN}."
fi

if sudo test -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"; then
  sudo cp "${SSL_SRC}" "${DST}"
  sudo nginx -t
  sudo systemctl reload nginx
fi

echo "Host nginx configured for ${DOMAIN}"
