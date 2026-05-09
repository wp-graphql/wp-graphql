<?php
/**
 * Plugin Name:       WPGraphQL IDE
 * Description:       A next-gen query editor for WPGraphQL.
 * Author:            WPGraphQL, Joseph Fusco
 * Author URI:        https://github.com/josephfusco
 * GitHub Plugin URI: https://github.com/wp-graphql/wpgraphql-ide
 * License:           GPL-3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wpgraphql-ide
 * Version:           4.4.1
 * Requires PHP:      7.4
 * Tested up to:      6.8
 * Requires Plugins:  wp-graphql
 *
 * @package WPGraphQLIDE
 */

namespace WPGraphQLIDE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

define( 'WPGRAPHQL_IDE_VERSION', '4.4.1' );
define( 'WPGRAPHQL_IDE_ROOT_ELEMENT_ID', 'wpgraphql-ide-root' );
define( 'WPGRAPHQL_IDE_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPGRAPHQL_IDE_PLUGIN_FILE', __FILE__ );

// Modular feature includes — kept out of this main plugin file to avoid
// further bloat. Each include hooks into WordPress on its own.
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/document-settings.php';
require_once __DIR__ . '/includes/public-endpoint.php';

/**
 * Check if WPGraphQL is available and handle the case where it is not.
 *
 * @return void
 */
function check_wpgraphql_availability() {
	// Check for the WPGraphQL class (available on init)
	// Router is initialized later on after_setup_theme, but we check for it in the enqueue function
	if ( ! class_exists( 'WPGraphQL' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\show_admin_notice' );
	} else {
		add_custom_capabilities();

		do_action( 'wpgraphql_ide_init' );
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\check_wpgraphql_availability' );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function initialize_plugin() {
	// Translation loading is handled by WordPress automatically since
	// 4.6+ for plugins with a matching `Text Domain:` header (we have
	// it, line 10). Calling `load_plugin_textdomain` ourselves used to
	// be the convention but is now redundant — and on WP 6.7+ it
	// actively races with WordPress's own just-in-time loader, which
	// fires `_doing_it_wrong` warnings whenever WP-CLI scans plugin
	// metadata before `init`. Letting WP own the loading entirely
	// removes our half of the race.
	add_action( 'init', [ \WPGraphQLIDE\PostTypes::class, 'register' ] );
	add_action( 'init', [ \WPGraphQLIDE\UserMeta::class, 'register' ] );
	add_action( 'admin_menu', __NAMESPACE__ . '\\register_dedicated_ide_menu' );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\register_wpadminbar_menus', 999 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_graphql_ide_menu_icon_css' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_graphql_ide_menu_icon_css' );
	// Enqueue scripts on both admin and frontend since admin bar appears on both
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_react_app_with_styles' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_react_app_with_styles' );

	add_action( 'graphql_register_settings', __NAMESPACE__ . '\\register_ide_settings' );
	add_action( 'graphql_admin_notices_render_notices', __NAMESPACE__ . '\\graphql_admin_notices_render_notices', 10, 1 );
	add_action( 'graphql_admin_notices_render_notice', __NAMESPACE__ . '\\graphql_admin_notices_render_notice', 10, 4 );

	add_filter( 'graphql_admin_notices_is_allowed_admin_page', __NAMESPACE__ . '\\graphql_admin_notices_is_allowed_admin_page', 10, 3 );
	add_filter( 'script_loader_tag', __NAMESPACE__ . '\\add_defer_attribute_to_script', 10, 2 );
	add_filter( 'graphql_setting_field_config', __NAMESPACE__ . '\\update_graphiql_link_field_config', 10, 3 );
	add_filter( 'graphql_get_setting_section_field_value', __NAMESPACE__ . '\\ensure_graphiql_link_is_unchecked', 10, 5 );
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\\add_settings_link' );

	// Scope REST queries to the current user's own documents/history.
	add_filter( 'rest_graphql_ide_query_query', [ \WPGraphQLIDE\Access::class, 'scope_rest_queries' ] );
	add_filter( 'rest_graphql_ide_history_query', [ \WPGraphQLIDE\Access::class, 'scope_rest_queries' ] );

	// Enforce manage_graphql_ide capability on all IDE REST routes.
	add_filter( 'rest_pre_dispatch', [ \WPGraphQLIDE\Access::class, 'enforce_rest_permissions' ], 10, 3 );

	// Prevent access to documents/history owned by other users on single routes.
	add_filter( 'rest_prepare_graphql_ide_query', [ \WPGraphQLIDE\Access::class, 'restrict_document_response' ], 10, 3 );
	add_filter( 'rest_prepare_graphql_ide_history', [ \WPGraphQLIDE\Access::class, 'restrict_document_response' ], 10, 3 );

	// Cap document title length on every write path so a long POST body
	// can't bloat the DB or break admin-UI layouts. Covers REST creates
	// and updates, the import/upsert flow, and any future direct
	// `wp_insert_post` callers.
	add_filter( 'wp_insert_post_data', [ \WPGraphQLIDE\Access::class, 'cap_document_title_length' ], 10, 2 );

	// Custom REST routes.
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_ide_rest_routes' );

	// GraphQL: register IDE-specific fields (meta) on the exposed types
	// and scope connections to the current user so the IDE's data is
	// queryable from GraphQL but isolated per user — same contract as
	// the REST endpoints. The `graphql_data_is_private` filter closes
	// the single-node lookup hole left by the connection-only filter:
	// without it, `node(id: "...")` could resolve another user's
	// IdeQuery if the requester knew its global ID.
	add_action( 'graphql_register_types', [ \WPGraphQLIDE\GraphQLSchema::class, 'register' ] );
	add_filter( 'graphql_connection_query_args', [ \WPGraphQLIDE\Access::class, 'scope_graphql_connections' ], 10, 2 );
	add_filter( 'graphql_data_is_private', [ \WPGraphQLIDE\Access::class, 'restrict_post_visibility' ], 10, 6 );

	// Strip a deleted document's id from its owner's personal collections.
	add_action( 'before_delete_post', [ \WPGraphQLIDE\UserMeta::class, 'purge_document_from_personal_collections' ], 10, 2 );

	// Core plugins/modules.
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/query-composer-panel/query-composer-panel.php';
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/help-panel/help-panel.php';
}
add_action( 'wpgraphql_ide_init', __NAMESPACE__ . '\\initialize_plugin' );

/**
 * Show admin notice if WPGraphQL is not available.
 *
 * @return void
 */
function show_admin_notice() {
	?>
	<div class="notice notice-error">
		<h3><?php esc_html_e( 'WPGraphQL IDE cannot load', 'wpgraphql-ide' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'WPGraphQL must be installed and active', 'wpgraphql-ide' ); ?></li>
		</ol>
	</div>
	<?php
}

/**
 * Assign custom capability to administrator role on plugin activation.
 *
 * Also seeds example collections + documents on first activation so a
 * fresh install isn't an empty IDE. Seeding is gated by an option so
 * re-activation never duplicates content.
 */
function wpgraphql_ide_activate(): void {
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'manage_graphql_ide' );
	}

	// Post types/taxonomies registered on `init` aren't available during
	// activation, so register them ad-hoc before seeding.
	if ( ! post_type_exists( 'graphql_ide_query' ) ) {
		\WPGraphQLIDE\PostTypes::register();
	}

	\WPGraphQLIDE\ImportExport::seed();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\wpgraphql_ide_activate' );


