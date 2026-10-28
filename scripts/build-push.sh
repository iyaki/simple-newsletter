#!/usr/bin/env sh

SCRIPTPATH=$(dirname "$(realpath "$0")")

IMAGE=ghcr.io/iyaki/simple-newsletter

podman build --tag $IMAGE:latest \
	--cache-from $IMAGE \
	--cache-to $IMAGE \
	--target production ${SCRIPTPATH}/.. &&
podman push $IMAGE:latest
