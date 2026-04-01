<?php
/**
 * Emit one JSON object per line for graphql-pqc bulk-register variables-jsonl.
 * Run via WP-CLI: BENCH_EMIT=category wp eval-file emit-headless-variables.php
 *
 * Global IDs match WPGraphQL: terms use Relay type "term", users use "user".
 *
 * @package WPGraphQL\PQC\Benchmark
 */

use GraphQLRelay\Relay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GraphQLRelay\\Relay' ) ) {
	WP_CLI::error( 'GraphQLRelay\\Relay not found (WPGraphQL must be loaded).' );
}

$emit = getenv( 'BENCH_EMIT' ) ?: '';

$line = static function ( array $obj ) {
	echo wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
};

switch ( $emit ) {
	case 'front':
		$line( [] );
		break;

	case 'blog':
		$line( [ 'first' => (int) ( getenv( 'BENCH_POSTS_FIRST' ) ?: 100 ) ] );
		break;

	case 'category':
		$lim   = (int) ( getenv( 'BENCH_CAT_LIMIT' ) ?: 50 );
		$terms = get_terms(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => $lim,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			]
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			WP_CLI::warning( 'No categories found; empty jsonl.' );
			break;
		}
		foreach ( $terms as $category_term ) {
			$line( [ 'id' => Relay::toGlobalId( 'term', (string) $category_term->term_id ) ] );
		}
		break;

	case 'tag':
		$lim   = (int) ( getenv( 'BENCH_TAG_LIMIT' ) ?: 50 );
		$terms = get_terms(
			[
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'number'     => $lim,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			]
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			WP_CLI::warning( 'No tags found; empty jsonl.' );
			break;
		}
		foreach ( $terms as $tag_term ) {
			$line( [ 'id' => Relay::toGlobalId( 'term', (string) $tag_term->term_id ) ] );
		}
		break;

	case 'user':
		$lim      = (int) ( getenv( 'BENCH_USER_LIMIT' ) ?: 40 );
		$user_ids = get_users(
			[
				'role'    => 'author',
				'fields'  => 'ID',
				'number'  => $lim,
				'orderby' => 'ID',
				'order'   => 'ASC',
			]
		);
		foreach ( $user_ids as $uid ) {
			$line( [ 'id' => Relay::toGlobalId( 'user', (string) $uid ) ] );
		}
		break;

	case 'uri':
		$lim  = (int) ( getenv( 'BENCH_URI_LIMIT' ) ?: 500 );
		$home = untrailingslashit( (string) home_url() );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- benchmark: sample recent posts for persisted URIs.
		$post_ids_for_uri = get_posts(
			[
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $lim,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		foreach ( $post_ids_for_uri as $post_id_for_uri ) {
			$permalink_full = get_permalink( (int) $post_id_for_uri );
			if ( ! is_string( $permalink_full ) || '' === $permalink_full ) {
				continue;
			}
			$stripped_home = untrailingslashit( $permalink_full );
			$uri_for_query = ( 0 === strpos( $stripped_home, $home ) )
				? substr( $stripped_home, strlen( $home ) )
				: $stripped_home;
			if ( '' === $uri_for_query ) {
				$uri_for_query = '/';
			} elseif ( '/' !== $uri_for_query[0] ) {
				$uri_for_query = '/' . $uri_for_query;
			}
			$line( [ 'uri' => trailingslashit( $uri_for_query ) ] );
		}
		break;

	default:
		WP_CLI::error( 'Set BENCH_EMIT=front|blog|category|tag|user|uri' );
}