/**
 * Adds custom capabilities to specified roles.
 */
function add_custom_capabilities(): void {
	$capabilities = get_custom_capabilities();
	$current_hash = generate_capabilities_hash( $capabilities );

	if ( ! has_capabilities_hash_changed( $current_hash ) ) {
		return;
	}

	update_roles_capabilities( $capabilities );
	save_capabilities_hash( $current_hash );
}

/**
 * Retrieves the custom capabilities and their associated roles for the plugin.
 *
 * @return array<string,mixed> The array of custom capabilities and roles.
 */
function get_custom_capabilities() {
	return [
		'manage_graphql_ide' => [ 'administrator' ],
	];
}

/**
 * Generate a hash for the capabilities array.
 *
 * @param array<string,mixed> $capabilities Array of capabilities and roles.
 * @return string MD5 hash of the capabilities array.
 */
function generate_capabilities_hash( $capabilities ) {
	return md5( (string) wp_json_encode( $capabilities ) );
}

/**
 * Check if the capabilities hash has changed.
 *
 * @param string $current_hash Current hash of the capabilities array.
 * @return bool True if the hash has changed, false otherwise.
 */
function has_capabilities_hash_changed( $current_hash ) {
	$stored_hash = get_option( 'wpgraphql_ide_capabilities' );
	return $current_hash !== $stored_hash;
}

/**
 * Update the capabilities for the specified roles.
 *
 * @param array<string,mixed> $capabilities Array of capabilities and roles.
 */
function update_roles_capabilities( $capabilities ): void {
	foreach ( $capabilities as $capability => $roles ) {
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );

			if ( $role && ! $role->has_cap( $capability ) ) {
				$role->add_cap( $capability );
			}
		}
	}
}

/**
 * Save the new capabilities hash in the options table.
 *
 * @param string $current_hash Current hash of the capabilities array.
 */
function save_capabilities_hash( $current_hash ): void {
	update_option( 'wpgraphql_ide_capabilities', $current_hash );
}

/**
 * Checks if the current user has the capability required to load scripts and styles for the GraphQL IDE.
 *
 * @return bool Whether the user has the required capability.
 */
function user_has_graphql_ide_capability(): bool {
	$capability_required = apply_filters( 'wpgraphql_ide_capability_required', 'manage_graphql_ide' );

	return current_user_can( $capability_required );
}

/**
 * Determines if the current admin page is a dedicated WPGraphQL IDE page.
 *
 * @return bool True if the current page is a dedicated WPGraphQL IDE page, false otherwise.
 */
function current_screen_is_dedicated_ide_page(): bool {
	return is_ide_page() || is_legacy_ide_page();
}

/**
 * Checks if the current admin page is the new WPGraphQL IDE page.
 *
 * @return bool True if the current page is the new WPGraphQL IDE page, false otherwise.
 */
function is_ide_page(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();
	if ( ! ( $screen instanceof \WP_Screen ) ) {
		return false;
	}

	return 'graphql_page_graphql-ide' === $screen->id;
}

/**
 * Checks if the current admin page is the legacy GraphiQL IDE page.
 *
 * @return bool True if the current page is the legacy GraphiQL IDE page, false otherwise.
 */
function is_legacy_ide_page(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();
	if ( ! ( $screen instanceof \WP_Screen ) ) {
		return false;
	}

	return 'toplevel_page_graphiql-ide' === $screen->id;
}

/**
 * Registers the plugin's custom menu item in the WordPress Admin Bar.
 *
 * @global WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar instance.
 */
function register_wpadminbar_menus(): void {
	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	global $wp_admin_bar;

	$app_context = get_app_context();

	// Retrieve the settings array
	$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );

	// Get the specific link behavior value, default to 'drawer' if not set
	$link_behavior = isset( $graphql_ide_settings['graphql_ide_link_behavior'] ) ? $graphql_ide_settings['graphql_ide_link_behavior'] : 'drawer';

	if ( 'drawer' === $link_behavior && ! current_screen_is_dedicated_ide_page() ) {
		// Drawer Button
		$wp_admin_bar->add_node(
			[
				'id'    => 'wpgraphql-ide',
				'title' => '<div id="' . esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"><span class="ab-icon"></span>' . esc_html( $app_context['drawerButtonLabel'] ) . '</div>',
				'href'  => '#',
			]
		);
	} elseif ( 'disabled' !== $link_behavior ) {
		// Link to the new dedicated IDE page.
		$wp_admin_bar->add_node(
			[
				'id'    => 'wpgraphql-ide',
				'title' => '<span class="ab-icon"></span>' . esc_html( $app_context['drawerButtonLabel'] ),
				'href'  => esc_url( admin_url( 'admin.php?page=graphql-ide' ) ),
			]
		);
	}
}

