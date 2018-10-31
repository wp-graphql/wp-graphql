#!/usr/bin/env bash
set -e

source_docker_env() {
  source docker-tasks/common/env-files/env.sh
}

run_app() {
  env DOCKER_TASK='run-test-environment' CONTAINER_USER_ID="$(id -u)" CONTAINER_GROUP_ID="$(id -g)" docker-tasks/run-docker-compose-up.sh --build
}

main() {
  source_docker_env
  run_app
}

main
