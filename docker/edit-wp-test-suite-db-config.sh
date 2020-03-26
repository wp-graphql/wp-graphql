#!/usr/bin/env bash

set -eu

main() {
  local -r wp_test_core_dir_no_trailing_slash="$(echo ${WP_TEST_CORE_DIR} | sed 's:/\+$::')"

  sed -i "s:dirname( __FILE__ ) . '/src/':'${wp_test_core_dir_no_trailing_slash}/':" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/youremptytestdbnamehere/$WORDPRESS_DB_NAME/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/yourusernamehere/$WORDPRESS_DB_USER/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/yourpasswordhere/$WORDPRESS_DB_PASSWORD/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s|localhost|${WORDPRESS_DB_HOST}|" "${WP_TESTS_DIR}/wp-tests-config.php"
}

main