/**
 * Registers a submenu page for the dedicated GraphQL IDE and reorder the items.
 *
 * @see add_submenu_page() For more information on adding submenu pages.
 * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
 */
function register_dedicated_ide_menu(): void {
	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	// Remove the legacy submenu without affecting the ability to directly link to the legacy IDE (wp-admin/admin.php?page=graphiql-ide)
	$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
	$show_legacy_editor   = isset( $graphql_ide_settings['graphql_ide_show_legacy_editor'] ) ? $graphql_ide_settings['graphql_ide_show_legacy_editor'] : 'off';

	if ( 'off' === $show_legacy_editor ) {
		remove_submenu_page( 'graphiql-ide', 'graphiql-ide' );
	}

	add_submenu_page(
		'graphiql-ide',
		esc_html__( 'GraphQL IDE', 'wpgraphql-ide' ),
		esc_html__( 'GraphQL IDE', 'wpgraphql-ide' ),
		'manage_graphql_ide',
		'graphql-ide',
		__NAMESPACE__ . '\\render_dedicated_ide_page'
	);

	// Reorder the submenu items.
	add_action( 'admin_menu', __NAMESPACE__ . '\\reorder_graphql_submenu_items', 100 );
}

/**
 * Reorder the submenu items under the GraphQL menu.
 */
function reorder_graphql_submenu_items(): void {
	global $submenu;

	if ( isset( $submenu['graphiql-ide'] ) ) {
		$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
		$show_legacy_editor   = isset( $graphql_ide_settings['graphql_ide_show_legacy_editor'] ) ? $graphql_ide_settings['graphql_ide_show_legacy_editor'] : 'off';

		// Extract known submenu items and preserve unknown 3rd-party items.
		$graphql_ide  = null;
		$graphiql_ide = null;
		$extensions   = null;
		$settings     = null;
		$other_items  = [];

		foreach ( $submenu['graphiql-ide'] as $item ) {
			switch ( $item[0] ) {
				case 'GraphQL IDE':
					$graphql_ide = $item;
					break;
				case 'GraphiQL IDE': // Legacy menu item.
					$graphiql_ide = $item;
					break;
				case 'Extensions':
					$extensions = $item;
					break;
				case 'Settings':
					$settings = $item;
					break;
				default:
					// Preserve 3rd-party submenu items.
					$other_items[] = $item;
					break;
			}
		}

		// Create the reordered submenu array.
		$ordered_submenu = [];

		if ( $graphql_ide ) {
			$ordered_submenu[] = $graphql_ide;
		}
		if ( 'on' === $show_legacy_editor && $graphiql_ide ) {
			$graphiql_ide[0]   = esc_html__( 'Legacy GraphQL IDE', 'wpgraphql-ide' );
			$ordered_submenu[] = $graphiql_ide;
		}
		if ( $extensions ) {
			$ordered_submenu[] = $extensions;
		}
		if ( $settings ) {
			$ordered_submenu[] = $settings;
		}

		// Append 3rd-party submenu items after our known items.
		foreach ( $other_items as $item ) {
			$ordered_submenu[] = $item;
		}

		// Merge the reordered submenu back into the global $submenu.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu['graphiql-ide'] = $ordered_submenu;
	}
}

/**
 * Renders the container for the dedicated IDE page for the React app to be mounted to.
 */
function render_dedicated_ide_page(): void {
	echo '<div id="' . esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"></div>';
}

/**
 * Enqueues custom CSS to set the "GraphQL IDE" menu item icon in the WordPress Admin Bar.
 */
function enqueue_graphql_ide_menu_icon_css(): void {
	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	$custom_css = '
        #wp-admin-bar-wpgraphql-ide .ab-icon::before,
        #wp-admin-bar-wpgraphql-ide .ab-icon::before {
            background-image: url("data:image/svg+xml;base64,' . base64_encode( graphql_logo_svg() ) . '");
            background-size: 100%;
            border-radius: 12px;
            box-sizing: border-box;
            content: "";
            display: inline-block;
            height: 24px;
            width: 24px;
        }
    ';

	wp_add_inline_style( 'admin-bar', wp_kses_post( $custom_css ) );
}

/**
 * Enqueues the React application script and associated styles.
 *
 * @param bool $bypass_dedicated_page_gate When true, skip the
 *                                          capability + admin-bar gates
 *                                          that normally restrict this
 *                                          to logged-in IDE users on
 *                                          the right pages. The
 *                                          public-endpoint render passes
 *                                          true here because it serves
 *                                          anonymous visitors by design.
 */
