#!/usr/bin/env bash

set -eu

wait_for_wordpress_sut() {
  wait-for-service.sh 'wpgraphql.test:80/graphql' 'Wordpress system-under-test'
}

run_tests() {
  if [[ "${COVERAGE}" == 'true' ]]; then
    phpdbg -qrr ./vendor/bin/codecept run "${TEST_TYPE}" --env docker --coverage --coverage-xml
  else
    ./vendor/bin/codecept run "${TEST_TYPE}" --env docker
  fi
}

main() {
  edit-wp-test-suite-db-config.sh
  wait_for_wordpress_sut
  run_tests
}

main
