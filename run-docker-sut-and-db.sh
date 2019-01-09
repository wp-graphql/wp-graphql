#!/usr/bin/env bash
set -eu

run_sut_and_db() {
  docker-compose -f docker/docker-compose.sut-and-db.yml up --build
}

cleanup_docker_artifacts() {
  docker-compose -f docker/docker-compose.sut-and-db.yml down -v --rmi local 2> /dev/null
}

main() {
  trap cleanup_docker_artifacts EXIT
  run_sut_and_db
}

main
