#!/usr/bin/env bash
set -e

install_wp_test_framework() {
  bin/install-wp-tests.sh 'ignored' "${DB_USER}" "${DB_PASSWORD}" "${DB_HOST}" "${WP_VERSION}" 'true'
}

run_codeception() {
  ./vendor/bin/codecept "$@"
}

main() {
  install_wp_test_framework
  run_codeception "$@"
}

main "$@"
