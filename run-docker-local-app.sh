#!/usr/bin/env bash
set -e

run_app() {
  env DOCKER_TASK='run-local-app' docker-tasks/bin/run-docker-compose-up.sh --build
}

main() {
  run_app
}

main
