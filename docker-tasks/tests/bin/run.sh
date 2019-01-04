#!/usr/bin/env bash
set -eu

readonly TEST_RESULTS_DIR="${1:?Absolute path to Test report dir needs to be specified.}"

cd_to_task_dir() {
  cd "$( dirname "${BASH_SOURCE[0]}" )/.." >/dev/null
}

recreate_report_dir() {
  rm -rf "${TEST_RESULTS_DIR}"
  mkdir -p "${TEST_RESULTS_DIR}"
}

run_tests() {
  ../common/bin/docker-compose-up-wrapper.sh docker-compose-files/docker-compose.yml 'wpgraphql-tester' 'wpgraphql-tester:/tmp/wordpress/wp-content/plugins/wp-graphql/tests/_output/' "${TEST_RESULTS_DIR}"
}

main() {
  recreate_report_dir
  cd_to_task_dir
  run_tests
}

main
