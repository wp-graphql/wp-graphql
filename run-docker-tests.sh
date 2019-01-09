#!/usr/bin/env bash
set -eu

readonly TEST_TYPE="${1:?Test type must be wpunit, functional, or acceptance}"
readonly TEST_RESULTS_DIR="docker-output/${TEST_TYPE}"

recreate_report_dir() {
  rm -rf "${TEST_RESULTS_DIR}"
  mkdir -p "${TEST_RESULTS_DIR}"
}

run_tests() {
  # Run the Docker compose file and make note of the exit code. This will be used by the CI tool (e.g. Travis) to
  # determine if the build should fail.
  env TEST_TYPE="${TEST_TYPE}" docker-compose -f docker/docker-compose.tests.yml up --build --abort-on-container-exit --exit-code-from 'wpgraphql-tester'
}

copy_data_from_docker_container_to_host() {
  docker cp 'wpgraphql-tester:/tmp/wordpress/wp-content/plugins/wp-graphql/tests/_output/' "${TEST_RESULTS_DIR}"
}

cleanup_docker_artifacts() {
  docker-compose -f docker/docker-compose.tests.yml down -v --rmi local 2> /dev/null
}

main() {
  recreate_report_dir

  trap cleanup_docker_artifacts EXIT

  set +e
  run_tests
  local -r exit_code=$?
  set -e

  copy_data_from_docker_container_to_host

  exit $((exit_code))
}

main
