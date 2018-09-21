#!/usr/bin/env bash
set -e

install_wp_test_framework() {
  env WP_CLI_ARGS='--allow-root' bin/install-wp-tests.sh 'ignored' "${DB_USER}" "${DB_PASSWORD}" "${DB_HOST}" "${WP_VERSION}" 'true'
}

run_codeception() {
  local coverage_params=''

  if [[ "${COVERAGE}" == 'true' ]]; then
    coverage_params='--coverage --coverage-xml'
  fi

  phpdbg -qrr ./vendor/bin/codecept run "${TEST_TYPE}" --env docker ${coverage_params}
}

main() {
  cd /project
  install_wp_test_framework
  run_codeception "$@"
}

main "$@"
