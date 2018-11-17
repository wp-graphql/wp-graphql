#!/usr/bin/env bash
set -e

run_app() {
  env DOCKER_TASK='run-test-environment' docker-tasks/bin/run-docker-compose-up.sh --build
}

main() {
  run_app
}

main
