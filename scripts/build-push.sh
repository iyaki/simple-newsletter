#!/usr/bin/env sh

SCRIPTPATH=$(dirname "$(realpath "$0")")

docker buildx build --tag ghcr.io/iyaki/simple-newsletter:latest --target production --push ${SCRIPTPATH}/..
