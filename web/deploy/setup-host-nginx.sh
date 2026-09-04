#!/usr/bin/env bash
set -euo pipefail

CONF_DIR="$(cd "$(dirname "$0")" && pwd)/nginx"
HTTP_SRC="${CONF_DIR}/radar.angelocupertino.com.br.http.conf"
SSL_SRC="${CONF_DIR}/radar.angelocupertino.com.br.conf"
DST="/etc/nginx/sites-available/radar.angelocupertino.com.br"
DOMAIN="radar.angelocupertino.com.br"
EMAIL="${CERTBOT_EMAIL:-contato.verifycar@gmail.com}"

sudo cp "${HTTP_SRC}" "${DST}"
sudo ln -sf "${DST}" /etc/nginx/sites-enabled/radar.angelocupertino.com.br
sudo nginx -t
sudo systemctl reload nginx

if [ ! -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]; then
  echo "Issuing TLS certificate for ${DOMAIN}..."
  sudo certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect || \
  sudo certbot certonly --webroot -w /var/www/html -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}"
else
  echo "Certificate already exists for ${DOMAIN}."
fi

if [ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]; then
  sudo cp "${SSL_SRC}" "${DST}"
  sudo nginx -t
  sudo systemctl reload nginx
fi

echo "Host nginx configured for ${DOMAIN}"
