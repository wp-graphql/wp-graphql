#!/usr/bin/env bash

set -eu

readonly CONTAINER="${1:?Container name or id must be specified.}"

main() {
  docker exec -it "${CONTAINER}" /bin/bash
}

main
