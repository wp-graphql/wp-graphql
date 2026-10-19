<?php
/**
 * Constants defined in this file are to help PHPStan analyze code where
 * constants outside the plugin (WordPress core constants, etc.) are being used.
 */

define( 'PHPSTAN', true );

// `WPGRAPHQL_IDE_PLUGIN_DIR_PATH` is defined at runtime in wpgraphql-ide.php,
// which PHPStan does not load as a bootstrap. Declare it here so `includes/`
// files that build asset paths from it resolve during static analysis.
define( 'WPGRAPHQL_IDE_PLUGIN_DIR_PATH', __DIR__ . '/../' );