function enqueue_react_app_with_styles( bool $bypass_dedicated_page_gate = false ): void {
	if ( is_legacy_ide_page() ) {
		return;
	}

	if ( ! class_exists( '\WPGraphQL\Router' ) ) {
		return;
	}

	if ( ! $bypass_dedicated_page_gate ) {
		if ( ! user_has_graphql_ide_capability() ) {
			return;
		}

		// On frontend, only enqueue if admin bar is showing
		if ( ! is_admin() ) {
			if ( ! is_admin_bar_showing() ) {
				return;
			}
		}
	}

	// Don't enqueue new styles/scripts on the legacy IDE page
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		
		if ( $screen instanceof \WP_Screen && 'toplevel_page_graphiql-ide' === $screen->id ) {
			return;
		}
	}

	// Check if build assets exist before including them.
	// Build assets are generated by `npm run build` and may not exist in development.
	$asset_file_path         = WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'build/wpgraphql-ide.asset.php';
	$render_asset_file_path  = WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'build/wpgraphql-ide-render.asset.php';
	$graphql_asset_file_path = WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'build/graphql.asset.php';

	// Bail early if required build assets don't exist.
	if ( ! file_exists( $asset_file_path ) || ! file_exists( $render_asset_file_path ) || ! file_exists( $graphql_asset_file_path ) ) {
		return;
	}

	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is validated with file_exists() above
	$asset_file = include $asset_file_path;
	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is validated with file_exists() above
	$render_asset_file = include $render_asset_file_path;
	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is validated with file_exists() above
	$graphql_asset_file = include $graphql_asset_file_path;

	$app_context = get_app_context();

	wp_register_script(
		'graphql',
		plugins_url( 'build/graphql.js', __FILE__ ),
		$graphql_asset_file['dependencies'],
		$graphql_asset_file['version'],
		true // Load in footer
	);

	wp_enqueue_script(
		'wpgraphql-ide',
		plugins_url( 'build/wpgraphql-ide.js', __FILE__ ),
		array_merge( $asset_file['dependencies'], [ 'graphql' ] ),
		$asset_file['version'],
		true
	);

	$user_id                 = get_current_user_id();
	$collapsed_notices       = get_user_meta( $user_id, 'wpgraphql_ide_collapsed_notices', true );
	$personal_collections    = get_user_meta( $user_id, 'wpgraphql_ide_personal_collections', true );
	$seen_shared_collections = get_user_meta( $user_id, 'wpgraphql_ide_seen_shared_collections', true );
	$shared_collections      = \WPGraphQLIDE\UserMeta::aggregate_shared_collections();

	// Decode the JSON-string blob into an array for the bootstrap so
	// the client doesn't have to parse on every read. An invalid /
	// missing value falls back to an empty object so the JS hydrator
	// always sees a stable shape.
	$section_states_raw = get_user_meta( $user_id, 'wpgraphql_ide_section_states', true );
	$section_states     = is_string( $section_states_raw ) && '' !== $section_states_raw
		? json_decode( $section_states_raw, true )
		: [];
	if ( ! is_array( $section_states ) ) {
		$section_states = [];
	}

	$localized_data = [
		'nonce'                 => wp_create_nonce( 'wp_rest' ),
		'restUrl'               => esc_url_raw( rest_url() ),
		'graphqlEndpoint'       => trailingslashit( site_url() ) . 'index.php?' . \WPGraphQL\Router::$route,
		'rootElementId'         => WPGRAPHQL_IDE_ROOT_ELEMENT_ID,
		'context'               => $app_context,
		'isDedicatedIdePage'    => current_screen_is_dedicated_ide_page(),
		'dedicatedIdeBaseUrl'   => get_dedicated_ide_base_url(),
		'collapsedNotices'      => is_array( $collapsed_notices ) ? $collapsed_notices : [],
		'personalCollections'   => is_array( $personal_collections ) ? $personal_collections : [],
		'sharedCollections'     => $shared_collections,
		'seenSharedCollections' => is_array( $seen_shared_collections ) ? $seen_shared_collections : [],
		'sectionStates'         => empty( $section_states ) ? new \stdClass() : $section_states,
		// `manage_graphql_ide` is the gate for using the IDE at all, but
		// the share dialog needs to search and resolve other users — that
		// requires `list_users`, which is a strict subset of editor and
		// administrator. Surface it as a separate flag so the client can
		// hide the share affordance for IDE users who can't enumerate
		// other users (e.g. authors granted IDE access via a custom cap).
		'capabilities'          => [
			'listUsers' => current_user_can( 'list_users' ),
		],
	];

	/**
	 * Allow internal modules and external extensions to inject keys into the
	 * IDE's bootstrap data (window.WPGRAPHQL_IDE_DATA).
	 *
	 * @param array<string,mixed> $localized_data The bootstrap data being passed to the IDE.
	 * @param array<string,mixed> $app_context    The current app context.
	 */
	$localized_data = apply_filters( 'wpgraphql_ide_localized_data', $localized_data, $app_context );

	$escaped_data = wp_localize_escaped_data( $localized_data );

	wp_localize_script(
		'wpgraphql-ide',
		'WPGRAPHQL_IDE_DATA',
		$escaped_data
	);

	// Extensions looking to extend GraphiQL can hook in here,
	// after the window object is established, but before the App renders
	do_action( 'wpgraphql_ide_enqueue_script', $app_context );

	wp_enqueue_script(
		'wpgraphql-ide-render',
		plugins_url( 'build/wpgraphql-ide-render.js', __FILE__ ),
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide', 'graphql' ] ),
		$render_asset_file['version'],
		true
	);

	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style( 'wpgraphql-ide-render', plugins_url( 'build/wpgraphql-ide-render.css', __FILE__ ), [], $render_asset_file['version'] );

	// Avoid running custom styles through a build process for an improved developer experience.
	wp_enqueue_style( 'wpgraphql-ide', plugins_url( 'styles/wpgraphql-ide.css', __FILE__ ), [], $asset_file['version'] );
}

/**
 * Retrieves the base URL for the dedicated WPGraphQL IDE page.
 *
 * @return string The URL for the dedicated IDE page within the WordPress admin.
 */
function get_dedicated_ide_base_url(): string {
	return menu_page_url( 'graphql-ide', false );
}

/**
 * Retrieves the specific header of this plugin.
 *
 * @param string $key The plugin data key.
 * @return string|null The version number of the plugin. Returns null if the version is not found.
 */
