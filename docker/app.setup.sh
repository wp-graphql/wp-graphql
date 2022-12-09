#!/bin/bash

if [ "$USING_XDEBUG" == "1"  ]; then
    echo "Enabling XDebug 3"
    mv /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/
fi

# Run WordPress docker entrypoint.
. docker-entrypoint.sh 'apache2'

set +u

# Ensure mysql is loaded
dockerize -wait tcp://${DB_HOST}:${DB_HOST_PORT:-3306} -timeout 1m

# Config WordPress
if [ ! -f "${WP_ROOT_FOLDER}/wp-config.php" ]; then
    wp config create \
        --path="${WP_ROOT_FOLDER}" \
        --dbname="${DB_NAME}" \
        --dbuser="${DB_USER}" \
        --dbpass="${DB_PASSWORD}" \
        --dbhost="${DB_HOST}" \
        --dbprefix="${WP_TABLE_PREFIX}" \
        --skip-check \
        --quiet \
        --allow-root
fi

wp config set WP_AUTO_UPDATE_CORE false --allow-root
wp config set AUTOMATIC_UPDATER_DISABLED true --allow-root

# Install WP if not yet installed
if ! $( wp core is-installed --allow-root ); then
	wp core install \
		--path="${WP_ROOT_FOLDER}" \
		--url="${WP_URL}" \
		--title='Test' \
		--admin_user="${ADMIN_USERNAME}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--allow-root
else
    # Set the WP url accordingly. If running the app vs running tests. Tests failing locally if already had app running, vice/versa.
    wp --allow-root option set SITEURL "${WP_URL}"
    wp --allow-root option set HOME "${WP_URL}"
fi

echo "Running WordPress version: $(wp core version --allow-root) at $(wp option get home --allow-root)"
