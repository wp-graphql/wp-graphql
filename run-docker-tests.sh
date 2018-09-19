#!/usr/bin/env bash
set -e

if [[ -z "${1}" ]]; then
  echo 'Test type required. Can be "acceptance", "functional", or "wpunit"' >&2
  exit 1
fi

readonly TEST_TYPE="${1}"
readonly TEST_RESULTS_DIR=./docker-test-results

initialize_test_results_dir() {
  rm -rf "${TEST_RESULTS_DIR}"
  mkdir "${TEST_RESULTS_DIR}"
}

run_tests() {
  env DOCKER_TASK='run-tests' CONTAINER_DATA_PATH=/project/tests/_output HOST_DATA_PATH="${TEST_RESULTS_DIR}" TEST_TYPE="${TEST_TYPE}" docker-tasks/run-docker-compose-up.sh --build --exit-code-from 'main'
}

main() {
  run_tests
}

main
