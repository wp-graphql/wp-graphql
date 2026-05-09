<?php
/**
 * IDE per-user preferences and personal collections.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Registers user meta exposed to the REST API for the IDE frontend
 * (theme, persist-headers, sort modes, section state, personal
 * collections), plus the helpers that read and clean up that data.
 */
class UserMeta {

	/**
	 * Register user meta fields for IDE preferences.
	 *
	 * These are exposed via the REST API so the IDE frontend can
	 * read and write user preferences with @wordpress/api-fetch.
	 */
	public static function register(): void {
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
				'sanitize_callback' => [ self::class, 'sanitize_personal_collections' ],
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
	public static function sanitize_personal_collections( $value ): array {
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
	public static function aggregate_shared_collections(): array {
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
	public static function purge_document_from_personal_collections( int $post_id, $post ): void {
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
}
