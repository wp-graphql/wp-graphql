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
	add_action( 'init', __NAMESPACE__ . '\\register_ide_post_type' );
	add_action( 'init', __NAMESPACE__ . '\\register_ide_user_meta' );
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
	add_filter( 'rest_graphql_ide_query_query', __NAMESPACE__ . '\\scope_ide_queries_to_current_user' );
	add_filter( 'rest_graphql_ide_history_query', __NAMESPACE__ . '\\scope_ide_queries_to_current_user' );

	// Enforce manage_graphql_ide capability on all IDE REST routes.
	add_filter( 'rest_pre_dispatch', __NAMESPACE__ . '\\enforce_ide_rest_permissions', 10, 3 );

	// Prevent access to documents/history owned by other users on single routes.
	add_filter( 'rest_prepare_graphql_ide_query', __NAMESPACE__ . '\\restrict_document_to_author', 10, 3 );
	add_filter( 'rest_prepare_graphql_ide_history', __NAMESPACE__ . '\\restrict_document_to_author', 10, 3 );

	// Cap document title length on every write path so a long POST body
	// can't bloat the DB or break admin-UI layouts. Covers REST creates
	// and updates, the import/upsert flow, and any future direct
	// `wp_insert_post` callers.
	add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\cap_ide_document_title_length', 10, 2 );

	// Custom REST routes.
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_ide_rest_routes' );

	// GraphQL: register IDE-specific fields (meta) on the exposed types
	// and scope connections to the current user so the IDE's data is
	// queryable from GraphQL but isolated per user — same contract as
	// the REST endpoints. The `graphql_data_is_private` filter closes
	// the single-node lookup hole left by the connection-only filter:
	// without it, `node(id: "...")` could resolve another user's
	// IdeQuery if the requester knew its global ID.
	add_action( 'graphql_register_types', __NAMESPACE__ . '\\register_ide_graphql_fields' );
	add_filter( 'graphql_connection_query_args', __NAMESPACE__ . '\\scope_ide_graphql_connections_to_current_user', 10, 2 );
	add_filter( 'graphql_data_is_private', __NAMESPACE__ . '\\restrict_ide_post_visibility_to_author', 10, 6 );

	// Strip a deleted document's id from its owner's personal collections.
	add_action( 'before_delete_post', __NAMESPACE__ . '\\purge_document_from_personal_collections', 10, 2 );

	// Core plugins/modules.
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/query-composer-panel/query-composer-panel.php';
	require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/help-panel/help-panel.php';
}
add_action( 'wpgraphql_ide_init', __NAMESPACE__ . '\\initialize_plugin' );

/**
 * Register the IDE query document custom post type.
 *
 * Each document stores a GraphQL query, its variables, and headers.
 * Documents are scoped to the authoring user via REST API filters.
 */
