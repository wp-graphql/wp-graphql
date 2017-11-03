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

ini_set( 'xdebug.max_nesting_level', 1024 );

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/wp-graphql.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

function _add_fields( $fields ) {

	$fields['testIsPrivate'] = [
		'type' => \WPGraphQL\Types::string(),
		'isPrivate' => true,
		'resolve' => function() {
			return 'isPrivateValue';
		}
	];

	$fields['authCallback'] = [
		'type' => \WPGraphQL\Types::string(),
		'auth' => [
			'callback' => function( $field, $field_key,  $source, $args, $context, $info, $field_resolver ) {
				/**
				 * If the current user isn't the user with the login "admin" throw an error
				 */
				if ( 'schema_admin_test@example.com' !== wp_get_current_user()->user_email ) {
					throw new \GraphQL\Error\UserError( __( 'You need the secret!', 'wp-graphql' ) );
				}
				return $field_resolver;
			}
		],
		'resolve' => function() {
			return 'authCallbackValue';
		}
	];

	$fields['authRoles'] = [
		'type' => \WPGraphQL\Types::string(),
		'auth' => [
			'allowedRoles' => [ 'administrator', 'editor' ],
		],
		'resolve' => function() {
			return 'allowedRolesValue';
		}
	];

	$fields['authCaps'] = [
		'type' => \WPGraphQL\Types::string(),
		'auth' => [
			'allowedCaps' => [ 'manage_options', 'graphql_rocks' ],
		],
		'resolve' => function() {
			return 'allowedCapsValue';
		}
	];

	return $fields;
}

tests_add_filter( 'graphql_post_fields', '_add_fields' );


/**
 * Require the autoloader
 */
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
require_once dirname( dirname( __FILE__ ) ) . '/access-functions.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
