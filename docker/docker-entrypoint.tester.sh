#!/usr/bin/env bash

set -eu

wait_for_database() {
  set +e
  while [[ true ]]; do
    if curl --fail --show-error --silent "wpgraphql.test:80" > /dev/null 2>&1; then break; fi
      echo "Waiting for Wordpress system-under-test to be ready...."
      sleep 2
  done
  set -e
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
  wait_for_database
  run_tests
}

main
