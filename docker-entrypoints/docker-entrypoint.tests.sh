#!/usr/bin/env bash
set -e

install_wp_test_framework() {
  env WP_CLI_ARGS='--allow-root' bin/install-wp-tests.sh 'ignored' "${DB_USER}" "${DB_PASSWORD}" "${DB_HOST}" "${WP_VERSION}" 'true'
}

run_codeception() {
  phpdbg -qrr ./vendor/bin/codecept "$@"
}

main() {
  cd /project
  install_wp_test_framework
  run_codeception "$@"
}

main "$@"
