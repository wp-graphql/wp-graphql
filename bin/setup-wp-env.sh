#!/usr/bin/env bash

## Flush permalinks
npm run wp-env run cli -- wp rewrite structure /%postname%/ --hard
npm run wp-env run tests-cli -- wp rewrite structure /%postname%/ --hard

npm run wp-env run cli -- wp rewrite flush --hard
npm run wp-env run tests-cli  -- wp rewrite flush --hard

# Delete default WordPress content to ensure consistent test state
# The "Hello world!" post and default comment can interfere with test assertions
npm run wp-env run tests-cli -- wp post delete 1 --force 2>/dev/null || true
npm run wp-env run tests-cli -- wp comment delete 1 --force 2>/dev/null || true

# Auto-update constants are now set in tests/_data/config.php which is loaded
# by Codeception's WPLoader. Setting them here in wp-config.php causes
# "constant already defined" warnings since config.php loads first.

# Explicitly deactivate maintenance mode if active (matches old Docker setup)
npm run wp-env run tests-cli -- wp maintenance-mode deactivate 2>/dev/null || echo "Maintenance mode already inactive or command not available"

# Remove .maintenance file if it exists
npm run wp-env run tests-cli -- rm -f /var/www/html/.maintenance 2>/dev/null || true

# Fix internal Docker URL resolution for acceptance/functional tests
# wp-env sets WP_SITEURL to localhost:8889, but that port doesn't work inside
# the container (only port 80 works internally). This mu-plugin rewrites URLs
# so WPBrowser's loginAs() can follow WordPress-generated URLs correctly.
#
# IMPORTANT: This fix is ONLY applied when the request comes from Codeception tests
# (identified by X_TEST_REQUEST or X_WPBROWSER_REQUEST headers). Playwright e2e tests
# run outside Docker and need localhost:8889 URLs, so we must NOT rewrite for them.
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

# Get install Path for docker compose commands
cd $(npx wp-env install-path)

# Configure Apache (matches old Docker setup)
for container in tests-wordpress wordpress; do
	echo "Configuring Apache for $container..."
	
	# Set ServerName to prevent Apache warnings (matches old Docker setup)
	if ! docker compose exec -T $container grep -q "ServerName localhost" /etc/apache2/apache2.conf 2>/dev/null; then
		echo "Setting ServerName localhost..."
		docker compose exec -T -u root $container bash -c 'echo "ServerName localhost" >> /etc/apache2/apache2.conf'
	fi
	
	# Ensure mod_setenvif is enabled (required for SetEnvIf directive)
	echo "Enabling mod_setenvif..."
	docker compose exec -T -u root $container a2enmod setenvif 2>/dev/null || echo "mod_setenvif already enabled or failed"
	
	# Enable HTTP Authorization header passthrough
	# This must be in Apache's main config, not .htaccess (matches old Docker setup)
	# See: https://github.com/wp-graphql/wp-graphql/pull/3448
	if ! docker compose exec -T $container grep -q "HTTP_AUTHORIZATION" /etc/apache2/apache2.conf 2>/dev/null; then
		echo "Adding SetEnvIf directive to Apache config..."
		docker compose exec -T -u root $container bash -c 'echo "SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1" >> /etc/apache2/apache2.conf'
	else
		echo "Authorization header passthrough already configured."
	fi
	
	# Reload Apache to apply changes
	echo "Reloading Apache..."
	docker compose exec -T -u root $container apache2ctl graceful
	
	# Verify config
	echo "Verifying Apache config:"
	docker compose exec -T $container grep -E "(ServerName|HTTP_AUTHORIZATION)" /etc/apache2/apache2.conf || echo "WARNING: Config issue!"
done

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


# Install pdo on tests-cli (still in wp-env install path for docker compose)
if [[ $(docker compose exec -T -u root tests-cli php -m | grep pdo_mysql) != "pdo_mysql" ]]; then
	echo "Installing: pdo_mysql Extension on tests-cli."
	if docker compose exec -T -u root tests-cli docker-php-ext-install pdo_mysql; then
		if [[ $(docker compose exec -T -u root tests-cli php -m | grep pdo_mysql) == "pdo_mysql" ]]; then
			echo "pdo_mysql Extension on tests-cli: Installed."
		else
			echo "ERROR: pdo_mysql Extension on tests-cli: Installation command succeeded but extension not loaded." >&2
			exit 1
		fi
	else
		echo "ERROR: pdo_mysql Extension on tests-cli: Installation failed." >&2
		exit 1
	fi
else
	echo "pdo_mysql Extension on tests-cli: Already installed."
fi

cd -