function get_plugin_header( string $key = '' ): ?string {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( empty( $key ) ) {
		return null;
	}

	$plugin_data = get_plugin_data( __FILE__ );

	if ( ! is_array( $plugin_data ) ) {
		return null;
	}

	$plugin_header = $plugin_data[ $key ] ?? null;

	return is_string( $plugin_header ) ? $plugin_header : null;
}

/**
 * Retrieves and sanitizes external fragments.
 *
 * @return array<string> The sanitized array of external fragments.
 */
function get_external_fragments(): array {
	// Retrieve external fragments using the filter.
	$external_fragments = apply_filters( 'wpgraphql_ide_external_fragments', [] );

	// Loop through each fragment, sanitize, and ensure it's a valid GraphQL fragment.
	return array_filter(
		array_map( 'sanitize_text_field', $external_fragments ),
		static function ( string $fragment ): bool {
			// Check if the fragment starts with "fragment" and contains "on" (basic GraphQL fragment validation).
			return preg_match( '/^fragment\s+\w+\s+on\s+\w+\s*{/', trim( $fragment ) ) === 1;
		}
	);
}

/**
 * Recursive function to escape an array or value for safe output, specifically for localizing data in WordPress.
 *
 * @param mixed $data The data to escape.
 * @return mixed The escaped data.
 */
function wp_localize_escaped_data( $data ) {
	if ( is_array( $data ) ) {
		return array_map( __NAMESPACE__ . '\wp_localize_escaped_data', $data );
	} elseif ( is_string( $data ) ) {
		// Use wp_kses_post to allow basic HTML for content and esc_url for URLs
		return filter_var( $data, FILTER_VALIDATE_URL ) ? esc_url( $data ) : wp_kses_post( $data );
	} elseif ( is_int( $data ) ) {
		return absint( $data );
	} elseif ( is_bool( $data ) ) {
		return (bool) $data;
	}

	return $data; // Return original value if it's not a string, int, or bool.
}

/**
 * Retrieves app context.
 *
 * @return array<string, mixed> The possibly filtered app context array.
 */
function get_app_context(): array {
	$current_user = wp_get_current_user();

	// Get the avatar URL for the current user. Returns an empty string if no user is logged in.
	$avatar_url = $current_user->exists() ? ( get_avatar_url( $current_user->ID ) ?: '' ) : '';

	$app_context = [
		'pluginVersion'     => get_plugin_header( 'Version' ),
		'pluginName'        => get_plugin_header( 'Name' ),
		'externalFragments' => get_external_fragments(),
		'avatarUrl'         => $avatar_url,
		'drawerButtonLabel' => __( 'GraphQL IDE', 'wpgraphql-ide' ),
		// 0 for anonymous, post id otherwise. localStorage buckets
		// (unsaved tabs, etc) scope by this so signed-in and anonymous
		// state on the same browser don't pollute each other.
		'currentUserId'     => (int) $current_user->ID,
	];

	return apply_filters( 'wpgraphql_ide_context', $app_context );
}

/**
 * Adds styles to hide generic admin notices on the GraphQL IDE page.
 *
 * @param array<int, mixed> $notices The array of notices to render.
 */
function graphql_admin_notices_render_notices( array $notices ): void {
	$custom_css = '
        body.graphql_page_graphql-ide #wpbody .wpgraphql-admin-notice {
            display: block;
            position: absolute;
            top: 0;
            right: 0;
            z-index: 1;
            min-width: 40%;
        }
        body.graphql_page_graphql-ide #wpgraphql-ide-root {
            height: calc(100vh - var(--wp-admin--admin-bar--height) - ' . count( $notices ) * 45 . 'px);
        }
    ';

	/**
	 * Register and enqueue the custom CSS is needed in order to properly add inline styles.
	 * This is needed because of the way graphql_admin_notices_render_notices is called, outside of the normal enqueue process.
	 */
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_register_style( 'wpgraphql-ide-admin-notices', false );
	wp_enqueue_style( 'wpgraphql-ide-admin-notices' );
	wp_add_inline_style( 'wpgraphql-ide-admin-notices', wp_kses_post( $custom_css ) );
}

/**
 * Adds styles to apply top margin to notices added via register_graphql_admin_notice.
 *
 * @param string               $notice_slug The slug of the notice.
 * @param array<string, mixed> $notice The notice data.
 * @param bool                 $is_dismissable Whether the notice is dismissable.
 * @param int                  $count The count of notices.
 */
function graphql_admin_notices_render_notice( string $notice_slug, array $notice, bool $is_dismissable, int $count ): void {
	$custom_css = '
        body.graphql_page_graphql-ide #wpbody #wpgraphql-admin-notice-' . esc_attr( $notice_slug ) . ' {
            top: ' . esc_attr( ( $count * 45 ) . 'px' ) . ';
        }
    ';

	/**
	 * Register and enqueue the custom CSS is needed in order to properly add inline styles.
	 * This is needed because of the way graphql_admin_notices_render_notices is called, outside of the normal enqueue process.
	 */
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_register_style( 'wpgraphql-ide-admin-notice', false );
	wp_enqueue_style( 'wpgraphql-ide-admin-notice' );
	wp_add_inline_style( 'wpgraphql-ide-admin-notice', $custom_css );
}

/**
 * Filters to allow GraphQL admin notices to be displayed on the dedicated IDE page.
 *
 * @param bool               $is_plugin_scoped_page True if the current page is within scope of the plugin's pages.
 * @param string             $current_page_id The ID of the current admin page.
 * @param array<int, string> $allowed_pages The list of allowed pages.
 * @return bool Whether the admin notice is allowed on the current page.
 */
function graphql_admin_notices_is_allowed_admin_page( bool $is_plugin_scoped_page, string $current_page_id, array $allowed_pages ): bool {
	// If the current page is the dedicated IDE page, we want to allow notices to be displayed.
	if ( 'graphql_page_graphql-ide' === $current_page_id ) {
		return true;
	}

	return $is_plugin_scoped_page;
}

