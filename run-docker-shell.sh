#!/usr/bin/env bash
set -e

if [[ -z "${1}" ]]; then
  echo 'Docker container id or container name needs to be specified!' >&2
  exit 1
fi

readonly CONTAINER_ID="${1}"

main() {
  docker exec -it "${CONTAINER_ID}" /bin/bash
}

main
