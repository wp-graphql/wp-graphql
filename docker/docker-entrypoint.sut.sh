#!/usr/bin/env bash

set -eu

# The Official WordPress Docker file declares /var/www/html as a volume, so need to
# populate it at container run-time and not image build-time.
copy_wordpress_files() {
  cp -a /usr/src/wordpress/* /var/www/html/
}

wait_for_database() {
  set +e
  while [[ true ]]; do
    if curl --fail --show-error --silent "${WORDPRESS_DB_HOST}:3306" > /dev/null 2>&1; then break; fi
      echo "Waiting for database to be ready...."
      sleep 2
  done
  set -e
}

configure_wordpress() {
  wp --allow-root config create --dbname="${WORDPRESS_DB_NAME}" --dbuser="${WORDPRESS_DB_USER}" --dbpass="${WORDPRESS_DB_PASSWORD}" --dbhost="${WORDPRESS_DB_HOST}" --skip-check --force=true
  wp --allow-root core install --url='http://wpgraphql.test' --title='WPGraphQL Tests' --admin_user='admin' --admin_password='password' --admin_email='admin@wpgraphql.test' --skip-email
  wp --allow-root rewrite structure '/%year%/%monthnum%/%postname%/'

  # activate the plugin
  wp --allow-root plugin activate wp-graphql

  # Flush the permalinks
  wp --allow-root rewrite flush --hard

  # Export sql data for Codeception's use
  # TODO: Enable this?
  # wp --allow-root db export "$(pwd)/tests/_data/dump.sql"

  chown 'www-data:www-data' wp-config.php .htaccess
}

run_wordpress() {
  docker-entrypoint.sh 'apache2-foreground'
}

main() {
  copy_wordpress_files
  wait_for_database
  configure_wordpress
  run_wordpress
}

main