function register_ide_post_type(): void {
	register_post_type(
		'graphql_ide_query',
		[
			'label'               => __( 'IDE Queries', 'wpgraphql-ide' ),
			'description'         => __( 'Saved GraphQL IDE query documents.', 'wpgraphql-ide' ),
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => true,
			'rest_base'           => 'graphql-ide-queries',
			// Expose to WPGraphQL so the IDE (and integrations that
			// follow) can read saved queries through the GraphQL
			// surface instead of REST. A scoping filter on
			// `graphql_connection_query_args` enforces per-user
			// ownership so this isn't a leak of other users' data.
			'show_in_graphql'     => true,
			'graphql_single_name' => 'IdeQuery',
			'graphql_plural_name' => 'IdeQueries',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'custom-fields', 'page-attributes' ],
		]
	);

	$post_meta_auth = static function () {
		return current_user_can( 'manage_graphql_ide' );
	};

	$sanitize_json = static function ( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		// Validate it's valid JSON if non-empty.
		json_decode( $value );
		return json_last_error() === JSON_ERROR_NONE ? $value : '';
	};

	register_post_meta(
		'graphql_ide_query',
		'_graphql_ide_variables',
		[
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'auth_callback'     => $post_meta_auth,
			'sanitize_callback' => $sanitize_json,
		]
	);

	register_post_meta(
		'graphql_ide_query',
		'_graphql_ide_headers',
		[
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'auth_callback'     => $post_meta_auth,
			'sanitize_callback' => $sanitize_json,
		]
	);

	// History CPT — global execution history, not scoped to a document.
	register_post_type(
		'graphql_ide_history',
		[
			'label'               => __( 'IDE History', 'wpgraphql-ide' ),
			'description'         => __( 'GraphQL IDE execution history entries.', 'wpgraphql-ide' ),
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => true,
			'rest_base'           => 'graphql-ide-history',
			'show_in_graphql'     => true,
			'graphql_single_name' => 'IdeHistoryEntry',
			'graphql_plural_name' => 'IdeHistoryEntries',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => [ 'author', 'custom-fields' ],
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_query',
		[
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => '',
			'auth_callback' => $post_meta_auth,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_variables',
		[
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'auth_callback'     => $post_meta_auth,
			'sanitize_callback' => $sanitize_json,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_headers',
		[
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'auth_callback'     => $post_meta_auth,
			'sanitize_callback' => $sanitize_json,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_duration_ms',
		[
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => 0,
			'auth_callback' => $post_meta_auth,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_status',
		[
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => '',
			'auth_callback' => $post_meta_auth,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_document_id',
		[
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => 0,
			'auth_callback' => $post_meta_auth,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_is_authenticated',
		[
			'type'          => 'boolean',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => true,
			'auth_callback' => $post_meta_auth,
		]
	);

	register_post_meta(
		'graphql_ide_history',
		'_graphql_ide_http_method',
		[
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => 'POST',
			'auth_callback' => $post_meta_auth,
		]
	);

	// Collections taxonomy for grouping saved queries.
	register_taxonomy(
		'graphql_ide_collection',
		'graphql_ide_query',
		[
			'labels'              => [
				'name'          => __( 'Collections', 'wpgraphql-ide' ),
				'singular_name' => __( 'Collection', 'wpgraphql-ide' ),
			],
			'public'              => false,
			'show_in_rest'        => true,
			'rest_base'           => 'graphql-ide-collections',
			'show_in_graphql'     => true,
			'graphql_single_name' => 'IdeCollection',
			'graphql_plural_name' => 'IdeCollections',
			'hierarchical'        => true,
			'show_ui'             => false,
			'show_admin_column'   => false,
			'capabilities'        => [
				'manage_terms' => 'manage_graphql_ide',
				'edit_terms'   => 'manage_graphql_ide',
				'delete_terms' => 'manage_graphql_ide',
				'assign_terms' => 'manage_graphql_ide',
			],
		]
	);
}

/**
 * Register user meta fields for IDE preferences.
 *
 * These are exposed via the REST API so the IDE frontend can
 * read and write user preferences with @wordpress/api-fetch.
 *
 * @return void
 */
function register_ide_user_meta() {
	$auth_callback = static function () {
		return current_user_can( 'manage_graphql_ide' );
	};

	register_meta(
		'user',
		'wpgraphql_ide_theme',
		[
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => static function ( $value ) {
				return in_array( $value, [ '', 'light', 'dark' ], true ) ? $value : '';
			},
		]
	);

	register_meta(
		'user',
		'wpgraphql_ide_persist_headers',
		[
			'type'          => 'boolean',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => false,
			'auth_callback' => $auth_callback,
		]
	);

	register_meta(
		'user',
		'wpgraphql_ide_collection_order',
		[
			'type'          => 'array',
			'single'        => true,
			'show_in_rest'  => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type' => 'integer',
					],
				],
			],
			'default'       => [],
			'auth_callback' => $auth_callback,
		]
	);

	register_meta(
		'user',
		'wpgraphql_ide_collection_sort_modes',
		[
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => [
						'type' => 'string',
						'enum' => [ 'manual', 'title_asc', 'modified_desc', 'status' ],
					],
				],
			],
			'default'       => new \stdClass(),
			'auth_callback' => $auth_callback,
		]
	);

	// Per-user UI state for collapsible sections in the IDE
	// (saved-queries collections, the Documents bucket, Unsaved, etc.).
	//
	// Stored as a JSON-encoded string of an object keyed by section
	// id. Each value is itself an object so we can add per-section
	// fields (lastViewedAt, pinned, sort overrides…) over time without
	// registering a new meta key for every toggle. Schema is opaque to
	// the server — UI owns the shape — so adding a new field on the
	// client doesn't require a server release.
	//
	// { "_documents": { "collapsed": false },
	// "5":          { "collapsed": true },
	// "pc_abc":     { "collapsed": false } }
	//
	// String storage (vs. type=object) sidesteps WP's REST schema
	// validation for arbitrary nested objects, which is finicky with
	// `additionalProperties` and empty defaults.
	register_meta(
		'user',
		'wpgraphql_ide_section_states',
		[
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => [
				'schema' => [
					'type' => 'string',
				],
			],
			'default'       => '{}',
			'auth_callback' => $auth_callback,
		]
	);

	// IDs of personal collections that have been shared with the current
	// user and that the user has already been notified about. Used to
	// avoid re-firing the "X shared a collection with you" snackbar on
	// every page load. Stored as the collection's string id (e.g.
	// `pc_lq2ab3_xyz123`) since that's the stable owner-side identifier.
	register_meta(
		'user',
		'wpgraphql_ide_seen_shared_collections',
		[
			'type'          => 'array',
			'single'        => true,
			'show_in_rest'  => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type' => 'string',
					],
				],
			],
			'default'       => [],
			'auth_callback' => $auth_callback,
		]
	);

	// IDs of document notices the user has collapsed. Notices not present
	// here render expanded. Persisted per-user so a collapsed notice stays
	// collapsed across sessions and devices.
	register_meta(
		'user',
		'wpgraphql_ide_collapsed_notices',
		[
			'type'          => 'array',
			'single'        => true,
			'show_in_rest'  => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type' => 'string',
					],
				],
			],
			'default'       => [],
			'auth_callback' => $auth_callback,
		]
	);

	// Personal collections — per-user grouping of documents that lives
	// in this user's row, separate from the sitewide `graphql_ide_collection`
	// taxonomy. The owner can extend visibility to specific other users via
	// the `shared_with` array; those users get a read-only "Shared with me"
	// view assembled server-side from every IDE user's meta.
	register_meta(
		'user',
		'wpgraphql_ide_personal_collections',
		[
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'id'           => [ 'type' => 'string' ],
							'name'         => [ 'type' => 'string' ],
							'document_ids' => [
								'type'  => 'array',
								'items' => [ 'type' => 'integer' ],
							],
							'shared_with'  => [
								'type'  => 'array',
								'items' => [ 'type' => 'integer' ],
							],
						],
					],
				],
			],
			'default'           => [],
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_personal_collections',
		]
	);
}

