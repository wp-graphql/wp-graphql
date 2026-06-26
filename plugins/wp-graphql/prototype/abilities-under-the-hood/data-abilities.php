<?php
/**
 * WPGraphQL Abilities Prototype — Data Abilities (stand-in).
 *
 * PROTOTYPE ONLY. Registers stand-in "get a post" / "get a list of posts"
 * abilities using the WordPress 6.9+ Abilities API. These simulate the kind of
 * read abilities we assume WordPress core will eventually ship, shaped here to
 * embody the recommendations in our draft response to the open PR AND to serve
 * both WPGraphQL's internals and a generic REST/agent consumer. Not for
 * production.
 *
 * Designed as a standalone module: in a real adoption the abilities would live
 * in WordPress core and this file would not exist. It is loaded via
 * require_once from the WPGraphQL bootstrap (see prototype/abilities-under-the-hood/load.php)
 * so the prototype travels with the branch and reproduces with zero env config.
 *
 * @package WPGraphQL\Prototype\Abilities
 *
 * This file is deliberately shaped around the positions in our PR-response draft,
 * so the prototype is *working evidence* for those positions:
 *
 *   - SPLIT single vs list. `wpgraphql/get-post` fetches ONE post and returns a
 *     single object (its output contract matches "get a single post by ID").
 *     `wpgraphql/get-posts` returns a list (array).
 *   - PERMISSION GRANULARITY. Because it is single-entity, get-post can gate
 *     cleanly in permission_callback (per-call == per-row). get-posts cannot:
 *     WP_Ability::check_permissions() is per-call, so row-level visibility has to
 *     live inside execute_callback. (Finding: the abilities permission model is
 *     single-entity-shaped.)
 *   - LEAN DEFAULT PAYLOAD. Both return a small default field set. Heavy fields
 *     (rendered content/excerpt, which run the_content) and raw fields are only
 *     returned when explicitly named in `fields` — never by default.
 *   - RAW = EXPLICIT INTENT. Raw fields require both an explicit `fields` request
 *     AND per-post edit capability (mirrors REST ?context=edit / WPGraphQL
 *     format: RAW). Out of the default shape entirely.
 *   - OPT-IN TOTAL. get-posts only computes found_posts/total_pages when
 *     `include_total` is true; otherwise it sets no_found_rows for a cheaper query.
 *   - BATCH-BY-IDS. get-posts accepts `include` (an ID array → post__in) so a
 *     DataLoader can batch-load N nodes in one call instead of N single-get calls.
 *     (Finding: a clean "single returns single" contract leaves batching to the
 *     list ability; neither single-get nor filtered-list is an obvious home for
 *     "load exactly these 37 ids", so the contract has to say so explicitly.)
 *
 * The visibility rules are ported from WPGraphQL\Model\Post::is_private() /
 * is_post_private() — see the note on the permission-model mismatch below.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The lean default field set returned when the caller does not name `fields`.
 * Deliberately excludes heavy fields (content/excerpt) and raw fields.
 *
 * @var string[]
 */
const WPGRAPHQL_PROTO_DEFAULT_FIELDS = [
	'id',
	'databaseId',
	'slug',
	'title',
	'date',
	'status',
	'type',
	'authorDatabaseId',
	'parentDatabaseId',
	'link',
];

/**
 * Whether the Abilities API is available (WordPress 6.9+).
 */
function wpgraphql_proto_abilities_available(): bool {
	return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
}

/* -------------------------------------------------------------------------
 * Visibility rules — ported from WPGraphQL\Model\Post.
 *
 * Finding (permission-model mismatch): a naive read ability would gate on
 * current_user_can('read_post', $id). For a *published, public* post that maps
 * to the 'read' cap, which an anonymous (logged-out) user does NOT have — so a
 * naive read ability breaks anonymous public reads, which WPGraphQL (and any
 * public REST surface) must support. To keep parity we port WPGraphQL's own
 * "published + public = world-readable" rule rather than lean on read_post.
 * i.e. the Model's permission logic physically moves into the ability — which is
 * the whole hypothesis made concrete.
 * ---------------------------------------------------------------------- */

