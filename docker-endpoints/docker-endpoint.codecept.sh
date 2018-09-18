#!/usr/bin/env bash
set -e

run_codeception() {
  ./vendor/bin/codecept "$@"
}

main() {
  env WP_CLI_ARGS='--allow-root' bin/install-wp-tests.sh 'ignored' "${DB_USER}" "${DB_PASSWORD}" "${DB_HOST}" "${WP_VERSION}" 'true'
  run_codeception "$@"
}

main "$@"
