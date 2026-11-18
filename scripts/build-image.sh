#!/usr/bin/env sh

# Arma el staging de la imagen de producción (app + vendor --no-dev, el mismo
# set que COPYeaba el viejo Dockerfile) y construye la imagen OCI con Nix
# (infrastructure/image.nix, mismo pin de nixpkgs que devenv).
# Sin push: el canal de producción lo publica scripts/build-push.sh.
# Requiere devenv shell (composer, php, nix).

set -eu

SCRIPTPATH=$(dirname "$(realpath "$0")")
ROOT=$(realpath "${SCRIPTPATH}/..")
STAGING="$ROOT/.image-staging"

command -v composer >/dev/null 2>&1 || {
    echo "falta composer: corrégilo desde devenv shell" >&2
    exit 1
}

# Staging fresco en cada build: sólo lo que viaja en la imagen (sin
# tests/docs/scripts/secretos).
rm -rf "$STAGING"
mkdir -p "$STAGING"
cd "$ROOT"
tar -cf - composer.json composer.lock bin config libs public |
    tar -xf - -C "$STAGING"

cd "$STAGING"

COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --classmap-authoritative >&2

# stdout queda limpio: build-push.sh y docker-publish capturan la salida como
# ruta del tarball y con ella el exit code del build (un pipe con tail lo
# enmascararía).
cd "$ROOT"
nix build -f infrastructure/image.nix --print-out-paths
