#!/usr/bin/env bash

# ===========================================
# WPGraphQL Monorepo - wp-env Setup Script
# ===========================================
# This script runs automatically via the afterStart lifecycle hook
# in .wp-env.json when `npm run wp-env start` is executed.

# Setups WordPress environments inside wp-env
#
# @ todo call inside container via afterStart when we have a clearer mono repo strategy.
#
# Arguments:
#   $1 - wp-env Environment Name
setup_wp() {
	local ENV_NAME="$1"
	echo "=== Setting up WPGraphQL development environment ==="

	# Always activate wp-graphql (base plugin required for all tests)
	npm run wp-env run $ENV_NAME -- wp plugin activate wp-graphql 2>/dev/null || true

	# Activate wp-graphql-smart-cache in tests-cli environment (where all tests run)
	# This ensures smart-cache tests have the plugin active, and wp-graphql tests should be
	# resilient to extensions being active (which is realistic for production scenarios)
	if [ "$ENV_NAME" = "tests-cli" ]; then
		npm run wp-env run $ENV_NAME -- wp plugin activate wp-graphql-smart-cache 2>/dev/null || true
	fi

	# Flush permalinks (must be done after plugins are activated so GraphQL endpoint is registered)
	npm run wp-env run $ENV_NAME -- wp rewrite structure /%postname%/ --hard
	npm run wp-env run $ENV_NAME -- wp rewrite flush --hard

	# Delete default WordPress content to ensure consistent test state
	# The "Hello world!" post and default comment can interfere with test assertions
	npm run wp-env run $ENV_NAME -- wp post delete 1 --force 2>/dev/null || true
	npm run wp-env run $ENV_NAME -- wp comment delete 1 --force 2>/dev/null || true

	# Explicitly deactivate maintenance mode if active
	npm run wp-env run $ENV_NAME -- wp maintenance-mode deactivate 2>/dev/null || echo "Maintenance mode already inactive or command not available"

	# Remove .maintenance file if it exists
	npm run wp-env run $ENV_NAME -- bash -c 'rm -f /var/www/html/.maintenance 2>/dev/null || true'
}


# Install the pdo_mysql extension on the provided container
# Arguments:
#   $1 - Docker Container ID
#   $2 - wp-env Environment Name
install_pdo_mysql() {
	local CONTAINER_ID="$1"
	local ENV_NAME="$2"

	if docker exec -u root "$CONTAINER_ID" php -m | grep -q pdo_mysql; then
		echo "pdo_mysql Extension on $ENV_NAME: Already installed."
		return 0
	fi

	echo "Installing: pdo_mysql Extension on $ENV_NAME."
	if ! docker exec -u root "$CONTAINER_ID" docker-php-ext-install pdo_mysql > /dev/null 2>&1; then
		echo "WARNING: pdo_mysql Extension on $ENV_NAME: Installation failed. This is expected on ephemeral containers." >&2
		return 0
	fi

	if ! docker exec -u root "$CONTAINER_ID" php -m | grep -q pdo_mysql; then
		echo "WARNING: pdo_mysql Extension on $ENV_NAME: Installation command succeeded but extension not loaded." >&2
		return 0
	fi

	echo "pdo_mysql Extension on $ENV_NAME: Installed."
}

# Install PCOV
# Arguments:
#   $1 - wp-env Environment Name
install_pcov() {
	local ENV_NAME="$1"

	if npm run wp-env run $ENV_NAME -- php -m | grep -q pcov; then
		echo "pcov Extension on $ENV_NAME: Already installed."
		return 0
	fi

	echo "Installing: pcov Extension on $ENV_NAME."
	if ! npm run wp-env run $ENV_NAME -- sudo pecl install pcov > /dev/null 2>&1; then
		echo "WARNING: pcov Extension on $ENV_NAME: Installation failed. This is expected on ephemeral containers." >&2
		return 0
	fi

	npm run wp-env run $ENV_NAME -- bash -- -c 'echo "extension=pcov" | sudo tee /usr/local/etc/php/conf.d/99-pcov.ini > /dev/null'
	npm run wp-env run $ENV_NAME -- bash -- -c 'echo "pcov.enabled=1" | sudo tee -a /usr/local/etc/php/conf.d/99-pcov.ini > /dev/null'

	echo "pcov Extension on $ENV_NAME: Installed."
}

