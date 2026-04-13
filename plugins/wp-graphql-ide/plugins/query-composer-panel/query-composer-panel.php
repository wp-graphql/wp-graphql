<?php
/**
 * Plugin Name: Query Composer Panel
 * Description: Registers a new Activity Bar Panel with the IDE
 */

namespace WPGraphQLIDE\QueryComposerPanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_QUERY_COMPOSER_PANEL_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_QUERY_COMPOSER_PANEL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the scripts and styles for the Query Composer panel.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_QUERY_COMPOSER_PANEL_DIR_PATH . 'build/query-composer-panel.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'query-composer-panel',
		WPGRAPHQL_IDE_QUERY_COMPOSER_PANEL_URL . 'build/query-composer-panel.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);

	wp_enqueue_style(
		'query-composer-panel',
		WPGRAPHQL_IDE_QUERY_COMPOSER_PANEL_URL . 'build/style-query-composer-panel.css',
		[],
		$asset_file['version']
	);
}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\enqueue_assets' );
