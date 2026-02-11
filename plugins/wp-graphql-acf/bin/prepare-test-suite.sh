#!/usr/bin/env bash

# Script to prepare test suite with detected ACF plugins
# This creates temporary suite files that include the detected ACF plugins
# The suite files extend codeception.dist.yml and only override the WPLoader plugins list

# Detect ACF plugins
eval $(bash bin/detect-acf-plugins.sh)

# Build plugins array (YAML format)
PLUGINS_YAML=""
ACTIVATE_PLUGINS_YAML=""

# Start with required plugins
# Note: Plugin directory is wp-graphql-acf, file is wpgraphql-acf.php
PLUGINS_YAML="        - wp-graphql/wp-graphql.php"$'\n'"        - wp-graphql-acf/wpgraphql-acf.php"
ACTIVATE_PLUGINS_YAML="        - wp-graphql/wp-graphql.php"$'\n'"        - wp-graphql-acf/wpgraphql-acf.php"

# Add ACF plugins if detected
if [ -n "$ACF_PLUGIN_SLUG" ]; then
  PLUGINS_YAML="${PLUGINS_YAML}"$'\n'"        - ${ACF_PLUGIN_SLUG}"
  ACTIVATE_PLUGINS_YAML="${ACTIVATE_PLUGINS_YAML}"$'\n'"        - ${ACF_PLUGIN_SLUG}"
else
  echo "⚠️  WARNING: ACF plugin not detected!" >&2
  echo "   ACF must be installed before running tests." >&2
  echo "   Run: npm run install-acf" >&2
  echo "   Or: npm run install-acf:pro (requires ACF_LICENSE_KEY)" >&2
  echo "" >&2
fi

if [ -n "$ACF_EXTENDED_PLUGIN_SLUG" ]; then
  PLUGINS_YAML="${PLUGINS_YAML}"$'\n'"        - ${ACF_EXTENDED_PLUGIN_SLUG}"
  ACTIVATE_PLUGINS_YAML="${ACTIVATE_PLUGINS_YAML}"$'\n'"        - ${ACF_EXTENDED_PLUGIN_SLUG}"
fi

# Create wpunit suite file
WPUNIT_SUITE_FILE="tests/wpunit.suite.yml"
cat > "$WPUNIT_SUITE_FILE" << EOF
# Codeception Test Suite Configuration
# Auto-generated - includes detected ACF plugins
# This file extends codeception.dist.yml and overrides only the WPLoader plugins list
actor: WpunitTester
modules:
  enabled:
    - Asserts
    - lucatume\WPBrowser\Module\WPLoader
  config:
    lucatume\WPBrowser\Module\WPLoader:
      wpRootFolder: '%TEST_WP_ROOT_FOLDER%'
      dbName: '%TEST_DB_NAME%'
      dbHost: '%TEST_DB_HOST%'
      dbUser: '%TEST_DB_USER%'
      dbPassword: '%TEST_DB_PASSWORD%'
      tablePrefix: '%TEST_WP_TABLE_PREFIX%'
      domain: '%TEST_WP_DOMAIN%'
      adminEmail: '%TEST_ADMIN_EMAIL%'
      multisite: '%MULTISITE%'
      title: 'Test'
      theme: '%TEST_THEME%'
      plugins:
${PLUGINS_YAML}
      activatePlugins:
${ACTIVATE_PLUGINS_YAML}
      configFile: 'tests/_data/config.php'
EOF

# Create functional suite file
FUNCTIONAL_SUITE_FILE="tests/functional.suite.yml"
cat > "$FUNCTIONAL_SUITE_FILE" << EOF
# Codeception Test Suite Configuration
# Auto-generated - includes detected ACF plugins
# This file extends codeception.dist.yml and overrides only the WPLoader plugins list
actor: FunctionalTester
modules:
  enabled:
    - Asserts
    - REST
    - lucatume\WPBrowser\Module\WPBrowser
    - lucatume\WPBrowser\Module\WPDb
    - lucatume\WPBrowser\Module\WPLoader
  config:
    lucatume\WPBrowser\Module\WPDb:
      cleanup: false
    lucatume\WPBrowser\Module\WPLoader:
      loadOnly: true
      wpRootFolder: '%TEST_WP_ROOT_FOLDER%'
      dbName: '%TEST_DB_NAME%'
      dbHost: '%TEST_DB_HOST%'
      dbUser: '%TEST_DB_USER%'
      dbPassword: '%TEST_DB_PASSWORD%'
      tablePrefix: '%TEST_WP_TABLE_PREFIX%'
      domain: '%TEST_WP_DOMAIN%'
      adminEmail: '%TEST_ADMIN_EMAIL%'
      multisite: '%MULTISITE%'
      title: 'Test'
      theme: '%TEST_THEME%'
      plugins:
${PLUGINS_YAML}
      activatePlugins:
${ACTIVATE_PLUGINS_YAML}
      configFile: 'tests/_data/config.php'
bootstrap: bootstrap.php
EOF

echo "Created $WPUNIT_SUITE_FILE and $FUNCTIONAL_SUITE_FILE with plugins: wp-graphql/wp-graphql.php wpgraphql-acf/wpgraphql-acf.php${ACF_PLUGIN_SLUG:+ }${ACF_PLUGIN_SLUG}${ACF_EXTENDED_PLUGIN_SLUG:+ }${ACF_EXTENDED_PLUGIN_SLUG}"
