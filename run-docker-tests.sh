#!/usr/bin/env bash
set -e

if [[ -z "${1}" ]]; then
  echo 'Test type required. Can be "acceptance", "functional", or "wpunit"' >&2
  exit 1
fi


readonly TEST_TYPE="${1}"
run_tests() {
  env DOCKER_TASK='run-tests' TEST_TYPE="${TEST_TYPE}" docker-tasks/run-docker-compose-up.sh --build --exit-code-from 'main'
}

main() {
  run_tests
}

main