/**
 * Sanitize the personal_collections user meta payload.
 *
 * Filters each collection down to a known shape, caps name length, and
 * drops document IDs the current user can't see (via the same author
 * scoping used elsewhere). Shape mismatches are silently dropped rather
 * than raised so the UI stays responsive in the face of stale clients.
 *
 * @param mixed $value Raw value from the REST request.
 * @return array<int, array<string, mixed>>
 */
function sanitize_personal_collections( $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}

	$user_id = get_current_user_id();
	$out     = [];

	foreach ( $value as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$id   = isset( $entry['id'] ) ? (string) $entry['id'] : '';
		$name = isset( $entry['name'] ) ? (string) $entry['name'] : '';

		if ( '' === $id || ! preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $id ) ) {
			continue;
		}

		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			continue;
		}
		if ( strlen( $name ) > 200 ) {
			$name = substr( $name, 0, 200 );
		}

		$ids = [];
		if ( isset( $entry['document_ids'] ) && is_array( $entry['document_ids'] ) ) {
			foreach ( $entry['document_ids'] as $doc_id ) {
				$doc_id = (int) $doc_id;
				if ( $doc_id <= 0 ) {
					continue;
				}
				$post = get_post( $doc_id );
				if ( ! $post || 'graphql_ide_query' !== $post->post_type ) {
					continue;
				}
				if ( (int) $post->post_author !== $user_id ) {
					continue;
				}
				$ids[] = $doc_id;
			}
			$ids = array_values( array_unique( $ids ) );
		}

		$shared = [];
		if ( isset( $entry['shared_with'] ) && is_array( $entry['shared_with'] ) ) {
			foreach ( $entry['shared_with'] as $uid ) {
				$uid = (int) $uid;
				if ( $uid <= 0 || $uid === $user_id ) {
					// Owner doesn't share with themselves; that's implicit.
					continue;
				}
				if ( ! user_can( $uid, 'manage_graphql_ide' ) ) {
					// Can't share with someone who can't use the IDE.
					continue;
				}
				$shared[] = $uid;
			}
			$shared = array_values( array_unique( $shared ) );
		}

		$out[] = [
			'id'           => $id,
			'name'         => $name,
			'document_ids' => $ids,
			'shared_with'  => $shared,
		];
	}

	return $out;
}

/**
 * Build the "Shared with me" view for the current user.
 *
 * Walks every IDE-capable user's `wpgraphql_ide_personal_collections`
 * meta, returns entries where the current user appears in `shared_with`.
 * Strips `shared_with` from the result (recipients don't need to see the
 * full ACL) and tacks on the owner's id + display name so the panel
 * can attribute the section.
 *
 * Read-only by construction — the owner's user_meta is the only writable
 * source. Recipients never see the original blob.
 *
 * @return array<int, array<string, mixed>>
 */
function aggregate_shared_collections(): array {
	$current_id = get_current_user_id();
	if ( $current_id <= 0 ) {
		return [];
	}

	$users = get_users(
		[
			'capability' => 'manage_graphql_ide',
			'exclude'    => [ $current_id ],
			'fields'     => [ 'ID', 'display_name' ],
		]
	);

	$out = [];
	foreach ( $users as $user ) {
		$collections = get_user_meta( (int) $user->ID, 'wpgraphql_ide_personal_collections', true );
		if ( ! is_array( $collections ) || empty( $collections ) ) {
			continue;
		}
		foreach ( $collections as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$shared = isset( $entry['shared_with'] ) && is_array( $entry['shared_with'] )
				? array_map( 'intval', $entry['shared_with'] )
				: [];
			if ( ! in_array( $current_id, $shared, true ) ) {
				continue;
			}
			$doc_ids = isset( $entry['document_ids'] ) && is_array( $entry['document_ids'] )
				? array_map( 'intval', $entry['document_ids'] )
				: [];
			// Inline a thin doc descriptor so the panel can render
			// titles + status without each viewer doing a second REST
			// fetch per shared doc.
			$documents = [];
			foreach ( $doc_ids as $doc_id ) {
				$post = get_post( $doc_id );
				if ( ! $post || 'graphql_ide_query' !== $post->post_type ) {
					continue;
				}
				$documents[] = [
					'id'     => (int) $post->ID,
					'title'  => $post->post_title,
					'status' => $post->post_status,
				];
			}
			$out[] = [
				'id'        => (string) ( $entry['id'] ?? '' ),
				'name'      => (string) ( $entry['name'] ?? '' ),
				'documents' => $documents,
				'owner'     => [
					'id'           => (int) $user->ID,
					'display_name' => (string) $user->display_name,
				],
			];
		}
	}

	return $out;
}

