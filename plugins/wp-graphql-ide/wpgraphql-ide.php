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
 * Domain Path:       /languages
 * Version:           4.5.0
 * Requires at least: 6.1
 * Tested up to:      7.0
 * Requires PHP:      7.4
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

define( 'WPGRAPHQL_IDE_VERSION', '4.5.0' );
define( 'WPGRAPHQL_IDE_ROOT_ELEMENT_ID', 'wpgraphql-ide-root' );
define( 'WPGRAPHQL_IDE_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPGRAPHQL_IDE_PLUGIN_FILE', __FILE__ );

/**
 * Manual PSR-4 autoloader for the `WPGraphQLIDE\` namespace.
 *
 * Composer's autoloader does the same thing once `composer install` has
 * been run — but cross-plugin CI jobs only run composer install in their
 * own plugin's directory, so when wp-env loads the IDE alongside (e.g.)
 * smart-cache integration tests, the IDE's `vendor/` doesn't exist and
 * every classed-out `\WPGraphQLIDE\Foo::method()` call fatals before
 * the request can render. Same risk for Bedrock-style installs that
 * skip per-plugin composer.
 *
 * Registering this fallback is harmless when Composer's autoloader has
 * already loaded — the SPL chain just falls through to it for any
 * non-IDE class.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPGraphQLIDE\\';
		$len    = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}
		$relative = substr( $class, $len );
		$file     = WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is constrained by the prefix check + file_exists guard above; mapping a class name to its PSR-4 file is intrinsically variable.
			require_once $file;
		}
	}
);

// Modular feature includes — kept out of this main plugin file to avoid
// further bloat. Each include hooks into WordPress on its own.
require_once __DIR__ . '/includes/access-functions.php';
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
	add_action( 'init', [ \WPGraphQLIDE\UserMeta::class, 'register' ] );

	// Bridge Smart Cache's primitives (graphql_document + 4 taxonomies)
	// into REST exposure the IDE's JS client needs. No-op without Smart
	// Cache. Wire the filters now — before Smart Cache's `init` priority
	// 10 fires register_post_type / register_taxonomy.
	\WPGraphQLIDE\SmartCacheBridge::register();
	add_action( 'admin_menu', [ \WPGraphQLIDE\AdminUI::class, 'register_dedicated_ide_menu' ] );
	add_action( 'admin_bar_menu', [ \WPGraphQLIDE\AdminUI::class, 'register_wpadminbar_menus' ], 999 );
	add_action( 'admin_enqueue_scripts', [ \WPGraphQLIDE\AdminUI::class, 'enqueue_graphql_ide_menu_icon_css' ] );
	add_action( 'wp_enqueue_scripts', [ \WPGraphQLIDE\AdminUI::class, 'enqueue_graphql_ide_menu_icon_css' ] );
	// Enqueue scripts on both admin and frontend since admin bar appears on both
	add_action( 'admin_enqueue_scripts', [ \WPGraphQLIDE\AssetEnqueue::class, 'enqueue' ] );
	add_action( 'wp_enqueue_scripts', [ \WPGraphQLIDE\AssetEnqueue::class, 'enqueue' ] );

	add_action( 'graphql_register_settings', [ \WPGraphQLIDE\SettingsPage::class, 'register' ] );
	add_action( 'graphql_admin_notices_render_notices', [ \WPGraphQLIDE\AdminUI::class, 'graphql_admin_notices_render_notices' ], 10, 1 );
	add_action( 'graphql_admin_notices_render_notice', [ \WPGraphQLIDE\AdminUI::class, 'graphql_admin_notices_render_notice' ], 10, 4 );

	add_filter( 'graphql_admin_notices_is_allowed_admin_page', [ \WPGraphQLIDE\AdminUI::class, 'graphql_admin_notices_is_allowed_admin_page' ], 10, 3 );
	add_filter( 'script_loader_tag', [ \WPGraphQLIDE\AssetEnqueue::class, 'defer_script_attribute' ], 10, 2 );
	add_filter( 'graphql_setting_field_config', [ \WPGraphQLIDE\SettingsPage::class, 'rewrite_legacy_graphiql_link' ], 10, 3 );
	add_filter( 'graphql_get_setting_section_field_value', [ \WPGraphQLIDE\SettingsPage::class, 'force_legacy_graphiql_off' ], 10, 5 );
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ \WPGraphQLIDE\AdminUI::class, 'add_settings_link' ] );

	// Scope REST queries to the current user's own documents.
	// `graphql_document` is Smart Cache's saved-document post type — the
	// filter no-ops when SC isn't installed (the hook simply never fires).
	add_filter( 'rest_graphql_document_query', [ \WPGraphQLIDE\Access::class, 'scope_rest_queries' ] );

	// Enforce manage_graphql_ide capability on all IDE REST routes.
	add_filter( 'rest_pre_dispatch', [ \WPGraphQLIDE\Access::class, 'enforce_rest_permissions' ], 10, 3 );

	// Prevent access to documents owned by other users on single routes.
	add_filter( 'rest_prepare_graphql_document', [ \WPGraphQLIDE\Access::class, 'restrict_document_response' ], 10, 3 );

	// Cap document title length on every write path so a long POST body
	// can't bloat the DB or break admin-UI layouts. Covers REST creates
	// and updates, the import/upsert flow, and any future direct
	// `wp_insert_post` callers.
	add_filter( 'wp_insert_post_data', [ \WPGraphQLIDE\Access::class, 'cap_document_title_length' ], 10, 2 );

	// Custom REST routes.
	add_action( 'rest_api_init', [ \WPGraphQLIDE\Rest::class, 'register' ] );

	// GraphQL: scope Smart Cache `graphqlDocument` connections to the
	// current user so the IDE's data is queryable from GraphQL but
	// isolated per user — same contract as the REST endpoints. The
	// `graphql_data_is_private` filter closes the single-node lookup
	// hole left by the connection-only filter: without it,
	// `node(id: "...")` could resolve another user's document if the
	// requester knew its global ID.
	add_filter( 'graphql_connection_query_args', [ \WPGraphQLIDE\Access::class, 'scope_graphql_connections' ], 10, 2 );
	add_filter( 'graphql_data_is_private', [ \WPGraphQLIDE\Access::class, 'restrict_post_visibility' ], 10, 6 );

	// Strip a deleted document's id from its owner's personal collections.
	add_action( 'before_delete_post', [ \WPGraphQLIDE\UserMeta::class, 'purge_document_from_personal_collections' ], 10, 2 );

	// Core plugins/modules.
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/query-composer-panel/query-composer-panel.php';
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/help-panel/help-panel.php';
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/smart-cache-panel/smart-cache-panel.php';
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
 */
