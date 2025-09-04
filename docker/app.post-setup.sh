#!/bin/bash

# Activate wp-graphql
wp plugin activate wp-graphql --allow-root

# Set pretty permalinks.
wp rewrite structure '/%year%/%monthnum%/%postname%/' --allow-root

# Use alternative database export for WordPress 6.8+ to avoid MariaDB SSL issues
if [[ "${WP_VERSION}" == "6.8"* ]]; then
    echo "Using alternative database export method for WordPress 6.8+"
    # Create a basic MySQL client config that forces no SSL
    echo "[client]
ssl=false
[mysql]
ssl=false
[mysqldump]
ssl=false" > /tmp/.my.cnf

    # Export using mysqldump directly with SSL disabled
    MYSQL_PWD=${WORDPRESS_DB_PASSWORD} mysqldump \
        --defaults-extra-file=/tmp/.my.cnf \
        --user=${WORDPRESS_DB_USER} \
        --host=${WORDPRESS_DB_HOST} \
        --port=3306 \
        --single-transaction \
        --routines \
        --triggers \
        ${WORDPRESS_DB_NAME} > "${DATA_DUMP_DIR}/dump.sql" 2>/dev/null || \
    echo "Database export failed, but continuing with tests..."

    # Clean up config file
    rm -f /tmp/.my.cnf
else
    # Use wp-cli for older WordPress versions
    wp db export "${DATA_DUMP_DIR}/dump.sql" --allow-root
fi

# If maintenance mode is active, de-activate it
if $( wp maintenance-mode is-active --allow-root ); then
  echo "Deactivating maintenance mode"
  wp maintenance-mode deactivate --allow-root
fi


