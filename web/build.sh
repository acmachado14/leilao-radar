#!/usr/bin/env bash
set -euo pipefail

PLATFORM="linux/arm64"
APP_IMAGE="acmachado14/leilao-radar:main"
NGINX_IMAGE="acmachado14/leilao-radar-nginx:main"
PUSH="${PUSH:-true}"

if [ "${PUSH}" = "true" ]; then
  OUTPUT_ARGS=(--push)
else
  OUTPUT_ARGS=(--load)
fi

echo "Building app image (${PLATFORM})..."
docker buildx build \
  --platform "${PLATFORM}" \
  -f Dockerfile \
  -t "${APP_IMAGE}" \
  "${OUTPUT_ARGS[@]}" \
  .

echo "Building nginx image (${PLATFORM})..."
docker buildx build \
  --platform "${PLATFORM}" \
  --build-arg APP_IMAGE="${APP_IMAGE}" \
  -f Dockerfile.nginx \
  -t "${NGINX_IMAGE}" \
  "${OUTPUT_ARGS[@]}" \
  .

if [ "${PUSH}" = "true" ]; then
  echo "Pushed ${APP_IMAGE} and ${NGINX_IMAGE}"
else
  echo "Loaded ${APP_IMAGE} and ${NGINX_IMAGE} locally"
fi