function wpgraphql_ide_activate(): void {
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'manage_graphql_ide' );
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\wpgraphql_ide_activate' );


/**
 * Adds custom capabilities to specified roles.
 *
 * Runs on every `plugins_loaded` (not just activation) so the cap is granted
 * for installs that never fire the activation hook — must-use plugins,
 * Composer/bootstrap loads, or sites where the plugin is force-activated.
 *
 * The stored hash is only a fast-path to skip the role writes when nothing has
 * changed. We deliberately do NOT trust it on its own: a role can lose the cap
 * after the hash is saved (role reset/migration, multisite role sync, a manual
 * edit), and a hash-only guard would then leave administrators permanently
 * without `manage_graphql_ide`. So we also re-apply whenever a target role is
 * actually missing its cap, which makes this self-healing and idempotent.
 */
function add_custom_capabilities(): void {
	$capabilities = get_custom_capabilities();
	$current_hash = generate_capabilities_hash( $capabilities );

	// Skip only when the definition is unchanged AND every role already holds
	// its cap. Either condition failing means we (re)apply.
	if ( ! has_capabilities_hash_changed( $current_hash ) && capabilities_are_applied( $capabilities ) ) {
		return;
	}

	update_roles_capabilities( $capabilities );
	save_capabilities_hash( $current_hash );
}

/**
 * Whether every role already holds each of its declared capabilities.
 *
 * @since x-release-please-version
 *
 * @param array<string,string[]> $capabilities Map of capability => role slugs.
 * @return bool True only if all roles exist and already have their caps.
 */
function capabilities_are_applied( array $capabilities ): bool {
	foreach ( $capabilities as $capability => $roles ) {
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role instanceof \WP_Role || ! $role->has_cap( $capability ) ) {
				return false;
			}
		}
	}

	return true;
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
 * Back-compat wrapper around {@see wpgraphql_ide_user_can()} — the global-
 * namespace helper is the single source of truth and is what new code
 * should call directly.
 *
 * @return bool Whether the user has the required capability.
 */
function user_has_graphql_ide_capability(): bool {
	return wpgraphql_ide_user_can();
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

\WPGraphQLIDE\Telemetry::init();
