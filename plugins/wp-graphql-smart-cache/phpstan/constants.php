<?php
/**
 * Constants defined in this file are to help phpstan analyze code where constants outside the plugin (WordPress core constants, etc) are being used
 */

defined( 'WP_LANG_DIR' ) || define( 'WP_LANG_DIR', true );
defined( 'SAVEQUERIES' ) || define( 'SAVEQUERIES', true );
defined( 'WPGRAPHQL_PLUGIN_URL' ) || define( 'WPGRAPHQL_PLUGIN_URL', true );
defined( 'WP_CONTENT_DIR' ) || define( 'WP_CONTENT_DIR', true );
defined( 'WP_PLUGIN_DIR' ) || define( 'WP_PLUGIN_DIR', true );
defined( 'PHPSTAN' ) || define( 'PHPSTAN', true );