/**
 * Strip a deleted document's id from its owner's personal_collections.
 *
 * Personal collections own their membership inside one user_meta blob, so a
 * deleted document leaves stale references unless we sweep them. We only
 * walk the document author's row — no other user could have referenced it.
 *
 * @param int      $post_id Post ID being deleted.
 * @param \WP_Post $post    Post object.
 */
function purge_document_from_personal_collections( int $post_id, $post ): void {
	if ( ! ( $post instanceof \WP_Post ) || 'graphql_ide_query' !== $post->post_type ) {
		return;
	}

	$author_id = (int) $post->post_author;
	if ( $author_id <= 0 ) {
		return;
	}

	$collections = get_user_meta( $author_id, 'wpgraphql_ide_personal_collections', true );
	if ( ! is_array( $collections ) || empty( $collections ) ) {
		return;
	}

	$changed = false;
	foreach ( $collections as $idx => $entry ) {
		if ( ! isset( $entry['document_ids'] ) || ! is_array( $entry['document_ids'] ) ) {
			continue;
		}
		$filtered = array_values(
			array_filter(
				$entry['document_ids'],
				static fn( $id ) => (int) $id !== $post_id
			)
		);
		if ( count( $filtered ) !== count( $entry['document_ids'] ) ) {
			$collections[ $idx ]['document_ids'] = $filtered;
			$changed                             = true;
		}
	}

	if ( $changed ) {
		update_user_meta( $author_id, 'wpgraphql_ide_personal_collections', $collections );
	}
}

/**
 * Scope REST API queries for IDE documents to the current user.
 *
 * @param array<string, mixed> $args WP_Query arguments.
 * @return array<string, mixed> Modified arguments.
 */
function scope_ide_queries_to_current_user( $args ) {
	$args['author'] = get_current_user_id();
	return $args;
}

/**
 * Scope GraphQL connections on IDE post types to the current user.
 *
 * The IDE exposes `IdeQuery` and `IdeHistoryEntry` to GraphQL so the
 * client (and integrations) can read saved queries and history without
 * REST. Without scoping, anyone holding `manage_graphql_ide` would see
 * every other IDE user's queries through `ideQueries` / `ideHistoryEntries`
 * — the GraphQL surface would be a wider read than the REST endpoints
 * already enforce. Mirror the REST behavior here so the per-user
 * isolation is the same on both surfaces.
 *
 * @since x-release-please-version
 *
 * @param array<string, mixed> $query_args  Connection query args (forwarded to WP_Query).
 * @param mixed                $source      The parent (root) source for the connection.
 * @return array<string, mixed>
 */
function scope_ide_graphql_connections_to_current_user( $query_args, $source ): array {
	unset( $source ); // Source is unused — scoping is global per current user.

	$post_type = isset( $query_args['post_type'] ) ? $query_args['post_type'] : null;

	$ide_post_types = [ 'graphql_ide_query', 'graphql_ide_history' ];

	$matches_ide_pt = false;
	if ( is_string( $post_type ) ) {
		$matches_ide_pt = in_array( $post_type, $ide_post_types, true );
	} elseif ( is_array( $post_type ) ) {
		$matches_ide_pt = (bool) array_intersect( $post_type, $ide_post_types );
	}

	if ( ! $matches_ide_pt ) {
		return $query_args;
	}

	$query_args['author'] = get_current_user_id();

	return $query_args;
}

/**
 * Mark IDE post objects as private when the current user isn't the
 * author. The connection-level filter already scopes list queries,
 * but `node(id: "...")` resolves a model directly from the global ID
 * and bypasses connection args entirely. Without this filter, a user
 * holding `manage_graphql_ide` could read another user's IdeQuery or
 * IdeHistoryEntry just by guessing or sharing its global ID.
 *
 * Visibility is decided in the WPGraphQL Model layer: returning true
 * here marks the data private so the model's restricted fields (id,
 * databaseId, isRestricted, etc.) are still readable, but the rest
 * resolves to null. This matches WPGraphQL's existing pattern for
 * draft posts and is the same path used by core's `is_post_private()`.
 *
 * @since x-release-please-version
 *
 * @param bool        $is_private   Whether the model is private.
 * @param string      $model_name   Name of the WPGraphQL model.
 * @param mixed       $data         The un-modeled data (a `WP_Post` for `PostObject`).
 * @param string|null $visibility   Visibility set so far.
 * @param int|null    $owner        Owner user ID, if any.
 * @param \WP_User    $current_user Current user for the session.
 */
