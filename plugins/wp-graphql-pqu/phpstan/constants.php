<?php
/**
 * Constants for PHPStan analysis (WordPress / dependent plugins not loaded).
 *
 * @package WPGraphQL\PQU
 */

if ( ! defined( 'PHPSTAN' ) ) {
	define( 'PHPSTAN', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Satisfy dependency checks referenced by App::can_load_plugin().
if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
	define( 'WPGRAPHQL_VERSION', '2.0.0' );
}
if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_VERSION', '2.0.0' );
}
