<?php
/**
 * Import / export / seed for IDE collections + documents.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Owns the JSON wire format used by the activation seeder, the
 * REST `/import` and `/export` handlers, and the canonical example
 * dataset shipped in `seeds/example-documents.json`.
 */
class ImportExport {

	/**
	 * Wire format version for the import/export JSON. Bump on any
	 * breaking schema change (renamed/removed fields, restructured
	 * collections, etc.). Additive changes don't require a bump.
	 */
	// phpcs:ignore SlevomatCodingStandard.TypeHints.ClassConstantTypeHint.MissingNativeTypeHint -- Typed class constants require PHP 8.3+; this plugin's floor is 7.4.
	public const IMPORT_SCHEMA_VERSION = 1;

	/**
	 * Import a `{ collections: [...] }` payload as documents owned by the
	 * given user. Idempotent for published docs (SHA-256 dedup); drafts
	 * are always created fresh (drafts are mutable working copies).
	 *
	 * @param array<string,mixed> $data       Payload matching the seed JSON schema.
	 * @param int                 $author_id  Owner of imported documents.
	 * @return array{created: int, skipped: int, collections: array<int,int>}
	 */
	public static function import( array $data, int $author_id ): array {
		// Treat a missing version as the current schema so legacy / un-versioned
		// payloads (including the very first seed file) still import cleanly.
		$version = isset( $data['version'] ) ? (int) $data['version'] : self::IMPORT_SCHEMA_VERSION;
		if ( self::IMPORT_SCHEMA_VERSION !== $version ) {
			return [
				'created'     => 0,
				'skipped'     => 0,
				'collections' => [],
				'error'       => sprintf(
					/* translators: 1: payload version, 2: supported version */
					__( 'Unsupported import schema version %1$d (this build expects version %2$d).', 'wpgraphql-ide' ),
					$version,
					self::IMPORT_SCHEMA_VERSION
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

			$term = term_exists( $name, 'graphql_document_group' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'graphql_document_group' );
			}
			if ( is_wp_error( $term ) || empty( $term['term_id'] ) ) {
				continue;
			}

			$term_id    = (int) $term['term_id'];
			$term_ids[] = $term_id;

			foreach ( $docs as $doc ) {
				$result = self::upsert( $doc, $term_id, $author_id );
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
	 * Normalizes + hashes every document (not just publishes) and looks
	 * the hash up against Smart Cache's `graphql_query_alias` taxonomy
	 * (or post_name, when Smart Cache isn't loaded) so a content-duplicate
	 * import attaches to the existing post instead of triggering Smart
	 * Cache's collision-throw inside `save_post_graphql_document`.
	 *
	 * @param array<string,mixed> $doc       Document payload.
	 * @param int                 $term_id   Collection term ID to attach.
	 * @param int                 $author_id Owner.
	 * @return 'created'|'skipped'|'error'
	 */
	private static function upsert( array $doc, int $term_id, int $author_id ): string {
		$query = isset( $doc['query'] ) ? (string) $doc['query'] : '';
		if ( '' === trim( $query ) ) {
			return 'error';
		}

		$status = ( $doc['status'] ?? 'publish' ) === 'draft' ? 'draft' : 'publish';
		$title  = isset( $doc['title'] ) && '' !== $doc['title'] ? (string) $doc['title'] : __( 'Untitled', 'wpgraphql-ide' );

		try {
			$ast  = \GraphQL\Language\Parser::parse( $query );
			$body = \GraphQL\Language\Printer::doPrint( $ast );
			$slug = hash( 'sha256', $body );
		} catch ( \Throwable $e ) {
			return 'error';
		}

		$existing = self::find_existing_by_hash( $slug );
		if ( $existing ) {
			wp_set_object_terms( $existing, [ $term_id ], 'graphql_document_group', true );
			return 'skipped';
		}

		$postarr = [
			'post_type'    => 'graphql_document',
			'post_status'  => $status,
			'post_author'  => $author_id,
			'post_title'   => $title,
			'post_content' => $body,
		];
		// Publishes are content-addressed at the slug layer so direct
		// REST reads / Smart Cache's get-by-queryId machinery resolve
		// without a taxonomy lookup. Drafts keep WP's title-derived slug.
		if ( 'publish' === $status ) {
			$postarr['post_name'] = $slug;
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'error';
		}

		wp_set_object_terms( $post_id, [ $term_id ], 'graphql_document_group' );

		if ( ! empty( $doc['variables'] ) ) {
			update_post_meta( $post_id, '_graphql_ide_variables', (string) $doc['variables'] );
		}
		if ( ! empty( $doc['headers'] ) ) {
			update_post_meta( $post_id, '_graphql_ide_headers', (string) $doc['headers'] );
		}

		return 'created';
	}

	/**
	 * Resolve an existing `graphql_document` for the given content hash.
	 *
	 * Smart Cache stamps the sha256 of every saved doc's normalized
	 * content onto the `graphql_query_alias` taxonomy term — that's
	 * the authoritative cross-status identity. Fall back to post_name
	 * for environments where Smart Cache isn't loaded; the IDE still
	 * sets `post_name = $slug` on its own publishes, so the fallback
	 * keeps publish-to-publish dedup honest.
	 *
	 * @param string $slug sha256 hash of the normalized query.
	 * @return int|null Existing post ID, or null if no match.
	 */
	private static function find_existing_by_hash( string $slug ): ?int {
		if ( taxonomy_exists( 'graphql_query_alias' ) ) {
			$by_term = get_posts(
				[
					'post_type'      => 'graphql_document',
					'post_status'    => [ 'publish', 'draft' ],
					'posts_per_page' => 1,
					'fields'         => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'tax_query'      => [
						[
							'taxonomy' => 'graphql_query_alias',
							'field'    => 'name',
							'terms'    => $slug,
						],
					],
				]
			);
			if ( ! empty( $by_term ) ) {
				return (int) $by_term[0];
			}
		}

		$by_slug = get_posts(
			[
				'post_type'      => 'graphql_document',
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		return ! empty( $by_slug ) ? (int) $by_slug[0] : null;
	}

	/**
	 * Build an export payload — given user's documents grouped by
	 * collection. Documents not assigned to any collection are skipped so
	 * the export round-trips through the importer cleanly.
	 *
	 * @param int $author_id Owner whose documents to export.
	 * @return array{version:int, collections: array<int,array{name:string,documents:array<int,array<string,mixed>>}>}
	 */
	public static function export( int $author_id ): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'graphql_document_group',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [
				'version'     => self::IMPORT_SCHEMA_VERSION,
				'collections' => [],
			];
		}

		$collections = [];

		foreach ( $terms as $term ) {
			$post_ids = get_posts(
				[
					'post_type'      => 'graphql_document',
					'post_status'    => [ 'draft', 'publish' ],
					'author'         => $author_id,
					'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						[
							'taxonomy' => 'graphql_document_group',
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
			'version'     => self::IMPORT_SCHEMA_VERSION,
			'collections' => $collections,
		];
	}

}
