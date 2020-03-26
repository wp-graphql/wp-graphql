#!/usr/bin/env bash
set -eu

run_test_environment() {
  docker-compose -f docker/docker-compose.tests.yml -f docker/docker-compose.test-environment.yml up --build
}

cleanup_docker_artifacts() {
  docker-compose -f docker/docker-compose.tests.yml -f docker/docker-compose.test-environment.yml down -v --rmi local 2> /dev/null
}

main() {
  trap cleanup_docker_artifacts EXIT
  run_test_environment
}

main
