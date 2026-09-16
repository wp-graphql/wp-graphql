<?php
/**
 * WP-CLI commands for saved GraphQL documents.
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Document;

/**
 * Audit and remove automatically registered GraphQL documents.
 *
 * ## EXAMPLES
 *
 *     # List automatically registered documents and flag unverified alias names
 *     $ wp graphql smart-cache documents audit
 *
 *     # Delete automatically registered documents, keeping granted, denied, or grouped ones
 *     $ wp graphql smart-cache documents purge --yes
 */
// phpcs:ignore WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli -- WPCOM_VIP_CLI_Command is not available outside VIP.
class DocumentsCommand extends \WP_CLI_Command {

	/**
	 * List automatically registered documents.
	 *
	 * ## OPTIONS
	 *
	 * [--unverified]
	 * : Only list documents carrying an alias name not derived from the document hash.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - ids
	 *   - count
	 * ---
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function audit( $args, $assoc_args ) {
		$unverified_only = ! empty( $assoc_args['unverified'] );
		$format          = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$ids             = Audit::get_automatic_document_ids( $unverified_only );

		if ( 'count' === $format ) {
			\WP_CLI::line( (string) count( $ids ) );
			return;
		}
		if ( 'ids' === $format ) {
			\WP_CLI::line( implode( ' ', $ids ) );
			return;
		}

		$rows = [];
		foreach ( $ids as $id ) {
			$post   = get_post( $id );
			$terms  = get_the_terms( $id, Document::ALIAS_TAXONOMY_NAME );
			$rows[] = [
				'ID'                 => $id,
				'title'              => $post instanceof \WP_Post ? $post->post_title : '',
				'author'             => $post instanceof \WP_Post ? (int) $post->post_author : 0,
				'curated'            => Audit::is_curated( $id ) ? 'yes' : 'no',
				'unverified_aliases' => implode( ', ', Audit::get_unverified_aliases( $id ) ),
				'aliases'            => is_array( $terms ) ? count( $terms ) : 0,
				'modified'           => $post instanceof \WP_Post ? $post->post_modified_gmt : '',
			];
		}

		\WP_CLI\Utils\format_items( $format, $rows, [ 'ID', 'title', 'author', 'curated', 'unverified_aliases', 'aliases', 'modified' ] );
	}

	/**
	 * Delete automatically registered documents.
	 *
	 * Deleting them is safe: a client that still uses one re-sends the full query
	 * on its next request and the document is registered again under its
	 * verified hash.
	 *
	 * ## OPTIONS
	 *
	 * [--include-curated]
	 * : Also delete documents an administrator granted, denied, or placed in a group.
	 *
	 * [--dry-run]
	 * : Report what would be deleted without deleting.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function purge( $args, $assoc_args ) {
		$include_curated = ! empty( $assoc_args['include-curated'] );
		$ids             = Audit::get_automatic_document_ids( false );

		if ( ! $include_curated ) {
			$ids = array_values( array_filter( $ids, static function ( $id ) {
				return ! Audit::is_curated( $id );
			} ) );
		}

		if ( empty( $ids ) ) {
			\WP_CLI::success( 'No automatically registered documents to delete.' );
			return;
		}

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			\WP_CLI::log( sprintf( 'Would delete %d document(s): %s', count( $ids ), implode( ', ', $ids ) ) );
			return;
		}

		\WP_CLI::confirm( sprintf( 'Delete %d automatically registered document(s)?', count( $ids ) ), $assoc_args );

		$result = Audit::purge( $include_curated, Audit::BATCH_SIZE, PHP_INT_MAX );

		\WP_CLI::success( sprintf( 'Deleted %d document(s), kept %d curated, %d remaining.', $result['deleted'], $result['skipped'], $result['remaining'] ) );
	}
}
