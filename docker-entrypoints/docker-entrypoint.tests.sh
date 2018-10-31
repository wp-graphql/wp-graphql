#!/usr/bin/env bash

set -e

run_tests() {
  if [[ "${COVERAGE}" == 'true' ]]; then
    phpdbg -qrr ./vendor/bin/codecept run "${TEST_TYPE}" --env docker --coverage --coverage-xml
  else
    ./vendor/bin/codecept run "${TEST_TYPE}" --env docker
  fi
}

main() {
  initialize-wp-test-environment.sh
  run_tests
}

main
