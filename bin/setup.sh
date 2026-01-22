#!/usr/bin/env bash

# ===========================================
# WPGraphQL Monorepo - wp-env Setup Script
# ===========================================
# This script runs inside the container via the afterStart lifecycle hook
# in .wp-env.json when `npm run wp-env start` is executed.
# It is triggered by bin/after-start.sh.

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

post_setup
