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

		// Keep `graphql_document` on the classic editor. Adding `show_in_rest`
		// above (which the IDE's REST client and REST-exposed meta need) is
		// enough to flip WordPress's `use_block_editor_for_post_type()` to
		// true, since the post type already supports `editor`. The block
		// editor is not a Smart Cache feature and the IDE has no business
		// changing Smart Cache's native edit screen, so opt the post type
		// back out. Priority 9 (below the default 10) makes this the default
		// while still letting a site that genuinely wants Gutenberg here
		// override it with a later-priority filter.
		add_filter( 'use_block_editor_for_post_type', [ self::class, 'disable_block_editor_for_smart_cache_document' ], 9, 2 );

		// Register IDE-specific meta on graphql_document. Runs on `init`
		// priority 11 so it lands after Smart Cache's own init at 10.
		add_action( 'init', [ self::class, 'register_ide_meta_on_smart_cache_document' ], 11 );

		// Surface the IDE's variables / headers meta on the GraphqlDocument
		// GraphQL type (read fields + Create/Update input fields) so the IDE
		// can read and write per-document execution context through GraphQL
		// instead of REST. Smart Cache itself only stores the query string
		// in post_content; this adds the IDE-specific execution-context
		// surface in the same place the rest of the document fields live.
		add_action( 'graphql_register_types', [ self::class, 'register_ide_graphql_fields_on_smart_cache_document' ] );

		// Persist the variables / headers inputs after a Create/Update
		// mutation succeeds. Mirrors the Smart Cache MaxAge / Grant pattern
		// (see plugins/wp-graphql-smart-cache/src/Document/MaxAge.php:125).
		add_action( 'graphql_mutation_response', [ self::class, 'save_ide_inputs_after_mutation' ], 10, 6 );
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
	 * Keep Smart Cache's `graphql_document` on the classic editor.
	 *
	 * The IDE only enables `show_in_rest` on this post type so its REST
	 * client and the `_graphql_ide_*` meta work. A side effect of that is
	 * WordPress switching the post type to the block editor, because
	 * `use_block_editor_for_post_type()` returns true once a post type both
	 * supports `editor` and is REST-exposed. Gutenberg was never part of
	 * Smart Cache's document UI, so the IDE forces it back off here.
	 *
	 * Hooked at priority 9 (below the default 10) so it sets the default
	 * rather than the final word: a site that deliberately wants the block
	 * editor for `graphql_document` can still re-enable it from a callback
	 * at priority 10 or later.
	 *
	 * @param bool   $use_block_editor Whether the post type uses the block editor.
	 * @param string $post_type        Post type being checked.
	 * @return bool
	 */
	public static function disable_block_editor_for_smart_cache_document( $use_block_editor, $post_type ) {
		if ( 'graphql_document' === $post_type ) {
			return false;
		}

		return $use_block_editor;
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

	/**
	 * Expose the IDE's variables / headers meta in the GraphQL schema on
	 * `GraphqlDocument`. Three registrations per field:
	 *
	 * 1. A read field on the `GraphqlDocument` object type, resolving from
	 *    the underlying `_graphql_ide_*` post meta.
	 * 2. An input field on `CreateGraphqlDocumentInput`.
	 * 3. An input field on `UpdateGraphqlDocumentInput`.
	 *
	 * Persistence of the inputs happens in `save_ide_inputs_after_mutation`
	 * (hooked on `graphql_mutation_response`).
	 *
	 * Smart Cache owns the post type, so we add to its type definition via
	 * `register_graphql_field` from inside the bridge — same shape Smart
	 * Cache itself uses for its `description` / `grant` / `max_age_header`
	 * fields (see `plugins/wp-graphql-smart-cache/src/Document/`).
	 */
	public static function register_ide_graphql_fields_on_smart_cache_document(): void {
		if ( ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		$variables_config = [
			'type'        => 'String',
			'description' => __( 'JSON-encoded variables to send with this query (IDE execution context).', 'wpgraphql-ide' ),
		];

		$headers_config = [
			'type'        => 'String',
			'description' => __( 'JSON-encoded HTTP headers to send with this query (IDE execution context).', 'wpgraphql-ide' ),
		];

		// Read field — resolver reads the meta key the bridge already writes
		// to via the REST path.
		register_graphql_field(
			'GraphqlDocument',
			'variables',
			array_merge(
				$variables_config,
				[
					'resolve' => static function ( $post ) {
						return (string) get_post_meta( $post->databaseId, '_graphql_ide_variables', true );
					},
				]
			)
		);

		register_graphql_field(
			'GraphqlDocument',
			'headers',
			array_merge(
				$headers_config,
				[
					'resolve' => static function ( $post ) {
						return (string) get_post_meta( $post->databaseId, '_graphql_ide_headers', true );
					},
				]
			)
		);

		// Input fields — accepted by the auto-generated Smart Cache
		// mutations. Persisted in save_ide_inputs_after_mutation.
		register_graphql_field( 'CreateGraphqlDocumentInput', 'variables', $variables_config );
		register_graphql_field( 'UpdateGraphqlDocumentInput', 'variables', $variables_config );
		register_graphql_field( 'CreateGraphqlDocumentInput', 'headers', $headers_config );
		register_graphql_field( 'UpdateGraphqlDocumentInput', 'headers', $headers_config );
	}

	/**
	 * Persist the IDE's variables / headers mutation inputs after
	 * `createGraphqlDocument` / `updateGraphqlDocument` runs. Mirrors Smart
	 * Cache's own pattern for max_age_header and grant fields — runs after
	 * the post is in place so `postObjectId` is reliable.
	 *
	 * The same `_graphql_ide_*` meta keys are written that the REST path
	 * uses (via the `register_post_meta` calls above), so both wire
	 * protocols stay round-trip-compatible during the migration.
	 *
	 * @param array<string,mixed>                  $post_object     Mutation payload (contains postObjectId).
	 * @param array<string,mixed>                  $filtered_input  Mutation input args after the graphql_mutation_input filter.
	 * @param array<string,mixed>                  $input           Unfiltered mutation input args.
	 * @param \WPGraphQL\AppContext                $context         Request context.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info            Resolve info.
	 * @param string                               $mutation_name   Mutation field name.
	 */
	public static function save_ide_inputs_after_mutation( $post_object, $filtered_input, $input, $context, $info, $mutation_name ): void {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return;
		}

		if ( empty( $post_object['postObjectId'] ) ) {
			return;
		}

		$post_id = (int) $post_object['postObjectId'];

		if ( isset( $filtered_input['variables'] ) ) {
			// register_post_meta's sanitize_callback ($sanitize_json) runs on
			// update_post_meta, so invalid JSON is dropped to an empty string
			// before storage — same behavior as the REST write path.
			update_post_meta( $post_id, '_graphql_ide_variables', (string) $filtered_input['variables'] );
		}

		if ( isset( $filtered_input['headers'] ) ) {
			update_post_meta( $post_id, '_graphql_ide_headers', (string) $filtered_input['headers'] );
		}
	}
}