function restrict_ide_post_visibility_to_author( $is_private, $model_name, $data, $visibility, $owner, $current_user ): bool {
	unset( $visibility ); // Unused — we make a final decision based on the data + current user.

	if ( 'PostObject' !== $model_name ) {
		return (bool) $is_private;
	}

	if ( ! $data instanceof \WP_Post ) {
		return (bool) $is_private;
	}

	$ide_post_types = [ 'graphql_ide_query', 'graphql_ide_history' ];
	if ( ! in_array( $data->post_type, $ide_post_types, true ) ) {
		return (bool) $is_private;
	}

	$current_user_id = $current_user instanceof \WP_User ? (int) $current_user->ID : 0;
	$post_author_id  = (int) $data->post_author;

	// Owner is the source of truth when the model layer set it (matches
	// the User-owner pattern used elsewhere in WPGraphQL).
	if ( null !== $owner ) {
		$post_author_id = (int) $owner;
	}

	if ( $current_user_id > 0 && $current_user_id === $post_author_id ) {
		return (bool) $is_private;
	}

	return true;
}

/**
 * Register IDE-specific GraphQL fields backed by post meta.
 *
 * Post meta isn't auto-exposed by `register_post_meta` — WPGraphQL
 * needs explicit `register_graphql_field` calls. The field names
 * strip the internal `_graphql_ide_` prefix and switch to camelCase
 * to match the rest of the WPGraphQL schema. Resolvers read from the
 * underlying post meta; the meta keys themselves are unchanged so
 * existing REST and direct DB access keep working.
 *
 * The query body itself lives in `post_content` (so the editor's
 * autosave + revision history work), but consumers expect a clearly
 * named field — we expose `queryString` as a thin alias that returns
 * `post_content` so neither audience has to know about the storage
 * detail.
 *
 * @since x-release-please-version
 */
function register_ide_graphql_fields(): void {
	if ( ! function_exists( 'register_graphql_field' ) ) {
		return;
	}

	register_graphql_field(
		'IdeQuery',
		'queryString',
		[
			'type'        => 'String',
			'description' => __( 'The GraphQL document body for this saved query.', 'wpgraphql-ide' ),
			'resolve'     => static function ( $post ) {
				return get_post_field( 'post_content', $post->databaseId );
			},
		]
	);

	register_graphql_field(
		'IdeQuery',
		'variables',
		[
			'type'        => 'String',
			'description' => __( 'JSON-encoded variables for this saved query.', 'wpgraphql-ide' ),
			'resolve'     => static function ( $post ) {
				return (string) get_post_meta( $post->databaseId, '_graphql_ide_variables', true );
			},
		]
	);

	register_graphql_field(
		'IdeQuery',
		'headers',
		[
			'type'        => 'String',
			'description' => __( 'JSON-encoded HTTP headers stored with this saved query.', 'wpgraphql-ide' ),
			'resolve'     => static function ( $post ) {
				return (string) get_post_meta( $post->databaseId, '_graphql_ide_headers', true );
			},
		]
	);

	$history_meta_fields = [
		'queryString'     => [
			'meta'        => '_graphql_ide_query',
			'type'        => 'String',
			'description' => __( 'The GraphQL document executed for this history entry.', 'wpgraphql-ide' ),
		],
		'variables'       => [
			'meta'        => '_graphql_ide_variables',
			'type'        => 'String',
			'description' => __( 'JSON-encoded variables sent with the request.', 'wpgraphql-ide' ),
		],
		'headers'         => [
			'meta'        => '_graphql_ide_headers',
			'type'        => 'String',
			'description' => __( 'JSON-encoded HTTP headers sent with the request.', 'wpgraphql-ide' ),
		],
		'durationMs'      => [
			'meta'        => '_graphql_ide_duration_ms',
			'type'        => 'Int',
			'description' => __( 'How long the request took, in milliseconds.', 'wpgraphql-ide' ),
		],
		'executionStatus' => [
			'meta'        => '_graphql_ide_status',
			'type'        => 'String',
			'description' => __( 'Result status of the executed request (e.g. success, error). Distinct from post_status (which is on the inherited Post.status field).', 'wpgraphql-ide' ),
		],
		'documentId'      => [
			'meta'        => '_graphql_ide_document_id',
			'type'        => 'Int',
			'description' => __( 'Database ID of the saved IdeQuery this entry was executed against, if any.', 'wpgraphql-ide' ),
		],
		'isAuthenticated' => [
			'meta'        => '_graphql_ide_is_authenticated',
			'type'        => 'Boolean',
			'description' => __( 'Whether the request was sent with an authenticated session.', 'wpgraphql-ide' ),
		],
		'httpMethod'      => [
			'meta'        => '_graphql_ide_http_method',
			'type'        => 'String',
			'description' => __( 'HTTP method used for the request.', 'wpgraphql-ide' ),
		],
	];

	foreach ( $history_meta_fields as $field_name => $config ) {
		$meta_key = $config['meta'];
		$type     = $config['type'];

		register_graphql_field(
			'IdeHistoryEntry',
			$field_name,
			[
				'type'        => $type,
				'description' => $config['description'],
				'resolve'     => static function ( $post ) use ( $meta_key, $type ) {
					$value = get_post_meta( $post->databaseId, $meta_key, true );

					if ( 'Int' === $type ) {
						return (int) $value;
					}
					if ( 'Boolean' === $type ) {
						// Stored as `1`/empty by post meta. Cast through
						// (int) first to keep `'0'` falsy.
						return (bool) (int) $value;
					}
					return (string) $value;
				},
			]
		);
	}
}

