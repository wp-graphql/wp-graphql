#!/usr/bin/env bash

## Flush permalinks
npm run wp-env run cli -- wp rewrite structure /%postname%/ --hard
npm run wp-env run tests-cli -- wp rewrite structure /%postname%/ --hard

npm run wp-env run cli -- wp rewrite flush --hard
npm run wp-env run tests-cli  -- wp rewrite flush --hard

# Fix internal Docker URL resolution and prevent maintenance mode for acceptance/functional tests
# - wp-env sets WP_SITEURL to localhost:8889, but that port doesn't work inside
#   the container (only port 80 works internally). This mu-plugin rewrites URLs
#   so WPBrowser's loginAs() can follow WordPress-generated URLs correctly.
# - Prevents maintenance mode from blocking test requests
npm run wp-env run tests-cli -- bash -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cat > /var/www/html/wp-content/mu-plugins/wp-env-url-fix.php << '\''MUPLUGIN'\''
<?php
/**
 * Plugin Name: WP-ENV Test Fixes
 * Description: Fixes internal Docker URL resolution and prevents maintenance mode for tests
 */

// Fix internal Docker URL resolution
function wpgraphql_wpenv_fix_url( $url ) {
    return preg_replace( "#https?://(localhost|tests-wordpress):8889#", "http://tests-wordpress", $url );
}
add_filter( "site_url", "wpgraphql_wpenv_fix_url", 1 );
add_filter( "home_url", "wpgraphql_wpenv_fix_url", 1 );
add_filter( "wp_login_url", "wpgraphql_wpenv_fix_url", 1 );
add_filter( "admin_url", "wpgraphql_wpenv_fix_url", 1 );

// Prevent maintenance mode during tests
add_filter( "enable_maintenance_mode", "__return_false" );
add_filter( "wp_auto_update_core", "__return_false" );
add_filter( "auto_update_plugin", "__return_false" );
add_filter( "auto_update_theme", "__return_false" );

// Remove any stale maintenance file on each request
$maintenance_file = ABSPATH . ".maintenance";
if ( file_exists( $maintenance_file ) ) {
    @unlink( $maintenance_file );
}
MUPLUGIN
echo "Installed test fixes mu-plugin"
'

# Get install Path for docker compose commands
cd $(npx wp-env install-path)

# Enable HTTP Authorization header passthrough for Apache
# This must be in Apache's main config, not .htaccess (matches old Docker setup)
# See: https://github.com/wp-graphql/wp-graphql/pull/3448
for container in tests-wordpress wordpress; do
	echo "Configuring Apache Authorization header passthrough for $container..."
	
	# Ensure mod_setenvif is enabled (required for SetEnvIf directive)
	echo "Enabling mod_setenvif..."
	docker compose exec -T -u root $container a2enmod setenvif 2>/dev/null || echo "mod_setenvif already enabled or failed"
	
	if ! docker compose exec -T $container grep -q "HTTP_AUTHORIZATION" /etc/apache2/apache2.conf 2>/dev/null; then
		echo "Adding SetEnvIf directive to Apache config..."
		docker compose exec -T -u root $container bash -c 'echo "SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1" >> /etc/apache2/apache2.conf'
		echo "Gracefully reloading Apache..."
		docker compose exec -T -u root $container apache2ctl graceful
		echo "Done."
	else
		echo "Authorization header passthrough already configured."
	fi
	# Verify it was added
	echo "Verifying Apache config:"
	docker compose exec -T $container grep "HTTP_AUTHORIZATION" /etc/apache2/apache2.conf || echo "WARNING: SetEnvIf not found in Apache config!"
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
