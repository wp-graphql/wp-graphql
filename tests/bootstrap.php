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
 * Require the autholoader
 */
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
require_once dirname( dirname( __FILE__ ) ) . '/access-functions.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