/**
 * Enforce manage_graphql_ide capability on all IDE document REST endpoints.
 *
 * This prevents users without the manage_graphql_ide capability from
 * accessing the graphql-ide-queries REST routes, even if they have
 * the edit_posts capability from the CPT's capability_type.
 *
 * @param mixed            $result  Response to replace the requested version with.
 * @param \WP_REST_Server  $server  Server instance.
 * @param \WP_REST_Request $request Request used to generate the response.
 * @return mixed|\WP_Error
 */
function enforce_ide_rest_permissions( $result, $server, $request ) {
	$route = $request->get_route();

	$is_ide_route = strpos( $route, '/wp/v2/graphql-ide-queries' ) === 0
		|| strpos( $route, '/wp/v2/graphql-ide-history' ) === 0;

	if ( ! $is_ide_route ) {
		return $result;
	}

	if ( ! current_user_can( 'manage_graphql_ide' ) ) {
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access IDE queries.', 'wpgraphql-ide' ),
			[ 'status' => 403 ]
		);
	}

	return $result;
}

/**
 * Restrict single document responses to the document's author.
 *
 * Prevents users from accessing documents they don't own, even if
 * they have the manage_graphql_ide capability.
 *
 * @param \WP_REST_Response $response The response object.
 * @param \WP_Post          $post     The post object.
 * @param \WP_REST_Request  $request  The request object.
 * @return \WP_REST_Response|\WP_Error
 */
function restrict_document_to_author( $response, $post, $request ) {
	$current_id = get_current_user_id();
	if ( (int) $post->post_author === $current_id ) {
		return $response;
	}

	// Shared-collection grant: a doc is also readable if it appears in a
	// personal collection the current user has been added to via
	// `shared_with`. The grant is read-only — single-doc fetches only;
	// the list route still scopes to the viewer's own docs.
	if (
		'graphql_ide_query' === $post->post_type
		&& current_user_can( 'manage_graphql_ide' )
		&& document_is_shared_with_current_user( (int) $post->ID )
	) {
		return $response;
	}

	return new \WP_Error(
		'rest_forbidden',
		__( 'You do not have permission to access this document.', 'wpgraphql-ide' ),
		[ 'status' => 403 ]
	);
}

/**
 * Truncate `post_title` to 200 chars on `graphql_ide_query` writes.
 *
 * The `post_title` column is TEXT in MySQL with no hard cap, so a
 * long POST body could bloat the database or break admin UIs (post
 * lists, the IDE's own tab strip) that can't reasonably display past
 * ~200 chars. Applies to every write path — REST creates/updates,
 * the import/upsert flow, and any direct `wp_insert_post` callers.
 *
 * Other CPTs pass through unchanged. `mb_substr` is used so we never
 * slice through a multi-byte character.
 *
 * @param array<string,mixed> $data    Slashed, sanitized post data about to be written.
 * @param array<string,mixed> $postarr Original input array (unsanitized).
 *
 * @return array<string,mixed>
 */
function cap_ide_document_title_length( array $data, array $postarr ): array {
	if ( ( $data['post_type'] ?? '' ) !== 'graphql_ide_query' ) {
		return $data;
	}
	$title = (string) ( $data['post_title'] ?? '' );
	if ( mb_strlen( $title ) > 200 ) {
		$data['post_title'] = mb_substr( $title, 0, 200 );
	}
	return $data;
}

/**
 * Whether `$doc_id` appears in any personal collection that has been
 * shared with the current user. Walks IDE-capable users' meta — small
 * scale only; cache if this turns into a hot path.
 *
 * @param int $doc_id
 */
function document_is_shared_with_current_user( int $doc_id ): bool {
	if ( $doc_id <= 0 ) {
		return false;
	}
	foreach ( aggregate_shared_collections() as $collection ) {
		if ( ! isset( $collection['documents'] ) || ! is_array( $collection['documents'] ) ) {
			continue;
		}
		foreach ( $collection['documents'] as $doc ) {
			if ( isset( $doc['id'] ) && (int) $doc['id'] === $doc_id ) {
				return true;
			}
		}
	}
	return false;
}

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
		register_ide_post_type();
	}

	seed_example_documents();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\wpgraphql_ide_activate' );

/**
 * The seed schema version. Bump this to push new example documents to
 * existing installs. Documents are only seeded when the stored option
 * version differs from this value, so users who deleted earlier seeds
 * won't get them recreated unless we ship a newer set.
 */
const SEED_VERSION = '1';

/**
 * Wire format version for the import/export JSON. Bump on any
 * breaking schema change (renamed/removed fields, restructured
 * collections, etc.). Additive changes don't require a bump.
 */
