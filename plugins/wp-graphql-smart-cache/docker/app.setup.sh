#!/bin/bash
# This script is run by the wp-graphql entrypoint.sh script as app-setup.sh.

# Run the base wp-graphql image setup script then our setup.
. /usr/local/bin/original-app-setup.sh

PLUGINS_DIR=${PLUGINS_DIR-.}
echo "Plugins dir ($PLUGINS_DIR)"

if [ ! -f "${PLUGINS_DIR}/wp-graphql/wp-graphql.php" ]; then
    # WPGRAPHQL_VERSION in format like v1.2.3 or latest
    echo "Install wp-graphql version (${WPGRAPHQL_VERSION})"
    if [[ -z ${WPGRAPHQL_VERSION} || "${WPGRAPHQL_VERSION}" == "latest" ]]; then
        echo "Installing latest WPGraphQL from WordPress.org"
        wp plugin install wp-graphql --activate --allow-root
    else
    	echo "Installing WPGraphQL from Github"
        wp plugin install "https://downloads.wordpress.org/plugin/wp-graphql.${WPGRAPHQL_VERSION-1.4.3}.zip" --allow-root
    fi
fi

# Activate the plugin
wp plugin activate wp-graphql-smart-cache --allow-root
