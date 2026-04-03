<?php
/**
 * Plugin Name: WPGraphQL Persisted Queries Cache
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Experimental beta — not for production. Persisted GraphQL queries via permalink URLs (not query strings) for URL-based cache purge on hosts without tag invalidation; extends WPGraphQL Smart Cache. APIs may change.
 * Author: WPGraphQL
 * Author URI: https://www.wpgraphql.com
 * Version: 0.1.0-beta.1
 * Text Domain: wp-graphql-pqc
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires WPGraphQL: 2.0.0
 * WPGraphQL Tested Up To: 2.0.0
 * Network: false
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WPGraphQL\PQC
 * @since 0.1.0-beta.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
if ( ! defined( 'WPGRAPHQL_PQC_VERSION' ) ) {
	define( 'WPGRAPHQL_PQC_VERSION', '0.1.0-beta.1' );
}

if ( ! defined( 'WPGRAPHQL_PQC_PLUGIN_DIR' ) ) {
	define( 'WPGRAPHQL_PQC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPGRAPHQL_PQC_PLUGIN_URL' ) ) {
	define( 'WPGRAPHQL_PQC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPGRAPHQL_PQC_PLUGIN_FILE' ) ) {
	define( 'WPGRAPHQL_PQC_PLUGIN_FILE', __FILE__ );
}

// Autoload required classes.
$autoload = WPGRAPHQL_PQC_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Composer autoload path from plugin constant.
	require_once $autoload;
}

/**
 * Initialize the plugin
 *
 * @return void
 */
function wpgraphql_pqc_init() {
	$app = \WPGraphQL\PQC\App::instance();
	add_action( 'plugins_loaded', [ $app, 'init' ], 50 );
}

wpgraphql_pqc_init();

/**
 * WP-CLI commands
 */
function wpgraphql_pqc_register_cli_commands(): void {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
		return;
	}

	if ( ! class_exists( \WPGraphQL\PQC\CLI\RegisterCommand::class ) ) {
		$cli_file = WPGRAPHQL_PQC_PLUGIN_DIR . 'src/CLI/RegisterCommand.php';
		if ( file_exists( $cli_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- CLI bootstrap; path from plugin constant.
			require_once $cli_file;
		}
	}

	if ( class_exists( \WPGraphQL\PQC\CLI\RegisterCommand::class ) ) {
		\WP_CLI::add_command( 'graphql-pqc', \WPGraphQL\PQC\CLI\RegisterCommand::class );
	}
}

add_action( 'cli_init', 'wpgraphql_pqc_register_cli_commands' );

// Register activation/deactivation hooks unconditionally (must be registered when plugin file loads).
register_activation_hook( WPGRAPHQL_PQC_PLUGIN_FILE, [ \WPGraphQL\PQC\App::instance(), 'activate' ] );
register_deactivation_hook( WPGRAPHQL_PQC_PLUGIN_FILE, [ \WPGraphQL\PQC\App::instance(), 'deactivate' ] );