const IMPORT_SCHEMA_VERSION = 1;

/**
 * Seed example collections and documents for the activating user.
 * Idempotent via `wpgraphql_ide_seed_version`.
 *
 * Documents are seeded as published with SHA-256 content-addressed
 * slugs (same algorithm as `handle_publish_document`), so the
 * activated install matches the canonical example dataset exactly.
 */
function seed_example_documents(): void {
	if ( get_option( 'wpgraphql_ide_seed_version' ) === SEED_VERSION ) {
		return;
	}

	$author_id = get_current_user_id();
	if ( ! $author_id ) {
		return;
	}

	import_documents_data( get_seed_definitions(), $author_id );
	update_option( 'wpgraphql_ide_seed_version', SEED_VERSION, false );
}

/**
 * Import a `{ collections: [...] }` payload as documents owned by the
 * given user. Idempotent for published docs (SHA-256 dedup); drafts
 * are always created fresh (drafts are mutable working copies).
 *
 * @param array<string,mixed> $data       Payload matching the seed JSON schema.
 * @param int                 $author_id  Owner of imported documents.
 * @return array{created: int, skipped: int, collections: array<int,int>}
 */
function import_documents_data( array $data, int $author_id ): array {
	// Treat a missing version as v1 so legacy/un-versioned payloads
	// (including the very first seed file) still import cleanly.
	$version = isset( $data['version'] ) ? (int) $data['version'] : IMPORT_SCHEMA_VERSION;
	if ( IMPORT_SCHEMA_VERSION !== $version ) {
		return [
			'created'     => 0,
			'skipped'     => 0,
			'collections' => [],
			'error'       => sprintf(
				/* translators: 1: payload version, 2: supported version */
				__( 'Unsupported import schema version %1$d (this build expects version %2$d).', 'wpgraphql-ide' ),
				$version,
				IMPORT_SCHEMA_VERSION
			),
		];
	}

	$created     = 0;
	$skipped     = 0;
	$term_ids    = [];
	$collections = $data['collections'] ?? [];

	if ( ! is_array( $collections ) ) {
		return [
			'created'     => 0,
			'skipped'     => 0,
			'collections' => [],
		];
	}

	foreach ( $collections as $collection ) {
		$name = isset( $collection['name'] ) ? (string) $collection['name'] : '';
		$docs = $collection['documents'] ?? [];
		if ( '' === $name || ! is_array( $docs ) ) {
			continue;
		}

		$term = term_exists( $name, 'graphql_ide_collection' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'graphql_ide_collection' );
		}
		if ( is_wp_error( $term ) || empty( $term['term_id'] ) ) {
			continue;
		}

		$term_id    = (int) $term['term_id'];
		$term_ids[] = $term_id;

		foreach ( $docs as $doc ) {
			$result = upsert_document( $doc, $term_id, $author_id );
			if ( 'created' === $result ) {
				++$created;
			} elseif ( 'skipped' === $result ) {
				++$skipped;
			}
		}
	}

	return [
		'created'     => $created,
		'skipped'     => $skipped,
		'collections' => $term_ids,
	];
}

/**
 * Insert or attach a single document. Returns the action taken so the
 * importer can report counts back to the UI.
 *
 * @param array<string,mixed> $doc
 * @param int                 $term_id
 * @param int                 $author_id
 * @return 'created'|'skipped'|'error'
 */
function upsert_document( array $doc, int $term_id, int $author_id ): string {
	$query = isset( $doc['query'] ) ? (string) $doc['query'] : '';
	if ( '' === trim( $query ) ) {
		return 'error';
	}

	$status = ( $doc['status'] ?? 'publish' ) === 'draft' ? 'draft' : 'publish';
	$title  = isset( $doc['title'] ) && '' !== $doc['title'] ? (string) $doc['title'] : __( 'Untitled', 'wpgraphql-ide' );

	$body = $query;
	$slug = '';

	if ( 'publish' === $status ) {
		try {
			$ast  = \GraphQL\Language\Parser::parse( $query );
			$body = \GraphQL\Language\Printer::doPrint( $ast );
			$slug = hash( 'sha256', $body );
		} catch ( \Throwable $e ) {
			return 'error';
		}

		$existing = get_posts(
			[
				'post_type'      => 'graphql_ide_query',
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $existing ) ) {
			wp_set_object_terms( (int) $existing[0], [ $term_id ], 'graphql_ide_collection', true );
			return 'skipped';
		}
	}

	$postarr = [
		'post_type'    => 'graphql_ide_query',
		'post_status'  => $status,
		'post_author'  => $author_id,
		'post_title'   => $title,
		'post_content' => $body,
	];
	if ( '' !== $slug ) {
		$postarr['post_name'] = $slug;
	}

	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 'error';
	}

	wp_set_object_terms( $post_id, [ $term_id ], 'graphql_ide_collection' );

	if ( ! empty( $doc['variables'] ) ) {
		update_post_meta( $post_id, '_graphql_ide_variables', (string) $doc['variables'] );
	}
	if ( ! empty( $doc['headers'] ) ) {
		update_post_meta( $post_id, '_graphql_ide_headers', (string) $doc['headers'] );
	}

	return 'created';
}

