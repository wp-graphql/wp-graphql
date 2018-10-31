#!/usr/bin/env bash

set -e

edit_wp_test_suite_db_config() {
  local -r wp_test_core_dir_no_trailing_slash="$(echo ${WP_TEST_CORE_DIR} | sed 's:/\+$::')"

  sed -i "s:dirname( __FILE__ ) . '/src/':'${wp_test_core_dir_no_trailing_slash}/':" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/youremptytestdbnamehere/$DB_SERVE_NAME/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/yourusernamehere/$DB_USER/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s/yourpasswordhere/$DB_PASSWORD/" "${WP_TESTS_DIR}/wp-tests-config.php"
  sed -i "s|localhost|${DB_HOST}|" "${WP_TESTS_DIR}/wp-tests-config.php"
}

# MySQL may not be ready when container starts; wait at most 30 seconds.
wait_for_database_connection() {
  set +ex

  local db_tries=1
  while [[ ${db_tries} -lt 15 ]]; do
    if curl --fail --show-error --silent "${DB_HOST}:3306" > /dev/null 2>&1; then break; fi
      echo 'Waiting for database to be ready....'
      sleep 2
      db_tries=$((db_tries + 1))
  done

  set -ex
}

configure_wordpress() {
  wp --allow-root --path="${WP_TEST_CORE_DIR}" config create --dbname="${DB_SERVE_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASSWORD}" --dbhost="${DB_HOST}" --skip-check --force=true
  wp --allow-root --path="${WP_TEST_CORE_DIR}" core install --url=wpgraphql.test --title='WPGraphQL Tests' --admin_user=admin --admin_password=password --admin_email=admin@wpgraphql.test --skip-email
  wp --allow-root --path="${WP_TEST_CORE_DIR}" rewrite structure '/%year%/%monthnum%/%postname%/'
}

activate_plugin() {
  # activate the plugin
  wp --allow-root --path="${WP_TEST_CORE_DIR}" plugin activate wp-graphql

  # Flush the permalinks
  wp --allow-root --path="${WP_TEST_CORE_DIR}" rewrite flush

  # Export sql data for Codeception's use
  wp --allow-root --path="${WP_TEST_CORE_DIR}" db export "$(pwd)/tests/_data/dump.sql"
}

main() {
  edit_wp_test_suite_db_config
  wait_for_database_connection
  configure_wordpress
  activate_plugin
}

main
