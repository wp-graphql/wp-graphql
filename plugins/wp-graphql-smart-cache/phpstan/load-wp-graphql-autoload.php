<?php
/**
 * PHPStan bootstrap: load WPGraphQL core plugin autoloader.
 *
 * Uses __DIR__ so the path resolves correctly when PHPStan runs from any CWD.
 * Requires composer install to have been run in plugins/wp-graphql (done in CI for this plugin).
 *
 * @package WPGraphQL\SmartCache
 */

$autoload = __DIR__ . '/../../wp-graphql/vendor/autoload.php';
if ( is_file( $autoload ) ) {
	require_once $autoload;
}