/**
 * Port of Post::is_post_private().
 */
function wpgraphql_proto_post_is_post_private( WP_Post $post ): bool {
	$pt = get_post_type_object( $post->post_type );
	if ( ! $pt ) {
		return true;
	}

	if ( ! isset( $pt->cap->edit_posts ) || ! current_user_can( $pt->cap->edit_posts ) ) {
		return true;
	}

	$current_user = get_current_user_id();
	if ( $current_user && (int) $post->post_author === $current_user && 'revision' !== $post->post_type ) {
		return false;
	}

	if ( 'private' === $post->post_status && ( ! isset( $pt->cap->read_private_posts ) || ! current_user_can( $pt->cap->read_private_posts ) ) ) {
		return true;
	}

	if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status ) {
		$parent = get_post( (int) $post->post_parent );
		if ( empty( $parent ) ) {
			return true;
		}
		if ( 'private' === $parent->post_status ) {
			$cap = isset( $pt->cap->read_private_posts ) ? $pt->cap->read_private_posts : 'read_private_posts';
		} else {
			$cap = isset( $pt->cap->edit_post ) ? $pt->cap->edit_post : 'edit_post';
		}
		if ( ! current_user_can( $cap, $parent->ID ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Port of Post::is_private().
 */
function wpgraphql_proto_post_is_private( WP_Post $post ): bool {
	$pt = get_post_type_object( $post->post_type );
	if ( ! $pt ) {
		return true;
	}

	if ( 'revision' === $post->post_type ) {
		$parent = get_post( (int) $post->post_parent );
		if ( ! $parent instanceof WP_Post ) {
			return true;
		}
		return wpgraphql_proto_post_is_post_private( $parent );
	}

	if ( 'attachment' === $post->post_type ) {
		if ( 'inherit' === $post->post_status && ! empty( $post->post_parent ) ) {
			$parent = get_post( (int) $post->post_parent );
			if ( ! $parent instanceof WP_Post ) {
				return false;
			}
			return wpgraphql_proto_post_is_private( $parent );
		}
		return false;
	}

	if ( 'publish' === $post->post_status && ( true === $pt->public || true === $pt->publicly_queryable ) ) {
		return false;
	}

	return wpgraphql_proto_post_is_post_private( $post );
}

/**
 * Per-row visibility gate. True when the current user may view the given post.
 */
function wpgraphql_proto_user_can_view_post( WP_Post $post ): bool {
	return ! wpgraphql_proto_post_is_private( $post );
}

/* -------------------------------------------------------------------------
 * Payload builder. Lean by default; heavy + raw fields only on explicit request.
 * ---------------------------------------------------------------------- */

/**
 * Build a post payload.
 *
 * @param string[]|null $fields Explicit field selection. When null, the lean
 *                              default set is returned (no heavy/raw fields).
 * @return array<string,mixed>
 */
function wpgraphql_proto_build_post_payload( WP_Post $post, ?array $fields = null ): array {
	$selection = null === $fields ? WPGRAPHQL_PROTO_DEFAULT_FIELDS : $fields;
	$want      = static function ( string $key ) use ( $selection ): bool {
		return in_array( $key, $selection, true );
	};

	$payload = [];

	$scalars = [
		'id'               => $post->ID,
		'databaseId'       => $post->ID,
		'slug'             => $post->post_name,
		'status'           => $post->post_status,
		'type'             => $post->post_type,
		'date'             => $post->post_date,
		'dateGmt'          => $post->post_date_gmt,
		'modified'         => $post->post_modified,
		'authorDatabaseId' => (int) $post->post_author,
		'parentDatabaseId' => (int) $post->post_parent,
		'menuOrder'        => (int) $post->menu_order,
		'commentStatus'    => $post->comment_status,
		'pingStatus'       => $post->ping_status,
	];
	foreach ( $scalars as $key => $value ) {
		if ( $want( $key ) ) {
			$payload[ $key ] = $value;
		}
	}

	if ( $want( 'link' ) ) {
		$payload['link'] = get_permalink( $post );
	}

	// Heavy fields — only computed when explicitly requested.
	$password_required = post_password_required( $post );
	if ( $want( 'title' ) ) {
		$payload['title'] = get_the_title( $post );
	}
	if ( $want( 'content' ) ) {
		$payload['content'] = $password_required ? '' : apply_filters( 'the_content', $post->post_content );
	}
	if ( $want( 'excerpt' ) ) {
		$payload['excerpt'] = $password_required ? '' : apply_filters( 'the_excerpt', $post->post_excerpt );
	}

	// Raw fields — explicit intent (named in `fields`) AND per-post edit cap.
	// Never part of the default set. Mirrors REST ?context=edit / format: RAW.
	$raw_requested = $fields && array_intersect( [ 'titleRaw', 'contentRaw', 'excerptRaw' ], $fields );
	if ( $raw_requested && current_user_can( 'edit_post', $post->ID ) ) {
		if ( $want( 'titleRaw' ) ) {
			$payload['titleRaw'] = $post->post_title;
		}
		if ( $want( 'contentRaw' ) ) {
			$payload['contentRaw'] = $post->post_content;
		}
		if ( $want( 'excerptRaw' ) ) {
			$payload['excerptRaw'] = $post->post_excerpt;
		}
	}

	return $payload;
}

/* -------------------------------------------------------------------------
 * Ability registration.
 * ---------------------------------------------------------------------- */

add_action(
	'wp_abilities_api_categories_init',
	static function (): void {
		if ( ! wpgraphql_proto_abilities_available() ) {
			return;
		}
		wp_register_ability_category(
			'data',
			[
				'label'       => __( 'Data', 'wpgraphql-proto' ),
				'description' => __( 'Read content and data from the site.', 'wpgraphql-proto' ),
			]
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! wpgraphql_proto_abilities_available() ) {
			return;
		}

		/*
		 * Ability: wpgraphql/get-post — GET A SINGLE POST.
		 * Contract: one id in, one post object out (or a uniform not-found/no-perm
		 * error). Single-entity, so permission gates cleanly in permission_callback.
		 */
		wp_register_ability(
			'wpgraphql/get-post',
			[
				'label'               => __( 'Get Post', 'wpgraphql-proto' ),
				'description'         => __( 'Retrieve a single readable post by ID. Returns a single post object. Lean default fields; request more via `fields`.', 'wpgraphql-proto' ),
				'category'            => 'data',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'id'     => [
							'type'        => 'integer',
							'description' => 'The post ID to fetch.',
						],
						'fields' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Optional explicit field selection. Heavy and raw fields are only returned when named here.',
						],
					],
					'required'             => [ 'id' ],
					'additionalProperties' => false,
				],
				// Single-entity: per-call == per-row, so we gate cleanly here.
				// A not-found post returns false → execute() yields a uniform
				// "no permission" error (does not leak existence).
				'permission_callback' => static function ( $input = null ) {
					$id = is_array( $input ) ? (int) ( $input['id'] ?? 0 ) : 0;
					if ( ! $id ) {
						return new WP_Error( 'wpgraphql_proto_missing_id', __( 'A post id is required.', 'wpgraphql-proto' ) );
					}
					$post = get_post( $id );
					if ( ! $post instanceof WP_Post ) {
						return false;
					}
					return wpgraphql_proto_user_can_view_post( $post );
				},
				'execute_callback'    => static function ( $input = null ) {
					$input  = is_array( $input ) ? $input : [];
					$post   = get_post( (int) ( $input['id'] ?? 0 ) );
					$fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : null;
					if ( ! $post instanceof WP_Post ) {
						return new WP_Error( 'wpgraphql_proto_not_found', __( 'Post not found.', 'wpgraphql-proto' ) );
					}
					return wpgraphql_proto_build_post_payload( $post, $fields );
				},
			]
		);

		/*
		 * Ability: wpgraphql/get-posts — GET A LIST OF POSTS.
		 * Returns an array. `include` (ids) supports batched DataLoader-style
		 * loads. `include_total` opts into found_posts/total_pages (otherwise we
		 * set no_found_rows for a cheaper query). Lean default fields.
		 */
		wp_register_ability(
			'wpgraphql/get-posts',
			[
				'label'               => __( 'Get Posts', 'wpgraphql-proto' ),
				'description'         => __( 'Query a list of readable posts. Supports batch-by-ids (`include`), basic filters, opt-in total, and explicit field selection.', 'wpgraphql-proto' ),
				'category'            => 'data',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'include'      => [
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
							'description' => 'Specific post IDs to load in one call (post__in). Enables batched/DataLoader-style access.',
						],
						'post_type'    => [ 'type' => 'string' ],
						'status'       => [ 'type' => 'string' ],
						'author'       => [ 'type' => 'integer' ],
						'search'       => [ 'type' => 'string' ],
						'orderby'      => [ 'type' => 'string' ],
						'order'        => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ] ],
						'per_page'     => [ 'type' => 'integer' ],
						'page'         => [ 'type' => 'integer' ],
						'include_total' => [
							'type'        => 'boolean',
							'description' => 'Opt in to computing total/total_pages. Off by default to avoid the found_posts cost.',
						],
						'fields'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
					'additionalProperties' => false,
				],
				// Per-call permission cannot express per-row visibility for a list,
				// so row filtering happens inside execute (finding: list abilities
				// embed row-level authz in execution).
				'permission_callback' => static function ( $input = null ) {
					return true;
				},
				'execute_callback'    => static function ( $input = null ) {
					$input         = is_array( $input ) ? $input : [];
					$fields        = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : null;
					$include_total = ! empty( $input['include_total'] );
					$include       = isset( $input['include'] ) && is_array( $input['include'] ) ? array_map( 'intval', $input['include'] ) : [];

					$has_include = ! empty( $include );
					$args        = [
						// For an explicit id batch (include), default to any
						// type/status — the per-row visibility gate below decides
						// what the caller may actually see. This mirrors how
						// WPGraphQL's DataLoader queries post_status 'any'.
						'post_type'     => isset( $input['post_type'] ) ? $input['post_type'] : ( $has_include ? 'any' : 'post' ),
						'post_status'   => isset( $input['status'] ) ? $input['status'] : ( $has_include ? 'any' : 'publish' ),
						// Opt-in total: skip the found_posts calculation otherwise.
						'no_found_rows' => ! $include_total,
					];

					if ( $has_include ) {
						// Batch-by-ids: load exactly these, preserving order.
						$args['post__in']       = $include;
						$args['orderby']        = 'post__in';
						$args['posts_per_page'] = count( $include );
					} else {
						$args['posts_per_page'] = isset( $input['per_page'] ) ? max( 1, (int) $input['per_page'] ) : 10;
						$args['paged']          = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
						$args['orderby']        = isset( $input['orderby'] ) ? $input['orderby'] : 'date';
						$args['order']          = isset( $input['order'] ) ? $input['order'] : 'DESC';
						if ( isset( $input['author'] ) ) {
							$args['author'] = (int) $input['author'];
						}
						if ( isset( $input['search'] ) ) {
							$args['s'] = (string) $input['search'];
						}
					}

					$query = new WP_Query( $args );

					$posts = [];
					foreach ( $query->posts as $post ) {
						if ( ! $post instanceof WP_Post ) {
							continue;
						}
						if ( ! wpgraphql_proto_user_can_view_post( $post ) ) {
							continue;
						}
						$posts[] = wpgraphql_proto_build_post_payload( $post, $fields );
					}

					$result = [ 'posts' => $posts ];
					if ( $include_total ) {
						$result['total']       = (int) $query->found_posts;
						$result['total_pages'] = (int) $query->max_num_pages;
					}
					return $result;
				},
			]
		);
	}
);
