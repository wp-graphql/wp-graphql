<?php
namespace WPGraphQL\Data;

/**
 * Class Config
 *
 * This class contains configurations for various data-related things, such as query filters for cursor pagination.
 *
 * @package WPGraphQL\Data
 */
class Config {

	/**
	 * Config constructor.
	 */
	public function __construct() {

		/**
		 * Filter the term_clauses in the WP_Term_Query to allow for cursor pagination support where a Term ID
		 * can be used as a point of comparison when slicing the results to return.
		 */
		add_filter( 'comments_clauses', [ $this, 'graphql_wp_comments_query_cursor_pagination_support' ], 10, 2 );


		/**
		 * Filter the WP_Query to support cursor based pagination where a post ID can be used
		 * as a point of comparison when slicing the results to return.
		 */
		add_filter( 'posts_where', [ $this, 'graphql_wp_query_cursor_pagination_support' ], 10, 2 );

		/**
		 * Filter the term_clauses in the WP_Term_Query to allow for cursor pagination support where a Term ID
		 * can be used as a point of comparison when slicing the results to return.
		 */
		add_filter( 'terms_clauses', [ $this, 'graphql_wp_term_query_cursor_pagination_support' ], 10, 3 );

	}

	/**
	 * This filters the WPQuery 'where' $args, enforcing the query to return results before or after the
	 * referenced cursor
	 *
	 * @param string    $where The WHERE clause of the query.
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return string
	 */
	public function graphql_wp_query_cursor_pagination_support( $where, \WP_Query $query ) {

		/**
		 * Access the global $wpdb object
		 */
		global $wpdb;

		/**
		 * If there's a graphql_cursor_offset in the query, we should check to see if
		 * it should be applied to the query
		 */
		if ( defined( 'GRAPHQL_REQUEST' ) && GRAPHQL_REQUEST && ! empty( $query->get( 'graphql_cursor_offset' ) ) ) {

			$cursor_offset = $query->get( 'graphql_cursor_offset' );

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare          = $query->get( 'graphql_cursor_compare' );
				$compare          = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';
				$compare_opposite = ( '<' === $compare ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_post = get_post( $cursor_offset );

				/**
				 * If the $cursor_post exists (hasn't been deleted), modify the query to compare based on the ID and post_date values
				 * But if the $cursor_post no longer exists, we're forced to just compare with the ID
				 *
				 */
				if ( ! empty( $cursor_post ) && ! empty( $cursor_post->post_date ) ) {
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date {$compare}= %s AND NOT ( {$wpdb->posts}.post_date {$compare_opposite}= %s AND {$wpdb->posts}.ID {$compare_opposite}= %d )", esc_sql( $cursor_post->post_date ), esc_sql( $cursor_post->post_date ), absint( $cursor_offset ) );
				} else {
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID {$compare} %d", $cursor_offset );
				}
			}
		}

		return $where;

	}

	/**
	 * This filters the term_clauses in the WP_Term_Query to support cursor based pagination, where we can
	 * move forward or backward from a particular record, instead of typical offset pagination which can be
	 * much more expensive and less accurate.
	 *
	 * @param array $pieces     Terms query SQL clauses.
	 * @param array $taxonomies An array of taxonomies.
	 * @param array $args       An array of terms query arguments.
	 *
	 * @return array $pieces
	 */
	public function graphql_wp_term_query_cursor_pagination_support( array $pieces, $taxonomies, $args ) {

		if ( defined( 'GRAPHQL_REQUEST' ) && GRAPHQL_REQUEST && ! empty( $args['graphql_cursor_offset'] ) ) {

			$cursor_offset = $args['graphql_cursor_offset'];

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare          = $args['graphql_cursor_compare'];
				$compare          = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';
				$compare_opposite = ( '<' === $compare ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_term = get_term( $cursor_offset );

				if ( ! empty( $cursor_term ) && ! empty( $cursor_term->name ) ) {
					$pieces['where'] .= sprintf( ' AND t.name %1$s= "%3$s" AND NOT ( t.name %2$s= "%3$s" AND t.term_id %2$s= %4$d )', $compare, $compare_opposite, $cursor_term->name, $cursor_offset );
				} else {
					$pieces['where'] .= sprintf( ' AND t.term_id %1$s %2$d', $compare, $cursor_offset );
				}
			}
		}

		return $pieces;

	}

	/**
	 * This returns a modified version of the $pieces of the comment query clauses if the request is a GRAPHQL_REQUEST
	 * and the query has a graphql_cursor_offset defined
	 *
	 * @param array $pieces A compacted array of comment query clauses.
	 * @param \WP_Comment_Query $comment_query  Current instance of WP_Comment_Query, passed by reference.
	 *
	 * @return array $pieces
	 */
	public function graphql_wp_comments_query_cursor_pagination_support( array $pieces, \WP_Comment_Query $comment_query ) {

		if ( defined( 'GRAPHQL_REQUEST' ) && GRAPHQL_REQUEST ) {

			$cursor_offset = ! empty( $comment_query->query_vars['graphql_cursor_offset'] ) ? $comment_query->query_vars['graphql_cursor_offset'] : null;

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare          = $comment_query->query_vars['graphql_cursor_compare'];
				$compare          = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';
				$compare_opposite = ( '<' === $compare ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_comment = get_comment( $cursor_offset );

				if ( ! empty( $cursor_comment ) && ! empty( $cursor_comment->comment_date ) ) {
					$pieces['where'] .= sprintf( ' AND comment_date %1$s= "%3$s" AND NOT ( comment_date %2$s= "%3$s" AND comment_ID %2$s= %4$d )', $compare, $compare_opposite, esc_html( $cursor_comment->comment_date ), absint( $cursor_offset ) );
				} else {
					$pieces['where'] .= sprintf( ' AND comment_ID %1$s %2$d', $compare, $cursor_offset );
				}
			}
		}

		return $pieces;

	}

}
