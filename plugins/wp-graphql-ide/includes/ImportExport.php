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
	 * Bump when shipping a new example dataset. The seeder runs only
	 * when the stored `wpgraphql_ide_seed_version` differs from this
	 * value, so users who deleted earlier seeds won't get them
	 * recreated unless we ship a newer set.
	 */
	// phpcs:ignore SlevomatCodingStandard.TypeHints.ClassConstantTypeHint.MissingNativeTypeHint -- Typed class constants require PHP 8.3+; this plugin's floor is 7.4.
	public const SEED_VERSION = '1';

	/**
	 * Wire format version for the import/export JSON. Bump on any
	 * breaking schema change (renamed/removed fields, restructured
	 * collections, etc.). Additive changes don't require a bump.
	 */
	// phpcs:ignore SlevomatCodingStandard.TypeHints.ClassConstantTypeHint.MissingNativeTypeHint -- Typed class constants require PHP 8.3+; this plugin's floor is 7.4.
	public const IMPORT_SCHEMA_VERSION = 1;

	/**
	 * Seed example collections and documents for the activating user.
	 * Idempotent via `wpgraphql_ide_seed_version`.
	 *
	 * Documents are seeded as published with SHA-256 content-addressed
	 * slugs (same algorithm as `handle_publish_document`), so the
	 * activated install matches the canonical example dataset exactly.
	 */
	public static function seed(): void {
		if ( get_option( 'wpgraphql_ide_seed_version' ) === self::SEED_VERSION ) {
			return;
		}

		$author_id = get_current_user_id();
		if ( ! $author_id ) {
			return;
		}

		self::import( self::get_seed_definitions(), $author_id );
		update_option( 'wpgraphql_ide_seed_version', self::SEED_VERSION, false );
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
				'taxonomy'   => 'graphql_ide_collection',
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
					'post_type'      => 'graphql_ide_query',
					'post_status'    => [ 'draft', 'publish' ],
					'author'         => $author_id,
					'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
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
			'version'     => self::IMPORT_SCHEMA_VERSION,
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
	private static function get_seed_definitions(): array {
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
}
