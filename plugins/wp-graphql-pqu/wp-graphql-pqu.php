<?php
/**
 * Plugin Name: WPGraphQL Persisted Query URLs
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Experimental beta — not for production. Persisted GraphQL operations at permalink URLs (not long query strings) for URL-based edge purge where tag invalidation is unavailable; extends WPGraphQL Smart Cache. APIs may change.
 * Author: WPGraphQL
 * Author URI: https://www.wpgraphql.com
 * Version: 0.1.0-beta.1
 * Text Domain: wp-graphql-pqu
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires WPGraphQL: 2.0.0
 * WPGraphQL Tested Up To: 2.0.0
 * Network: false
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WPGraphQL\PQU
 * @since 0.1.0-beta.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
if ( ! defined( 'WPGRAPHQL_PQU_VERSION' ) ) {
	define( 'WPGRAPHQL_PQU_VERSION', '0.1.0-beta.1' );
}

if ( ! defined( 'WPGRAPHQL_PQU_PLUGIN_DIR' ) ) {
	define( 'WPGRAPHQL_PQU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPGRAPHQL_PQU_PLUGIN_URL' ) ) {
	define( 'WPGRAPHQL_PQU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPGRAPHQL_PQU_PLUGIN_FILE' ) ) {
	define( 'WPGRAPHQL_PQU_PLUGIN_FILE', __FILE__ );
}

// Autoload required classes.
$autoload = WPGRAPHQL_PQU_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Composer autoload path from plugin constant.
	require_once $autoload;
}

/**
 * Initialize the plugin
 *
 * @return void
 */
function wpgraphql_pqu_init() {
	$app = \WPGraphQL\PQU\App::instance();
	add_action( 'plugins_loaded', [ $app, 'init' ], 50 );
}

wpgraphql_pqu_init();

/**
 * WP-CLI commands
 */
function wpgraphql_pqu_register_cli_commands(): void {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
		return;
	}

	if ( ! class_exists( \WPGraphQL\PQU\CLI\RegisterCommand::class ) ) {
		$cli_file = WPGRAPHQL_PQU_PLUGIN_DIR . 'src/CLI/RegisterCommand.php';
		if ( file_exists( $cli_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- CLI bootstrap; path from plugin constant.
			require_once $cli_file;
		}
	}

	if ( class_exists( \WPGraphQL\PQU\CLI\RegisterCommand::class ) ) {
		\WP_CLI::add_command( 'graphql-pqu', \WPGraphQL\PQU\CLI\RegisterCommand::class );
	}
}

add_action( 'cli_init', 'wpgraphql_pqu_register_cli_commands' );

// Register activation/deactivation hooks unconditionally (must be registered when plugin file loads).
register_activation_hook( WPGRAPHQL_PQU_PLUGIN_FILE, [ \WPGraphQL\PQU\App::instance(), 'activate' ] );
register_deactivation_hook( WPGRAPHQL_PQU_PLUGIN_FILE, [ \WPGraphQL\PQU\App::instance(), 'deactivate' ] );
