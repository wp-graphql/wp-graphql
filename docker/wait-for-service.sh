#!/usr/bin/env bash

set -eu

readonly URL="${1:?Service Url must be specified.}"
readonly SERVICE_NAME="${2:?Service name be specified.}"

main() {
  while [[ true ]]; do
    if curl --fail --show-error --silent "${URL}" > /dev/null 2>&1; then break; fi
      echo "Waiting for ${SERVICE_NAME} to be ready...."
      sleep 2
  done
}

main
