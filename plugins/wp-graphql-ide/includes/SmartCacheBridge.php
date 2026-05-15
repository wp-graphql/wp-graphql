<?php
/**
 * Smart Cache compatibility bridge.
 *
 * The IDE consumes Smart Cache's saved-document primitive (`graphql_document`
 * post type + `graphql_query_alias` / `graphql_document_grant` /
 * `graphql_document_http_maxage` / `graphql_document_group` taxonomies)
 * via the WP REST API. Smart Cache registers all of those with
 * `show_in_graphql: true` but not `show_in_rest`, which is fine for
 * Smart Cache's own use case but blocks the IDE's REST-based JS client.
 *
 * This bridge filters Smart Cache's registration args to add the REST
 * exposure the IDE needs. It runs on `init` priority < 10 so the filters
 * are in place when Smart Cache's own `init` handler calls
 * `register_post_type` / `register_taxonomy` at priority 10.
 *
 * It also registers two IDE-specific post meta fields
 * (`_graphql_ide_variables`, `_graphql_ide_headers`) on `graphql_document`
 * so the IDE can persist execution context (variables JSON, headers JSON)
 * alongside each saved query. Smart Cache itself only cares about the
 * query string in `post_content`; the IDE adds the meta as a non-invasive
 * extension. Plugins not running the IDE don't see the meta unless they
 * explicitly read it.
 *
 * Everything in this file no-ops when Smart Cache isn't active. Filter
 * callbacks check the post type / taxonomy name; `register_post_meta`
 * calls won't bind unless `graphql_document` actually exists.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

class SmartCacheBridge {

	/**
	 * Wire the filters that add REST exposure to Smart Cache's primitives
	 * and register the IDE's per-document meta. Idempotent; safe to call
	 * multiple times.
	 *
	 * Calling site (in `wpgraphql-ide.php`'s `initialize_plugin()`) is on
	 * `wpgraphql_ide_init` which fires from `plugins_loaded` — well before
	 * the `init` priority 10 at which Smart Cache's `register_post_type`
	 * runs. That ordering is what lets us mutate Smart Cache's args
	 * without forking its code.
	 */
	public static function register(): void {
		// Smart Cache isn't installed — nothing to bridge.
		if ( ! class_exists( '\\WPGraphQL\\SmartCache\\Document' ) ) {
			return;
		}

		add_filter( 'register_post_type_args', [ self::class, 'add_rest_to_smart_cache_post_type' ], 10, 2 );
		add_filter( 'register_taxonomy_args', [ self::class, 'add_rest_to_smart_cache_taxonomies' ], 10, 2 );

		// Register IDE-specific meta on graphql_document. Runs on `init`
		// priority 11 so it lands after Smart Cache's own init at 10.
		add_action( 'init', [ self::class, 'register_ide_meta_on_smart_cache_document' ], 11 );
	}

	/**
	 * Add `show_in_rest` to Smart Cache's `graphql_document` post type.
	 *
	 * @param array<string,mixed> $args      Args being passed to `register_post_type`.
	 * @param string              $post_type Post type slug.
	 * @return array<string,mixed>
	 */
	public static function add_rest_to_smart_cache_post_type( $args, $post_type ) {
		if ( 'graphql_document' !== $post_type ) {
			return $args;
		}

		// Don't clobber if Smart Cache (or another plugin) already enabled
		// REST upstream — that's the eventual happy path.
		if ( empty( $args['show_in_rest'] ) ) {
			$args['show_in_rest'] = true;
		}

		// Page-attributes support gives us `menu_order`, which the IDE
		// uses for user-driven document reordering. Custom-fields exposes
		// post meta to REST as a writable `meta` field. Both are
		// additive — Smart Cache's own `supports` array is preserved.
		$existing_supports = isset( $args['supports'] ) && is_array( $args['supports'] )
			? $args['supports']
			: [ 'title', 'editor', 'author' ];
		$args['supports'] = array_values(
			array_unique(
				array_merge(
					$existing_supports,
					[ 'custom-fields', 'page-attributes', 'excerpt' ]
				)
			)
		);

		return $args;
	}

	/**
	 * Add `show_in_rest` to Smart Cache's four document-related taxonomies.
	 *
	 * @param array<string,mixed> $args     Args being passed to `register_taxonomy`.
	 * @param string              $taxonomy Taxonomy slug.
	 * @return array<string,mixed>
	 */
	public static function add_rest_to_smart_cache_taxonomies( $args, $taxonomy ) {
		$ide_consumed_taxonomies = [
			'graphql_query_alias',           // Smart Cache: alias / queryId names.
			'graphql_document_grant',        // Smart Cache: allow / deny.
			'graphql_document_http_maxage',  // Smart Cache: Cache-Control max-age.
			'graphql_document_group',        // Smart Cache: collections / grouping.
		];

		if ( ! in_array( $taxonomy, $ide_consumed_taxonomies, true ) ) {
			return $args;
		}

		if ( empty( $args['show_in_rest'] ) ) {
			$args['show_in_rest'] = true;
		}

		return $args;
	}

	/**
	 * Register the IDE's per-document meta on Smart Cache's
	 * `graphql_document` post type. Variables JSON and headers JSON travel
	 * with each saved query so the IDE can restore the full execution
	 * context, not just the query string.
	 *
	 * Both keys are auth-gated on the IDE capability and JSON-sanitized
	 * on write. Empty strings allowed (default).
	 */
	public static function register_ide_meta_on_smart_cache_document(): void {
		// post_type_exists confirms Smart Cache actually registered. Belt-
		// and-suspenders alongside the class_exists check in register().
		if ( ! post_type_exists( 'graphql_document' ) ) {
			return;
		}

		$auth_callback = static function () {
			return wpgraphql_ide_user_can();
		};

		$sanitize_json = static function ( $value ) {
			if ( empty( $value ) ) {
				return '';
			}
			json_decode( $value );
			return json_last_error() === JSON_ERROR_NONE ? $value : '';
		};

		register_post_meta(
			'graphql_document',
			'_graphql_ide_variables',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'auth_callback'     => $auth_callback,
				'sanitize_callback' => $sanitize_json,
			]
		);

		register_post_meta(
			'graphql_document',
			'_graphql_ide_headers',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'auth_callback'     => $auth_callback,
				'sanitize_callback' => $sanitize_json,
			]
		);
	}
}