/**
 * Modifies the script tag for specific scripts to add the 'defer' attribute.
 *
 * @param string $tag The HTML <script> tag of the enqueued script.
 * @param string $handle The script's registered handle in WordPress.
 * @return string Modified script tag with 'defer' attribute included if handle matches; otherwise, unchanged.
 */
function add_defer_attribute_to_script( string $tag, string $handle ): string {
	if ( 'wpgraphql-ide' === $handle ) {
		return str_replace( ' src', ' defer="defer" src', $tag );
	}

	return $tag;
}

/**
 * Update the existing GraphiQL link field configuration to say "Legacy".
 *
 * @param array<string, mixed> $field_config The field configuration array.
 * @param string               $field_name The name of the field.
 * @param string               $section The section the field belongs to.
 * @return array<string, mixed> The modified field configuration array.
 */
function update_graphiql_link_field_config( array $field_config, string $field_name, string $section ): array {
	if ( 'show_graphiql_link_in_admin_bar' === $field_name && 'graphql_general_settings' === $section ) {
		$field_config['desc'] = sprintf(
			'%1$s<br><p class="description">%2$s</p>',
			__( 'Show the GraphiQL IDE link in the WordPress Admin Bar.', 'wpgraphql-ide' ),
			sprintf(
				/* translators: %s: Strong opening tag */
				__( '%1$sNote:%2$s This setting has been disabled by the new WPGraphQL IDE. Related settings are now available under the "IDE Settings" tab.', 'wpgraphql-ide' ),
				'<strong>',
				'</strong>'
			)
		);
		$field_config['disabled'] = true;
		$field_config['value']    = 'off';
	}
	return $field_config;
}

/**
 * Ensure the `show_graphiql_link_in_admin_bar` setting is always unchecked.
 *
 * @param mixed                $value The value of the field.
 * @param mixed                $default_value The default value if there is no value set.
 * @param string               $option_name The name of the option.
 * @param array<string, mixed> $section_fields The setting values within the section.
 * @param string               $section_name The name of the section the setting belongs to.
 * @return mixed The modified value of the field.
 */
function ensure_graphiql_link_is_unchecked( $value, $default_value, $option_name, $section_fields, $section_name ) {
	if ( 'show_graphiql_link_in_admin_bar' === $option_name && 'graphql_general_settings' === $section_name ) {
		return 'off';
	}
	return $value;
}

/**
 * Registers custom GraphQL settings.
 */
function register_ide_settings(): void {
	// Add a tab section to the GraphQL admin settings page.
	if ( function_exists( 'register_graphql_settings_section' ) ) {
		register_graphql_settings_section(
			'graphql_ide_settings',
			[
				'title' => __( 'IDE Settings', 'wpgraphql-ide' ),
				'desc'  => __( 'Customize your WPGraphQL IDE experience sitewide. Individual users can override these settings in their user profile.', 'wpgraphql-ide' ),
			]
		);
	}

	if ( function_exists( 'register_graphql_settings_field' ) ) {
		register_graphql_settings_field(
			'graphql_ide_settings',
			[
				'name'              => 'graphql_ide_link_behavior',
				'label'             => __( 'Admin Bar Link Behavior', 'wpgraphql-ide' ),
				'desc'              => __( 'How would you like to access the GraphQL IDE from the admin bar?', 'wpgraphql-ide' ),
				'type'              => 'radio',
				'options'           => [
					'drawer'         => __( 'Drawer (recommended) — open the IDE in a slide up drawer from any page', 'wpgraphql-ide' ),
					'dedicated_page' => sprintf(
						wp_kses_post(
							sprintf(
								/* translators: %s: URL to the GraphQL IDE page */
								__( 'Dedicated Page — direct link to <a href="%1$s">%1$s</a>', 'wpgraphql-ide' ),
								esc_url( admin_url( 'admin.php?page=graphql-ide' ) )
							)
						)
					),
					'disabled'       => __( 'Disabled — remove the IDE link from the admin bar', 'wpgraphql-ide' ),
				],
				'default'           => 'drawer',
				'sanitize_callback' => __NAMESPACE__ . '\\sanitize_custom_graphql_ide_link_behavior',
			]
		);

		register_graphql_settings_field(
			'graphql_ide_settings',
			[
				'name'  => 'graphql_ide_show_legacy_editor',
				'label' => __( 'Show Legacy Editor', 'wpgraphql-ide' ),
				'desc'  => __( 'Show the legacy editor', 'wpgraphql-ide' ),
				'type'  => 'checkbox',
			]
		);
	}
}

/**
 * Sanitize the input value for the custom GraphQL IDE link behavior setting.
 *
 * @param string $value The input value.
 * @return string The sanitized value.
 */
function sanitize_custom_graphql_ide_link_behavior( string $value ): string {
	$valid_values = [ 'drawer', 'dedicated_page', 'disabled' ];

	if ( in_array( $value, $valid_values, true ) ) {
		return $value;
	}

	return 'drawer';
}

/**
 * Adds a settings link to the plugin actions.
 *
 * @param array<int, string> $links The existing action links.
 * @return array<int, string> The modified action links.
 */
