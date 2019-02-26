#!/usr/bin/env bash
set -eu

run_app() {
  docker-compose -f docker/docker-compose.local-app.yml up --build
}

cleanup_docker_artifacts() {
  docker-compose -f docker/docker-compose.local-app.yml down -v --rmi local 2> /dev/null
}

main() {
  trap cleanup_docker_artifacts EXIT
  run_app
}

main
