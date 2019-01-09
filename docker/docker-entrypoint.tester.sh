#!/usr/bin/env bash

set -eu

edit_wp_test_suite_db_config() {
  local -r wp_test_core_dir_no_trailing_slash="$(echo ${WP_TEST_CORE_DIR} | sed 's:/\+$::')"

  sed -i "s:dirname( __FILE__ ) . '/src/':'${wp_test_core_dir_no_trailing_slash}/':" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/youremptytestdbnamehere/$DB_SERVE_NAME/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/yourusernamehere/$DB_USER/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/yourpasswordhere/$DB_PASSWORD/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s|localhost|${DB_HOST}|" "${WP_TESTS_DIR}/wp-tests-config.php"
}

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
  edit_wp_test_suite_db_config
  wait_for_database
  run_tests
}

main