function add_settings_link( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=graphql-settings#graphql_ide_settings' ) ),
		esc_html__( 'Settings', 'wpgraphql-ide' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}


/**
 * Generates the SVG logo for GraphQL.
 *
 * @return string The SVG logo markup.
 */
function graphql_logo_svg(): string {
	$svg  = '<svg width="36" height="36" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="WPGraphQL">';
	$svg .= '<circle cx="256" cy="256" r="256" fill="#0E1628"></circle>';
	$svg .= '<path d="m117.592 300.896c0-35.138.58-39.429 7.074-52.301 5.682-11.133 20.758-25.05 30.732-28.065 2.203-.696 2.899.348 6.726 9.858 12.408 31.195 37.11 54.505 69.349 65.29l8.465 2.899.348 16.815c.116 9.394-.116 16.932-.58 16.816-.58 0-2.899-3.131-5.45-6.958-11.945-18.671-35.718-30.036-59.724-28.645-21.802 1.276-40.589 12.061-52.765 30.152l-4.175 6.147zm25.165 85.353c10.09-3.015 17.743-13.568 17.743-24.47 0-7.77 9.51-16.699 17.627-16.699 10.321 0 17.396 6.958 18.787 18.44 1.276 10.32 5.567 16.815 14.032 21.337 4.407 2.436 6.147 2.552 32.471 2.552 26.441 0 28.065-.116 32.588-2.552 5.566-3.015 11.712-9.51 12.872-14.032.58-1.74.928-25.049.928-51.838v-48.706l-2.9-5.103c-4.87-8.582-10.437-11.597-24.469-13.452-19.019-2.436-30.036-7.538-41.053-18.787-8.117-8.118-14.96-21.57-16.815-33.051-3.71-21.918 7.19-46.503 26.325-59.26 11.48-7.654 20.526-10.437 33.979-10.437 8.813 0 12.64.58 19.25 2.9 14.728 5.218 25.745 14.031 33.515 27.02 8.234 13.916 8.002 10.205 8.698 94.514.58 68.885.928 76.539 2.783 82.337 6.146 19.02 18.903 34.559 34.443 42.097 21.338 10.437 42.212 11.133 60.767 2.087 19.019-9.393 33.747-30.615 37.69-54.389 2.435-14.612-1.16-23.193-11.83-28.528-10.32-5.219-21.917-3.827-29.107 3.479-4.639 4.639-6.262 8.118-8.234 17.86-2.551 12.06-8.118 17.394-18.323 17.394-6.378 0-12.524-3.247-15.424-8.233-2.203-3.827-2.319-6.61-2.899-78.743-.58-66.566-.812-75.727-2.667-82.801-12.409-47.895-49.403-80.366-98.69-86.513-24.584-3.015-56.94 6.843-78.858 24.354-17.627 13.916-29.108 30.615-36.53 52.997l-3.479 9.974-11.944 4.29c-19.02 6.727-28.645 12.641-42.909 26.441-12.872 12.525-21.802 26.441-27.6 43.14-5.335 15.772-5.799 21.339-5.799 75.844v51.374l2.668 5.102c3.015 5.683 10.089 11.25 16.003 12.64 2.204.465 14.38.929 27.253 1.044 17.511.116 24.701-.347 29.108-1.623zm132.204-172.793c6.03-2.551 8.35-4.87 11.48-11.597 4.523-9.625 3.248-20.526-3.362-28.064-4.755-5.45-9.51-7.306-18.555-7.306-6.03 0-8.234.58-12.64 3.363-15.077 9.51-14.265 34.79 1.39 42.792 6.147 3.016 15.425 3.363 21.687.812z" fill="#FF8C1A" fill-rule="nonzero"></path>';
	$svg .= '</svg>';

	return $svg;
}


/**
 * Register custom REST routes for the IDE.
 *
 * @return void
 */
function register_ide_rest_routes() {
	register_rest_route(
		'wpgraphql-ide/v1',
		'/documents/(?P<id>\d+)/publish',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\\handle_publish_document',
			'permission_callback' => static function () {
				return current_user_can( 'manage_graphql_ide' );
			},
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => static function ( $param ) {
						return is_numeric( $param );
					},
				],
			],
		]
	);

	register_rest_route(
		'wpgraphql-ide/v1',
		'/collections/(?P<id>\d+)/cascade',
		[
			'methods'             => 'DELETE',
			'callback'            => __NAMESPACE__ . '\\handle_delete_collection_cascade',
			'permission_callback' => static function () {
				return current_user_can( 'manage_graphql_ide' );
			},
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => static function ( $param ) {
						return is_numeric( $param );
					},
				],
			],
		]
	);

	register_rest_route(
		'wpgraphql-ide/v1',
		'/documents/export',
		[
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\\handle_export_documents',
			'permission_callback' => static function () {
				return current_user_can( 'manage_graphql_ide' );
			},
		]
	);

	register_rest_route(
		'wpgraphql-ide/v1',
		'/documents/import',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\\handle_import_documents',
			'permission_callback' => static function () {
				return current_user_can( 'manage_graphql_ide' );
			},
		]
	);

	register_rest_route(
		'wpgraphql-ide/v1',
		'/documents/reorder',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\\handle_reorder_documents',
			'permission_callback' => static function () {
				return current_user_can( 'manage_graphql_ide' );
			},
		]
	);

	register_rest_route(
		'wpgraphql-ide/v1',
		'/collections/reorder',
		[
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\\handle_reorder_collections',
			'permission_callback' => static function () {
				return current_user_can( 'manage_graphql_ide' );
			},
		]
	);
}

/**
 * Export the current user's documents grouped by collection. Returns
 * the same JSON shape used by `seeds/example-documents.json` and
 * accepted by the importer.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response
 */
function handle_export_documents( \WP_REST_Request $request ) {
	return rest_ensure_response( \WPGraphQLIDE\ImportExport::export( get_current_user_id() ) );
}

/**
 * Import a documents JSON payload into the current user's library.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response|\WP_Error
 */
