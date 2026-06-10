#!/usr/bin/env sh

SCRIPTPATH=$(dirname "$(realpath "$0")")

docker build --tag ghcr.io/iyaki/simple-newsletter:latest --target production ${SCRIPTPATH}/.. &&
docker push ghcr.io/iyaki/simple-newsletter:latest
