#!/usr/bin/env bash

# Script to detect which ACF plugins are installed (not necessarily active)
# This runs inside the wp-env container and outputs environment variable exports
# WPLoader will activate the plugins, so we just need to check if they're installed

# Detect ACF plugin (Free or Pro) - check if plugin file exists
ACF_PLUGIN_SLUG=""
if [ -f "/var/www/html/wp-content/plugins/advanced-custom-fields-pro/acf.php" ]; then
  ACF_PLUGIN_SLUG="advanced-custom-fields-pro/acf.php"
elif [ -f "/var/www/html/wp-content/plugins/advanced-custom-fields/acf.php" ]; then
  ACF_PLUGIN_SLUG="advanced-custom-fields/acf.php"
fi

# Detect ACF Extended plugin (Free or Pro)
ACF_EXTENDED_PLUGIN_SLUG=""
if [ -f "/var/www/html/wp-content/plugins/acf-extended-pro/acf-extended.php" ]; then
  ACF_EXTENDED_PLUGIN_SLUG="acf-extended-pro/acf-extended.php"
elif [ -f "/var/www/html/wp-content/plugins/acf-extended/acf-extended.php" ]; then
  ACF_EXTENDED_PLUGIN_SLUG="acf-extended/acf-extended.php"
fi

# Output as environment variable exports (can be sourced)
echo "export ACF_PLUGIN_SLUG=\"${ACF_PLUGIN_SLUG}\""
echo "export ACF_EXTENDED_PLUGIN_SLUG=\"${ACF_EXTENDED_PLUGIN_SLUG}\""
