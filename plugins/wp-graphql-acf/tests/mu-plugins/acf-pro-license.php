<?php
/**
 * Plugin Name: ACF Pro License (test env)
 * Description: Defines ACF_PRO_LICENSE from wp-content/acf-license-key.txt so ACF Pro activates in CI/local test env without manual activation. The key file is written by install-acf.sh and is not in the repo.
 *
 * @package WPGraphQL\ACF\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$key_file = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/acf-license-key.txt' : '';
if ( $key_file !== '' && is_readable( $key_file ) && ! defined( 'ACF_PRO_LICENSE' ) ) {
	$key = trim( (string) file_get_contents( $key_file ) );
	if ( $key !== '' ) {
		define( 'ACF_PRO_LICENSE', $key );
	}
}
