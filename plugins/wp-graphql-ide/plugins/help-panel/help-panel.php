<?php
/**
 * Plugin Name: Help Panel
 * Description: Registers a new Activity Bar Panel with the IDE
 */

namespace WPGraphQLIDE\HelpPanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_HELP_PANEL_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_HELP_PANEL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the scripts and styles for the Query Composer panel.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_HELP_PANEL_DIR_PATH . 'build/help-panel.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'help-panel',
		WPGRAPHQL_IDE_HELP_PANEL_URL . 'build/help-panel.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);

}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\enqueue_assets' );
