<?php
/**
 * Plugin Name: WPGraphQL IDE Smart Cache Panel
 * Description: Renders the `graphqlSmartCache` response extension as a dedicated tab in the WPGraphQL IDE response panel.
 *
 * Built inside wp-graphql-ide so it can later be lifted into the
 * wp-graphql-smart-cache plugin itself — the renderer is server-agnostic
 * (reads only from `response.extensions.graphqlSmartCache`) and depends
 * only on the public `registerResponseExtensionTab` API exposed on
 * `window.WPGraphQLIDE`. Moving it later is a directory copy plus a
 * `wp_enqueue_script` adjustment.
 */

namespace WPGraphQLIDE\SmartCachePanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_SMART_CACHE_PANEL_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_SMART_CACHE_PANEL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the script for the Smart Cache Panel.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_SMART_CACHE_PANEL_DIR_PATH . 'build/smart-cache-panel.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'smart-cache-panel',
		WPGRAPHQL_IDE_SMART_CACHE_PANEL_URL . 'build/smart-cache-panel.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);
}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\enqueue_assets' );
