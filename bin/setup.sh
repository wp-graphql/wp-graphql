#!/usr/bin/env bash

# ===========================================
# WPGraphQL Monorepo - wp-env Setup Script
# ===========================================
# This script runs inside the container via the afterStart lifecycle hook
# in .wp-env.json when `npm run wp-env start` is executed.
# It is triggered by bin/after-start.sh.

# Setups a plugin.
# Expects to be run from the plugin root directory.
#
# Arguments:
#   $1 - Plugin slug (directory name under wp-content/plugins)
setup_plugin() {
	local plugin_slug=$1

	echo "Setting up plugin: ${plugin_slug}"

	# Install Composer dependencies for the plugin
	echo "Installing Composer dependencies for ${plugin_slug}..."
	composer install --no-interaction 2>/dev/null || echo "Composer install failed or already installed"
}

# Sets up WordPress environment
post_setup() {
	echo "=== Setting up WPGraphQL development environment ==="

	# Flush permalinks
	wp rewrite structure /%postname%/ --hard
	wp rewrite flush --hard

	# Delete default WordPress content to ensure consistent test state
	# The "Hello world!" post and default comment can interfere with test assertions
	wp post delete 1 --force 2>/dev/null || true
	wp comment delete 1 --force 2>/dev/null || true

	# Explicitly deactivate maintenance mode if active
	wp maintenance-mode deactivate 2>/dev/null || echo "Maintenance mode already inactive or command not available"

	# Remove .maintenance file if it exists
	rm -f /var/www/html/.maintenance 2>/dev/null || true
}

setup_plugin "wp-graphql"
post_setup
