#!/usr/bin/env bash
# ponytail: thin wrapper — the devcontainer runner is the CI-proven path with
# all env/DB/server setup; keeping a second divergent copy is how this broke.
set -e
exec "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/run-e2e-in-devcontainer.sh" "$@"
