<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WPGraphQL
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/wp-graphql.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Setup some basic theme supports so we can query against it
 */
function _theme_setup() {

	/**
	 * Create a random menu
	 */
	$menu_id = wp_create_nav_menu( 'test_menu' );

	/**
	 * Register some menus to use in our testing
	 */
	$registered_menus = [
		'header' => 'Header Nav',
		'footer' => 'Footer Nav',
	];
	register_nav_menus( $registered_menus );

	$locations = [];
	foreach ( $registered_menus as $key => $value ) {
		$locations[ $key ] = $menu_id;
	}

	/**
	 * Set the created "test" menu as the active menu for each of the registered locations
	 */
	set_theme_mod( 'nav_menu_locations', $locations );

}
tests_add_filter( 'graphql_init', '_theme_setup' );

/**
 * Require the autholoader
 */
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
require_once dirname( dirname( __FILE__ ) ) . '/access-functions.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
