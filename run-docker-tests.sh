#!/usr/bin/env bash
set -e

if [[ -z "${1}" ]]; then
  echo 'Test type required. Can be "acceptance", "functional", or "wpunit"' >&2
  exit 1
fi

readonly TEST_TYPE="${1}"
readonly TEST_RESULTS_DIR="./docker-output/${TEST_TYPE}"

source_docker_env() {
  source docker-tasks/common/env-files/env.sh
}

initialize_test_results_dir() {
  rm -rf "${TEST_RESULTS_DIR}"
  mkdir -p "${TEST_RESULTS_DIR}"
}

get_wordpress_config_extra() {
  if [[ "${WP_MULTISITE}" == '1' ]]; then
    echo "define('WP_ALLOW_MULTISITE', true );"
    echo "define('MULTISITE', true);"
  else
    echo ''
  fi
}

run_tests() {
  echo "Going to run with WP version: ${WP_VERSION} and PHP version: ${PHP_VERSION}"

  env DOCKER_TASK='run-tests' \
    CONTAINER_DATA_PATH=/project/tests/_output/ \
    HOST_DATA_PATH="${TEST_RESULTS_DIR}" \
    TEST_TYPE="${TEST_TYPE}" \
    WORDPRESS_CONFIG_EXTRA="$(get_wordpress_config_extra)" \
    docker-tasks/run-docker-compose-up.sh --build --exit-code-from 'main'
}

main() {
  source_docker_env
  initialize_test_results_dir
  run_tests
}

main
