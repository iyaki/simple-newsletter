#!/usr/bin/env sh

CR_USER="${USER}"
if [ -n "${1}" ];
then
	CR_USER="${1}"
fi

printf "Use Github Personal Access Token as password\n"

docker login ghcr.io -u "${CR_USER}"
