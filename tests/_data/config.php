<?php
/**
 * Disable autoloading while running tests, as the test
 * suite already bootstraps the autoloader and creates
 * fatal errors when the autoloader is loaded twice
 */
define( 'GRAPHQL_DEBUG', true );

if ( ! defined( 'WPGRAPHQL_AUTOLOAD' ) && false === getenv( 'WPGRAPHQL_AUTOLOAD') ) {
	define( 'WPGRAPHQL_AUTOLOAD', getenv( 'WPGRAPHQL_AUTOLOAD') );
}
