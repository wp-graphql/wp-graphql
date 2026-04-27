<?php
/**
 * Plugin Name: Test Panel
 * Description: Internal test plugin that dogfoods the workspace tab API.
 */

namespace WPGraphQLIDE\TestPanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_TEST_PANEL_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_TEST_PANEL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the test panel script.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_TEST_PANEL_DIR_PATH . 'build/test-panel.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'test-panel',
		WPGRAPHQL_IDE_TEST_PANEL_URL . 'build/test-panel.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);
}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\enqueue_assets' );
