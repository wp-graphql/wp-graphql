<?php

namespace WPGraphQL\Data;

use WPGraphQL\Data\Cursor\PostObjectCursor;
use WPGraphQL\Data\Cursor\UserCursor;

/**
 * Class Config
 *
 * This class contains configurations for various data-related things, such as query filters for
 * cursor pagination.
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
		add_filter(
			'comments_clauses',
			[
				$this,
				'graphql_wp_comments_query_cursor_pagination_support',
			],
			10,
			2
		);

		/**
		 * Filter the WP_Query to support cursor based pagination where a post ID can be used
		 * as a point of comparison when slicing the results to return.
		 */
		add_filter( 'posts_where', [ $this, 'graphql_wp_query_cursor_pagination_support' ], 10, 2 );

		/**
		 * Filter the term_clauses in the WP_Term_Query to allow for cursor pagination support where a Term ID
		 * can be used as a point of comparison when slicing the results to return.
		 */
		add_filter(
			'terms_clauses',
			[
				$this,
				'graphql_wp_term_query_cursor_pagination_support',
			],
			10,
			3
		);

		/**
		 * Filter WP_Query order by add some stability to meta query ordering
		 */
		add_filter(
			'posts_orderby',
			[
				$this,
				'graphql_wp_query_cursor_pagination_stability',
			],
			10,
			2
		);

		if ( ! defined( 'ABSPATH' ) ) {
			exit;
		}

		/**
		 * Copied from https://github.com/wp-graphql/wp-graphql/issues/274#issuecomment-510150571
		 * Shoutouts to epeli!
		 *
		 * Add missing filters to WP_User_Query class.
		 */
		add_filter(
			'pre_user_query',
			function ( $query ) {

				if ( ! $query->get( 'suppress_filters' ) ) {
					$query->set( 'suppress_filters', 0 );
				}

				if ( ! $query->get( 'suppress_filters' ) ) {

					/**
					 * Filters the WHERE clause of the query.
					 *
					 * Specifically for manipulating paging queries.
					 **
					*
					* @param string $where The WHERE clause of the query.
					* @param WP_User_Query $query The WP_User_Query instance (passed by reference).
					*/
					$query->query_where = apply_filters_ref_array( 'graphql_users_where', [ $query->query_where, &$query ] );

					/**
					 * Filters the ORDER BY clause of the query.
					 *
					 * @param string $orderby The ORDER BY clause of the query.
					 * @param WP_User_Query $query The WP_User_Query instance (passed by reference).
					 */
					$query->query_orderby = apply_filters_ref_array( 'graphql_users_orderby', [ $query->query_orderby, &$query ] );

				}

				return $query;

			}
		);

		/**
		 * Filter the WP_User_Query to support cursor based pagination where a user ID can be used
		 * as a point of comparison when slicing the results to return.
		 */
		add_filter(
			'graphql_users_where',
			[
				$this,
				'graphql_wp_user_query_cursor_pagination_support',
			],
			10,
			2
		);

		/**
		 * Filter WP_User_Query order by add some stability to meta query ordering
		 */
		add_filter(
			'graphql_users_orderby',
			[
				$this,
				'graphql_wp_user_query_cursor_pagination_stability',
			],
			10,
			2
		);

	}

	/**
	 * When posts are ordered by a meta query the order might be random when
	 * the meta values have same values multiple times. This filter adds a
	 * secondary ordering by the post ID which forces stable order in such cases.
	 *
	 * @param string $orderby The ORDER BY clause of the query.
	 *
	 * @return string
	 */
	public function graphql_wp_query_cursor_pagination_stability( $orderby ) {
		if ( true === is_graphql_request() ) {

			global $wpdb;

			return "{$orderby}, {$wpdb->posts}.ID DESC ";
		}

		return $orderby;
	}

	/**
	 * This filters the WPQuery 'where' $args, enforcing the query to return results before or
	 * after the referenced cursor
	 *
	 * @param string    $where The WHERE clause of the query.
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return string
	 */
	public function graphql_wp_query_cursor_pagination_support( $where, \WP_Query $query ) {

		/**
		 * If there's a graphql_cursor_offset in the query, we should check to see if
		 * it should be applied to the query
		 */
		if ( true === is_graphql_request() ) {
			$post_cursor = new PostObjectCursor( $query );

			return $where . $post_cursor->get_where();
		}

		return $where;
	}

		/**
		 * When users are ordered by a meta query the order might be random when
		 * the meta values have same values multiple times. This filter adds a
		 * secondary ordering by the post ID which forces stable order in such cases.
		 *
		 * @param string $orderby The ORDER BY clause of the query.
		 *
		 * @return string
		 */
	public function graphql_wp_user_query_cursor_pagination_stability( $orderby ) {

		if ( true === is_graphql_request() ) {
			global $wpdb;

			// phpcs:ignore
			return "{$orderby}, {$wpdb->users}.ID DESC ";
		}

		return $orderby;
	}


	/**
	 * This filters the WP_User_Query 'where' $args, enforcing the query to return results before or
	 * after the referenced cursor
	 *
	 * @param string         $where The WHERE clause of the query.
	 * @param \WP_User_Query $query The WP_User_Query instance (passed by reference).
	 *
	 * @return string
	 */
	public function graphql_wp_user_query_cursor_pagination_support( $where, \WP_User_Query $query ) {

		/**
		 * If there's a graphql_cursor_offset in the query, we should check to see if
		 * it should be applied to the query
		 */
		if ( true === is_graphql_request() ) {
			$user_cursor = new UserCursor( $query );
			return $where . $user_cursor->get_where();
		}

		return $where;
	}


	/**
	 * This filters the term_clauses in the WP_Term_Query to support cursor based pagination, where
	 * we can move forward or backward from a particular record, instead of typical offset
	 * pagination which can be much more expensive and less accurate.
	 *
	 * @param array $pieces     Terms query SQL clauses.
	 * @param array $taxonomies An array of taxonomies.
	 * @param array $args       An array of terms query arguments.
	 *
	 * @return array $pieces
	 */
	public function graphql_wp_term_query_cursor_pagination_support( array $pieces, $taxonomies, $args ) {

		/**
		 * Access the global $wpdb object
		 */
		global $wpdb;

		if ( true === is_graphql_request() && ! empty( $args['graphql_cursor_offset'] ) ) {

			$cursor_offset = $args['graphql_cursor_offset'];

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare = ! empty( $args['graphql_cursor_compare'] ) ? $args['graphql_cursor_compare'] : '>';
				$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

				$order_by      = ! empty( $args['orderby'] ) ? $args['orderby'] : 'comment_date';
				$order         = ! empty( $args['order'] ) ? $args['order'] : 'DESC';
				$order_compare = ( 'ASC' === $order ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_term = get_term( $cursor_offset );

				if ( ! empty( $cursor_term ) && ! empty( $cursor_term->name ) ) {
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$pieces['where'] .= $wpdb->prepare( " AND t.{$order_by} {$order_compare} %s", $cursor_term->{$order_by} );
				} else {
					// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
					$pieces['where'] .= $wpdb->prepare( ' AND t.term_id %1$s %2$d', $compare, $cursor_offset );
				}
			}
		}

		return $pieces;

	}

	/**
	 * This returns a modified version of the $pieces of the comment query clauses if the request
	 * is a GraphQL Request and the query has a graphql_cursor_offset defined
	 *
	 * @param array             $pieces A compacted array of comment query clauses.
	 * @param \WP_Comment_Query $query  Current instance of WP_Comment_Query, passed by reference.
	 *
	 * @return array $pieces
	 */
	public function graphql_wp_comments_query_cursor_pagination_support( array $pieces, \WP_Comment_Query $query ) {

		/**
		 * Access the global $wpdb object
		 */
		global $wpdb;

		if (
			true === is_graphql_request() &&
			( is_array( $query->query_vars ) && array_key_exists( 'graphql_cursor_offset', $query->query_vars ) )
		) {

			$cursor_offset = $query->query_vars['graphql_cursor_offset'];

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare = ! empty( $query->get( 'graphql_cursor_compare' ) ) ? $query->get( 'graphql_cursor_compare' ) : '>';
				$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

				$order_by      = ! empty( $query->query_vars['order_by'] ) ? $query->query_vars['order_by'] : 'comment_date';
				$order         = ! empty( $query->query_vars['order'] ) ? $query->query_vars['order'] : 'DESC';
				$order_compare = ( 'ASC' === $order ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_comment = get_comment( $cursor_offset );
				if ( ! empty( $cursor_comment ) ) {
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$pieces['where'] .= $wpdb->prepare( " AND {$order_by} {$order_compare} %s", $cursor_comment->{$order_by} );
				} else {
					// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
					$pieces['where'] .= $wpdb->prepare( ' AND comment_ID %1$s %2$d', $compare, $cursor_offset );
				}
			}
		}

		return $pieces;

	}

}
