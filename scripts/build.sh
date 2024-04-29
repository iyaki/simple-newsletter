#!/usr/bin/env sh

SCRIPTPATH=$(dirname "$(realpath "$0")")

cd "${SCRIPTPATH}/../" || exit

docker compose -f "${SCRIPTPATH}/../compose-build.yaml" build --push build
