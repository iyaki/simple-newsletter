#!/usr/bin/env sh

# Build y push de la imagen de producción a GHCR. Sólo desde un tree limpio
# (el camino normal es el workflow docker-publish tras CI verde en main;
# esto es el camino manual sin cuota de Actions).
#
# Uso: scripts/build-push.sh [tag]
#   Publica :latest y, si se pasa un tag, también ese (ej: v0.1.0).
# Requiere devenv shell (composer/php/nix) y podman login ghcr.io con
# permisos write:packages.

set -e

SCRIPTPATH=$(dirname "$(realpath "$0")")
IMAGE=ghcr.io/iyaki/simple-newsletter

# :latest es el canal de producción: sin work en vuelo que no esté en el commit
if [ -n "$(git -C "${SCRIPTPATH}/.." status --porcelain)" ]; then
    echo "tree sucio: :latest es el canal de producción — commiteá y dejá verde CI primero" >&2
    exit 1
fi

# Imagen OCI derivada del pin de nixpkgs de devenv (única fuente de verdad);
# el staging lo arma build-image.sh (composer --no-dev), que imprime SÓLO la
# ruta del tarball en stdout (el exit code viaja entero).
TARBALL=$("${SCRIPTPATH}/build-image.sh")
podman load <"$TARBALL"
podman tag simple-newsletter:latest "${IMAGE}:latest"
podman push "${IMAGE}:latest"

# Digest publicado: el deploy puede pinnear por él
podman inspect --format '{{index .RepoDigests 0}}' "${IMAGE}:latest"

if [ -n "$1" ]; then
    podman tag "${IMAGE}:latest" "${IMAGE}:$1"
    podman push "${IMAGE}:$1"
fi
