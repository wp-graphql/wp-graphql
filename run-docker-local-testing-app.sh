#!/usr/bin/env bash
set -e

source_docker_env() {
  source docker-tasks/common/env-files/env.sh
}

run_app() {
  env DOCKER_TASK='run-local-testing-app' docker-tasks/run-docker-compose-up.sh --build
}

main() {
  source_docker_env
  run_app
}

main
