#!/usr/bin/env bash
# Helper script to run phpcbf on staged PHP files
# This script is called by lint-staged for each PHP file

FILE="$1"

# Get the absolute path of the file
if [[ "$FILE" != /* ]]; then
	# Relative path - make it absolute from repo root
	FILE="$(pwd)/$FILE"
fi

# Extract plugin directory from file path
# e.g., /path/to/repo/plugins/wp-graphql/src/File.php -> /path/to/repo/plugins/wp-graphql
PLUGIN_DIR=$(echo "$FILE" | sed -E 's|(.*/plugins/[^/]+)/.*|\1|')

# Check if plugin directory exists and has composer.json
if [ ! -f "$PLUGIN_DIR/composer.json" ]; then
	echo "⚠️  Skipping $FILE - no composer.json found in $PLUGIN_DIR"
	exit 0
fi

# Get relative path from plugin directory
RELATIVE_FILE="${FILE#$PLUGIN_DIR/}"

# Run phpcbf on the specific file from the plugin directory
cd "$PLUGIN_DIR" || exit 1
composer run fix-cs -- "$RELATIVE_FILE"

# Return success (phpcbf modifies files in place, so we always return 0)
exit 0