/**
 * Build an export payload — current user's documents grouped by
 * collection. Documents not assigned to any collection are skipped so
 * the export round-trips through the importer cleanly.
 *
 * @param int $author_id
 * @return array{collections: array<int,array{name:string,documents:array<int,array<string,mixed>>}>}
 */
function export_documents_data( int $author_id ): array {
	$terms = get_terms(
		[
			'taxonomy'   => 'graphql_ide_collection',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);

	if ( is_wp_error( $terms ) ) {
		return [
			'version'     => IMPORT_SCHEMA_VERSION,
			'collections' => [],
		];
	}

	$collections = [];

	foreach ( $terms as $term ) {
		$post_ids = get_posts(
			[
				'post_type'      => 'graphql_ide_query',
				'post_status'    => [ 'draft', 'publish' ],
				'author'         => $author_id,
				'tax_query'      => [
					[
						'taxonomy' => 'graphql_ide_collection',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					],
				],
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);

		if ( empty( $post_ids ) ) {
			continue;
		}

		$documents = [];
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$doc = [
				'title' => $post->post_title,
				'query' => $post->post_content,
			];

			$variables = (string) get_post_meta( $post->ID, '_graphql_ide_variables', true );
			if ( '' !== $variables ) {
				$doc['variables'] = $variables;
			}

			$headers = (string) get_post_meta( $post->ID, '_graphql_ide_headers', true );
			if ( '' !== $headers ) {
				$doc['headers'] = $headers;
			}

			// `publish` is the default — only emit when it differs.
			if ( 'publish' !== $post->post_status ) {
				$doc['status'] = $post->post_status;
			}

			$documents[] = $doc;
		}

		$collections[] = [
			'name'      => $term->name,
			'documents' => $documents,
		];
	}

	return [
		'version'     => IMPORT_SCHEMA_VERSION,
		'collections' => $collections,
	];
}

/**
 * Load the canonical example dataset from `seeds/example-documents.json`.
 * Returns the raw parsed payload — same shape the importer accepts.
 * Edit the JSON file and bump `SEED_VERSION` to push updated examples
 * to existing installs.
 *
 * @return array{collections?: array<int, array{name:string, documents:array<int,array<string,mixed>>}>}
 */
function get_seed_definitions(): array {
	$path = WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'seeds/example-documents.json';
	if ( ! file_exists( $path ) ) {
		return [ 'collections' => [] ];
	}

	// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading a local plugin file.
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		return [ 'collections' => [] ];
	}

	$data = json_decode( $contents, true );
	return is_array( $data ) ? $data : [ 'collections' => [] ];
}

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
	$shared_collections      = aggregate_shared_collections();

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
 * Initialize the plugin tracker.
 */
function graphql_ide_init_appsero_telemetry(): void {
	if ( ! class_exists( 'Appsero\Client' ) || defined( 'PHPSTAN' ) ) {
		return;
	}

	try {
		$client = new \Appsero\Client( 'e90103d6-2c09-4152-96e0-eb7d0d3b5c74', 'WPGraphQL IDE', __FILE__ );

		/**
		 * @var \Appsero\Insights $insights
		 *
		 * @phpstan-ignore varTag.type (The doctype for Appsero\Client::insights() is wrong.)
		 */
		$insights = $client->insights();

		if ( method_exists( $insights, 'add_plugin_data' ) ) {
			$insights->add_plugin_data();
		}

		$insights->init();
	} catch ( \Throwable $e ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is intentional here.
			sprintf(
				// translators: %s is the error message
				__( 'Error initializing Appsero: %s', 'wpgraphql-ide' ),
				$e->getMessage()
			)
		);
	}
}

graphql_ide_init_appsero_telemetry();

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
	return rest_ensure_response( export_documents_data( get_current_user_id() ) );
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

	$result = import_documents_data( $body, get_current_user_id() );
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

/**
 * Mirror the Appsero API requests to our own telemetry server.
 *
 * @param bool|\WP_Error $preempt Whether to preempt the request.
 * @param array          $args    The arguments for the request.
 * @param string         $url     The URL for the request.
 * @return bool|\WP_Error Whether to preempt the request.
 */
add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( strpos( $url, 'api.appsero.com' ) === false ) {
			return $preempt;
		}

		// Scope: only mirror this plugin's payloads, not other Appsero plugins on the site.
		$body = is_array( $args['body'] ?? null ) ? $args['body'] : [];
		if ( ( $body['hash'] ?? null ) !== 'e90103d6-2c09-4152-96e0-eb7d0d3b5c74' ) {
			return $preempt;
		}

		$mirror = str_replace(
			'https://api.appsero.com/',
			'https://telemetry.wpgraphql.com/api/appsero/',
			$url
		);

		wp_remote_post(
			$mirror,
			array_merge(
				$args,
				[
					'blocking' => false,
					'timeout'  => 3,
				]
			)
		);

		return $preempt; // let the real Appsero request proceed
	},
	10,
	3
);
