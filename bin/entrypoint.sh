#!/bin/bash

# Run WordPress docker entrypoint.
. docker-entrypoint.sh 'apache2'

set +u

# Ensure mysql is loaded
wait-for-it ${DB_HOST}:${DB_PORT} --timeout=300 -- echo "Application database is operationally..."

# Setup tester scripts.
cp $PROJECT_DIR/bin/setup-database.sh setup-database.sh
chmod 755 /var/www/html/setup-database.sh

# Update our domain to just be the docker container's IP address
export WORDPRESS_DOMAIN=$( hostname -i )
export WORDPRESS_URL="http://$WORDPRESS_DOMAIN"
echo "WORDPRESS_DOMAIN=$WORDPRESS_DOMAIN" >> .env
echo "WORDPRESS_URL=$WORDPRESS_URL" >> .env

# Config WordPress
if [ -f "${WP_ROOT_FOLDER}/wp-config.php" ]; then
	echo "Deleting old wp-config.php"
	rm ${WP_ROOT_FOLDER}/wp-config.php
fi

echo "Creating wp-config.php..."
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

# Install WP if not yet installed
if ! $( wp core is-installed --allow-root ); then
	echo "Installing WordPress..."
	wp core install \
		--path="${WP_ROOT_FOLDER}" \
		--url="${WORDPRESS_URL}" \
		--title='Test' \
		--admin_user="${ADMIN_USERNAME}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--allow-root
fi

echo "Setting pretty permalinks..."
wp rewrite structure '/%year%/%monthnum%/%postname%/' --allow-root

echo "Prepare for app database dump..."
if [ ! -d "${PROJECT_DIR}/local/db" ]; then
	mkdir ${PROJECT_DIR}/local/db
fi
if [ -f "${PROJECT_DIR}/local/db/app_db.sql" ]; then
	rm ${PROJECT_DIR}/local/db/app_db.sql
fi

echo "Dumping app database..."
wp db export "${PROJECT_DIR}/local/db/app_db.sql" \
	--dbuser="root" \
	--dbpass="${ROOT_PASSWORD}" \
	--skip-plugins \
	--skip-themes \
	--allow-root

echo "Setup complete!!!"
echo "WordPress app located at $WORDPRESS_URL";

# Make the "uploads" directory unrestricted.
chmod 777 -R wp-content/uploads

exec "$@"
