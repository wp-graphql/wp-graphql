#!/usr/bin/env bash
set -eu

main() {
  docker exec -it 'wpgraphql-tester-shell' /bin/bash
}

main
