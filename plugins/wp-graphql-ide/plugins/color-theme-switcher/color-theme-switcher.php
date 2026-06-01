<?php
/**
 * Plugin Name: Color Theme Switcher
 * Description: Proof-of-concept theming sub-plugin. Adds a paintbrush topbar action that opens a workspace tab listing every registered WP admin color scheme. Selecting one swaps the admin colors stylesheet live so the IDE's tokenized CSS picks up the new accent and palette without a reload.
 */

namespace WPGraphQLIDE\ColorThemeSwitcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_COLOR_THEME_SWITCHER_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_COLOR_THEME_SWITCHER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue the sub-plugin bundle and localize the color-scheme data the
 * UI renders.
 *
 * @return void
 */
function enqueue_assets(): void {
	$asset_file = null;
	$asset_path = WPGRAPHQL_IDE_COLOR_THEME_SWITCHER_DIR_PATH . 'build/color-theme-switcher.asset.php';

	if ( file_exists( $asset_path ) ) {
		$asset_file = include $asset_path;
	}

	if ( empty( $asset_file['dependencies'] ) ) {
		return;
	}

	wp_enqueue_script(
		'color-theme-switcher',
		WPGRAPHQL_IDE_COLOR_THEME_SWITCHER_URL . 'build/color-theme-switcher.js',
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide' ] ),
		$asset_file['version'],
		true
	);

	wp_localize_script(
		'color-theme-switcher',
		'WPGraphQLIDEColorThemeSwitcher',
		[
			'current' => get_user_option( 'admin_color' ) ?: 'fresh',
			'schemes' => get_registered_schemes(),
		]
	);
}
add_action( 'wpgraphql_ide_enqueue_script', __NAMESPACE__ . '\\enqueue_assets' );

/**
 * Persist the user's chosen color scheme. Mirrors `user-edit.php`'s
 * behaviour: it writes the slug straight to `admin_color` user meta,
 * which is what `get_user_option( 'admin_color' )` reads on every
 * subsequent admin pageload to enqueue the right colors stylesheet.
 *
 * @return void
 */
function register_rest_routes(): void {
	register_rest_route(
		'wpgraphql-ide/v1',
		'/color-scheme',
		[
			'methods'             => \WP_REST_Server::EDITABLE,
			'permission_callback' => __NAMESPACE__ . '\\rest_can_set_scheme',
			'callback'            => __NAMESPACE__ . '\\rest_set_scheme',
			'args'                => [
				'scheme' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
				],
			],
		]
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );

/**
 * @return bool
 */
function rest_can_set_scheme(): bool {
	return is_user_logged_in();
}

/**
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response|\WP_Error
 */
function rest_set_scheme( \WP_REST_Request $request ) {
	$scheme = (string) $request->get_param( 'scheme' );

	if ( '' === $scheme ) {
		return new \WP_Error(
			'wpgraphql_ide_invalid_scheme',
			__( 'A color scheme slug is required.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}

	// `$_wp_admin_css_colors` is populated by `register_admin_color_schemes()`
	// which hooks `admin_init` — a hook that does not fire on REST requests.
	// Force-load the admin helpers so the global is populated and the same
	// validation table the user-profile page sees is available here too.
	if ( ! is_admin() && function_exists( 'register_admin_color_schemes' ) === false ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	if ( function_exists( 'register_admin_color_schemes' ) ) {
		register_admin_color_schemes();
	}

	$schemes = get_registered_schemes();

	// If somehow no schemes are registered (very stripped install), fall
	// through and trust the sanitized slug — `get_user_option` already
	// rejects unknown values at render time by falling back to `fresh`.
	if ( ! empty( $schemes ) && ! isset( $schemes[ $scheme ] ) ) {
		return new \WP_Error(
			'wpgraphql_ide_invalid_scheme',
			__( 'Unknown color scheme.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}

	update_user_meta( get_current_user_id(), 'admin_color', $scheme );

	return rest_ensure_response( [ 'scheme' => $scheme ] );
}

/**
 * Normalize WP's `$_wp_admin_css_colors` global into a JSON-safe shape
 * the React UI can consume directly.
 *
 * @return array<string, array<string, mixed>>
 */
function get_registered_schemes(): array {
	global $_wp_admin_css_colors;

	if ( ! is_array( $_wp_admin_css_colors ) ) {
		return [];
	}

	$schemes = [];

	foreach ( $_wp_admin_css_colors as $slug => $scheme ) {
		// Each scheme object exposes `name`, `url`, `colors`, `icon_colors`.
		// We surface the four palette colors as `palette` so the UI can
		// render uniform swatches without inspecting the WP object shape.
		$schemes[ $slug ] = [
			'slug'    => (string) $slug,
			'name'    => isset( $scheme->name ) ? (string) $scheme->name : (string) $slug,
			'url'     => isset( $scheme->url ) ? (string) $scheme->url : '',
			'palette' => isset( $scheme->colors ) && is_array( $scheme->colors )
				? array_values( $scheme->colors )
				: [],
		];
	}

	return $schemes;
}