# Configure Apache (to match old Docker setup) on the specified wp-env environment
# Arguments:
#   $1 - wp-env Environment Name
configure_apache() {
	local ENV_NAME="$1"

	echo "Configuring Apache for $ENV_NAME..."
	
	# Set ServerName to prevent Apache warnings (matches old Docker setup)
	if ! docker compose exec -T $ENV_NAME grep -q "ServerName localhost" /etc/apache2/apache2.conf 2>/dev/null; then
		echo "Setting ServerName localhost..."
		docker compose exec -T -u root $ENV_NAME bash -c 'echo "ServerName localhost" >> /etc/apache2/apache2.conf'
	fi
	
	# Ensure mod_setenvif is enabled (required for SetEnvIf directive)
	echo "Enabling mod_setenvif..."
	docker compose exec -T -u root $ENV_NAME a2enmod setenvif 2>/dev/null || echo "mod_setenvif already enabled or failed"
	
	# Enable HTTP Authorization header passthrough
	# This must be in Apache's main config, not .htaccess (matches old Docker setup)
	# See: https://github.com/wp-graphql/wp-graphql/pull/3448
	if ! docker compose exec -T $ENV_NAME grep -q "HTTP_AUTHORIZATION" /etc/apache2/apache2.conf 2>/dev/null; then
		echo "Adding SetEnvIf directive to Apache config..."
		docker compose exec -T -u root $ENV_NAME bash -c 'echo "SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1" >> /etc/apache2/apache2.conf'
	else
		echo "Authorization header passthrough already configured."
	fi
	
	# Reload Apache to apply changes
	echo "Reloading Apache..."
	docker compose exec -T -u root $ENV_NAME apache2ctl graceful
	
	# Verify config
	echo "Verifying Apache config:"
	docker compose exec -T $ENV_NAME grep -E "(ServerName|HTTP_AUTHORIZATION)" /etc/apache2/apache2.conf || echo "WARNING: Config issue!"
}

# Fix internal Docker URL resolution for acceptance/functional tests
# wp-env sets WP_SITEURL to localhost:8889, but that port doesn't work inside
# the container (only port 80 works internally). This mu-plugin rewrites URLs
# so WPBrowser's loginAs() can follow WordPress-generated URLs correctly.
#
# IMPORTANT: This fix is ONLY applied when the request comes from Codeception tests
# (identified by X_TEST_REQUEST or X_WPBROWSER_REQUEST headers). Playwright e2e tests
# run outside Docker and need localhost:8889 URLs, so we must NOT rewrite for them.
#
# @todo move to a mapped `mu-plugins` directory in .wp-env.json when this is no longer shared.
create_url_fix_mu_plugin() {
	npm run wp-env run tests-cli -- bash -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cat > /var/www/html/wp-content/mu-plugins/wp-env-url-fix.php << '\''MUPLUGIN'\''
<?php
/**
 * Plugin Name: WP-ENV URL Fix
 * Description: Fixes internal Docker URL resolution for Codeception tests
 */
function wpgraphql_wpenv_fix_url( $url ) {
    // Only apply URL fix for Codeception tests (WPBrowser).
    // These tests run inside Docker and send specific headers.
    // Playwright e2e tests run outside Docker and need localhost URLs.
    $is_codeception_request = (
        ! empty( $_SERVER["HTTP_X_TEST_REQUEST"] ) ||
        ! empty( $_SERVER["HTTP_X_WPBROWSER_REQUEST"] )
    );

    if ( ! $is_codeception_request ) {
        return $url;
    }

    return preg_replace( "#https?://(localhost|tests-wordpress):8889#", "http://tests-wordpress", $url );
}
add_filter( "site_url", "wpgraphql_wpenv_fix_url", 1 );
add_filter( "home_url", "wpgraphql_wpenv_fix_url", 1 );
add_filter( "wp_login_url", "wpgraphql_wpenv_fix_url", 1 );
add_filter( "admin_url", "wpgraphql_wpenv_fix_url", 1 );
MUPLUGIN
echo "Installed URL fix mu-plugin"
'
}

#============================================
# Main Script Execution
#============================================

echo "=== Configuring wp-env for WPGraphQL ==="

# Run setup_wp in parallel for both environments
setup_wp "cli" &
setup_wp "tests-cli" &
wait

# Install the URL fix mu-plugin on tests-cli environment
create_url_fix_mu_plugin

# Get install Path for docker compose commands
cd "$(npm run wp-env install-path 2>/dev/null | tail -1)"

# Configure Apache (in parallel) to match old Docker setup
configure_apache "tests-wordpress" &
configure_apache "wordpress" &
wait

# Wait for Apache to stabilize after config changes
echo "Waiting for Apache to stabilize..."
sleep 2

# Warmup request to trigger any initialization and verify WordPress is working
echo "Making warmup request to WordPress..."
docker compose exec -T tests-wordpress curl -s -o /dev/null -w "%{http_code}" http://localhost/ || echo "Warmup request failed"
echo ""

# Debug: Show mu-plugin installation (using docker compose since we're in wp-env install path)
echo "Verifying mu-plugin installation:"
docker compose exec -T tests-cli ls -la /var/www/html/wp-content/mu-plugins/ || echo "WARNING: mu-plugins directory issue!"

# Verify mu-plugin content
echo "Verifying mu-plugin content:"
docker compose exec -T tests-cli cat /var/www/html/wp-content/mu-plugins/wp-env-url-fix.php || echo "WARNING: mu-plugin file issue!"

# Verify WordPress is responding correctly
echo "Verifying WordPress is responding:"
docker compose exec -T tests-cli wp option get siteurl || echo "WARNING: WordPress not responding!"

cd -

# Install pdo_mysql extension in the tests-cli environment
CONTAINER_ID="$(docker ps | grep tests-cli  | awk '{print $1}')"
if [[ -n "$CONTAINER_ID" ]]; then
	install_pdo_mysql "$CONTAINER_ID" "tests-cli"
fi

if [[ "$PCOV_ENABLED" == "1" ]]; then
	# Install pcov extension in the tests-cli environment
	install_pcov "tests-cli"
fi