function handle_import_documents( \WP_REST_Request $request ) {
	$body = $request->get_json_params();
	if ( ! is_array( $body ) || empty( $body['collections'] ) ) {
		return new \WP_Error(
			'invalid_payload',
			__( 'Import payload must be an object with a non-empty "collections" array.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}

	$result = \WPGraphQLIDE\ImportExport::import( $body, get_current_user_id() );
	return rest_ensure_response( $result );
}

/**
 * Persist a reorder of documents — sets `menu_order` for each post in
 * the order provided. The post type's `page-attributes` support
 * surfaces `menu_order` to WP REST and to the default `WP_Query` sort.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function handle_reorder_documents( \WP_REST_Request $request ) {
	$body  = $request->get_json_params();
	$order = isset( $body['order'] ) && is_array( $body['order'] ) ? $body['order'] : null;
	if ( ! $order ) {
		return new \WP_Error(
			'invalid_payload',
			__( 'Reorder payload must include an "order" array of post IDs.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}
	$author_id = get_current_user_id();
	foreach ( $order as $position => $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		// Only touch posts the user owns and that match our CPT.
		if ( ! $post || 'graphql_ide_query' !== $post->post_type || (int) $post->post_author !== $author_id ) {
			continue;
		}
		wp_update_post(
			[
				'ID'         => $post_id,
				'menu_order' => (int) $position,
			]
		);
	}
	return rest_ensure_response( [ 'ok' => true ] );
}

/**
 * Persist a reorder of collections per-user via term meta. Collection
 * order is user-scoped because terms themselves are global; per-user
 * ordering keeps the IDE feeling personal without leaking another
 * user's preferred order onto everyone.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function handle_reorder_collections( \WP_REST_Request $request ) {
	$body  = $request->get_json_params();
	$order = isset( $body['order'] ) && is_array( $body['order'] ) ? $body['order'] : null;
	if ( ! $order ) {
		return new \WP_Error(
			'invalid_payload',
			__( 'Reorder payload must include an "order" array of term IDs.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}
	$ids = array_values( array_filter( array_map( 'intval', $order ) ) );
	update_user_meta( get_current_user_id(), 'wpgraphql_ide_collection_order', $ids );
	return rest_ensure_response( [ 'ok' => true ] );
}

/**
 * Delete a collection along with all documents in it owned by the
 * current user. Documents owned by other users are left intact —
 * removing a shared term's assignment is enough to detach them.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function handle_delete_collection_cascade( \WP_REST_Request $request ) {
	$term_id = (int) $request->get_param( 'id' );
	$term    = get_term( $term_id, 'graphql_ide_collection' );

	if ( ! $term || is_wp_error( $term ) ) {
		return new \WP_Error(
			'not_found',
			__( 'Collection not found.', 'wpgraphql-ide' ),
			[ 'status' => 404 ]
		);
	}

	$user_id  = get_current_user_id();
	$post_ids = get_posts(
		[
			'post_type'      => 'graphql_ide_query',
			'post_status'    => [ 'draft', 'publish' ],
			'author'         => $user_id,
			'tax_query'      => [
				[
					'taxonomy' => 'graphql_ide_collection',
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]
	);

	$deleted = [];
	foreach ( $post_ids as $post_id ) {
		if ( wp_delete_post( (int) $post_id, true ) ) {
			$deleted[] = (int) $post_id;
		}
	}

	$result = wp_delete_term( $term_id, 'graphql_ide_collection' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response(
		[
			'collection_id'    => $term_id,
			'deleted_post_ids' => $deleted,
		]
	);
}

/**
 * Publish a draft document.
 *
 * Computes the SHA-256 hash of the AST-normalized query (matching
 * Smart Cache's algorithm), sets it as the post slug, and changes
 * the status to publish. If a published document with the same hash
 * already exists, returns the existing document instead.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error Response.
 */
function handle_publish_document( \WP_REST_Request $request ) {
	$post_id = (int) $request->get_param( 'id' );
	$post    = get_post( $post_id );

	if ( ! $post || 'graphql_ide_query' !== $post->post_type ) {
		return new \WP_Error(
			'not_found',
			__( 'Document not found.', 'wpgraphql-ide' ),
			[ 'status' => 404 ]
		);
	}

	// Ensure the current user owns this document.
	if ( get_current_user_id() !== (int) $post->post_author ) {
		return new \WP_Error(
			'forbidden',
			__( 'You do not have permission to publish this document.', 'wpgraphql-ide' ),
			[ 'status' => 403 ]
		);
	}

	$query_string = $post->post_content;

	if ( empty( trim( $query_string ) ) ) {
		return new \WP_Error(
			'empty_query',
			__( 'Cannot publish an empty document.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}

	try {
		// Parse and normalize the query using graphql-php (same as Smart Cache).
		$ast        = \GraphQL\Language\Parser::parse( $query_string );
		$normalized = \GraphQL\Language\Printer::doPrint( $ast );
		$hash       = hash( 'sha256', $normalized );
	} catch ( \GraphQL\Error\SyntaxError $e ) {
		return new \WP_Error(
			'invalid_query',
			sprintf(
				// translators: %s is the syntax error message.
				__( 'Invalid GraphQL query: %s', 'wpgraphql-ide' ),
				$e->getMessage()
			),
			[ 'status' => 400 ]
		);
	}

	// Check if a published document with this hash already exists.
	$existing = get_posts(
		[
			'post_type'   => 'graphql_ide_query',
			'post_status' => 'publish',
			'name'        => $hash,
			'numberposts' => 1,
		]
	);

	if ( ! empty( $existing ) ) {
		// Document already published — return the existing one.
		$existing_post = $existing[0];
		return rest_ensure_response(
			[
				'id'             => $existing_post->ID,
				'status'         => $existing_post->post_status,
				'query_hash'     => $hash,
				'already_exists' => true,
				'message'        => __( 'This query is already published.', 'wpgraphql-ide' ),
			]
		);
	}

	// Publish: normalize content, set slug to hash, change status.
	$result = wp_update_post(
		[
			'ID'           => $post_id,
			'post_content' => $normalized,
			'post_name'    => $hash,
			'post_status'  => 'publish',
		],
		true
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response(
		[
			'id'         => $post_id,
			'status'     => 'publish',
			'query_hash' => $hash,
		]
	);
}


\WPGraphQLIDE\Telemetry::init();
