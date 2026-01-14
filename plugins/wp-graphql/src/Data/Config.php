<?php

namespace WPGraphQL\Data;

use WPGraphQL\Data\Cursor\CommentObjectCursor;
use WPGraphQL\Data\Cursor\PostObjectCursor;
use WPGraphQL\Data\Cursor\TermObjectCursor;
use WPGraphQL\Data\Cursor\UserCursor;
use WP_Comment_Query;
use WP_Query;

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
			static function ( $query ) {
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
					 * @param string        $where The WHERE clause of the query.
					 * @param \WPGraphQL\Data\WP_User_Query $query The WP_User_Query instance (passed by reference).
					 */
					$query->query_where = apply_filters_ref_array(
						'graphql_users_where',
						[
							$query->query_where,
							&$query,
						]
					);

					/**
					 * Filters the ORDER BY clause of the query.
					 *
					 * @param string        $orderby The ORDER BY clause of the query.
					 * @param \WPGraphQL\Data\WP_User_Query $query The WP_User_Query instance (passed by reference).
					 */
					$query->query_orderby = apply_filters_ref_array(
						'graphql_users_orderby',
						[
							$query->query_orderby,
							&$query,
						]
					);
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
	 * When posts are ordered by fields that have duplicate values, we need to consider
	 * another field to "stabilize" the query order. We use IDs as they're always unique.
	 *
	 * This allows for posts with the same title or same date or same meta value to exist
	 * and for their cursors to properly go forward/backward to the proper place in the database.
	 *
	 * @param string    $orderby  The ORDER BY clause of the query.
	 * @param \WP_Query $query    The WP_Query instance executing.
	 *
	 * @return string
	 */
	public function graphql_wp_query_cursor_pagination_stability( string $orderby, WP_Query $query ) {
		// Bail early if it's not a GraphQL Request.
		if ( true !== is_graphql_request() ) {
			return $orderby;
		}

		/**
		 * If pre-filter hooked, return $pre_orderby.
		 *
		 * @param string|null $pre_orderby The pre-filtered ORDER BY clause of the query.
		 * @param string      $orderby     The ORDER BY clause of the query.
		 * @param \WP_Query   $query       The WP_Query instance (passed by reference).
		 *
		 * @return string|null
		 */
		$pre_orderby = apply_filters( 'graphql_pre_wp_query_cursor_pagination_stability', null, $orderby, $query );
		if ( null !== $pre_orderby ) {
			return $pre_orderby;
		}

		// Bail early if disabled by connection.
		if ( isset( $query->query_vars['graphql_apply_cursor_pagination_orderby'] )
			&& false === $query->query_vars['graphql_apply_cursor_pagination_orderby'] ) {
			return $orderby;
		}

		// Bail early if the cursor "graphql_cursor_compare" arg is not in the query,
		if ( ! isset( $query->query_vars['graphql_cursor_compare'] ) ) {
			return $orderby;
		}

		// Check the cursor compare order
		$order = '>' === $query->query_vars['graphql_cursor_compare'] ? 'ASC' : 'DESC';

		// Get Cursor ID key.
		$cursor = new PostObjectCursor( $query->query_vars );
		$key    = $cursor->get_cursor_id_key();

		// If there is a cursor compare in the arguments, use it as the stablizer for cursors.
		return ( $orderby ? "{$orderby}, " : '' ) . "{$key} {$order}";
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
	public function graphql_wp_query_cursor_pagination_support( string $where, WP_Query $query ) {
		// Bail early if it's not a GraphQL Request.
		if ( true !== is_graphql_request() ) {
			return $where;
		}

		/**
		 * If pre-filter hooked, return $pre_where.
		 *
		 * @param string|null $pre_where The pre-filtered WHERE clause of the query.
		 * @param string     $where     The WHERE clause of the query.
		 * @param \WP_Query  $query     The WP_Query instance (passed by reference).
		 *
		 * @return string|null
		 */
		$pre_where = apply_filters( 'graphql_pre_wp_query_cursor_pagination_support', null, $where, $query );
		if ( null !== $pre_where ) {
			return $pre_where;
		}

		// Bail early if disabled by connection.
		if ( isset( $query->query_vars['graphql_apply_cursor_pagination_where'] )
			&& false === $query->query_vars['graphql_apply_cursor_pagination_where'] ) {
			return $where;
		}

		// Apply the after cursor, moving forward through results
		if ( ! empty( $query->query_vars['graphql_after_cursor'] ) ) {
			$after_cursor = new PostObjectCursor( $query->query_vars, 'after' );
			$where       .= $after_cursor->get_where();
		}

		// Apply the after cursor, moving backward through results.
		if ( ! empty( $query->query_vars['graphql_before_cursor'] ) ) {
			$before_cursor = new PostObjectCursor( $query->query_vars, 'before' );
			$where        .= $before_cursor->get_where();
		}

		return $where;
	}

	/**
	 * When users are ordered by a meta query the order might be random when
	 * the meta values have same values multiple times. This filter adds a
	 * secondary ordering by the post ID which forces stable order in such cases.
	 *
	 * @param string         $orderby The ORDER BY clause of the query.
	 * @param \WP_User_Query $query The WP_User_Query instance (passed by reference).
	 *
	 * @return string
	 */
	public function graphql_wp_user_query_cursor_pagination_stability( $orderby, \WP_User_Query $query ) {

		// Bail early if it's not a GraphQL Request.
		if ( true !== is_graphql_request() ) {
			return $orderby;
		}

		/**
		 * If pre-filter hooked, return $pre_orderby.
		 *
		 * @param string|null     $pre_orderby The pre-filtered ORDER BY clause of the query.
		 * @param string          $orderby     The ORDER BY clause of the query.
		 * @param \WP_User_Query  $query       The WP_User_Query instance (passed by reference).
		 *
		 * @return string|null
		 */
		$pre_orderby = apply_filters( 'graphql_pre_wp_user_query_cursor_pagination_stability', null, $orderby, $query );
		if ( null !== $pre_orderby ) {
			return $pre_orderby;
		}

		// Bail early if disabled by connection.
		if ( isset( $query->query_vars['graphql_apply_cursor_pagination_orderby'] )
			&& false === $query->query_vars['graphql_apply_cursor_pagination_orderby'] ) {
			return $orderby;
		}

		// Bail early if the cursor "graphql_cursor_compare" arg is not in the query,
		if ( ! isset( $query->query_vars['graphql_cursor_compare'] ) ) {
			return $orderby;
		}

		// Check the cursor compare order
		$order = '>' === $query->query_vars['graphql_cursor_compare'] ? 'ASC' : 'DESC';

		// Get Cursor ID key.
		$cursor = new UserCursor( $query->query_vars );
		$key    = $cursor->get_cursor_id_key();

		return ( $orderby ? "{$orderby}, " : '' ) . "{$key} {$order}";
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

		// Bail early if it's not a GraphQL Request.
		if ( true !== is_graphql_request() ) {
			return $where;
		}

		/**
		 * If pre-filter hooked, return $pre_where.
		 *
		 * @param string|null    $pre_where The pre-filtered WHERE clause of the query.
		 * @param string         $where     The WHERE clause of the query.
		 * @param \WP_User_Query $query     The WP_Query instance (passed by reference).
		 *
		 * @return string|null
		 */
		$pre_where = apply_filters( 'graphql_pre_wp_user_query_cursor_pagination_support', null, $where, $query );
		if ( null !== $pre_where ) {
			return $pre_where;
		}

		// Bail early if disabled by connection.
		if ( isset( $query->query_vars['graphql_apply_cursor_pagination_where'] )
			&& false === $query->query_vars['graphql_apply_cursor_pagination_where'] ) {
			return $where;
		}

		// Apply the after cursor.
		if ( ! empty( $query->query_vars['graphql_after_cursor'] ) ) {
			$after_cursor = new UserCursor( $query->query_vars, 'after' );
			$where        = $where . $after_cursor->get_where();
		}

		// Apply the after cursor.
		if ( ! empty( $query->query_vars['graphql_before_cursor'] ) ) {
			$before_cursor = new UserCursor( $query->query_vars, 'before' );
			$where         = $where . $before_cursor->get_where();
		}

		return $where;
	}

	/**
	 * This filters the term_clauses in the WP_Term_Query to support cursor based pagination, where
	 * we can move forward or backward from a particular record, instead of typical offset
	 * pagination which can be much more expensive and less accurate.
	 *
	 * @param array<string,mixed> $pieces     Terms query SQL clauses.
	 * @param string[]            $taxonomies An array of taxonomies.
	 * @param array<string,mixed> $args       An array of terms query arguments.
	 *
	 * @return array<string,mixed> $pieces
	 */
	public function graphql_wp_term_query_cursor_pagination_support( array $pieces, array $taxonomies, array $args ) {

		// Bail early if it's not a GraphQL Request.
		if ( true !== is_graphql_request() ) {
			return $pieces;
		}

		/**
		 * If pre-filter hooked, return $pre_pieces.
		 *
		 * @param ?array<string,mixed> $pre_pieces The pre-filtered term query SQL clauses.
		 * @param array<string,mixed>  $pieces     Terms query SQL clauses.
		 * @param string[]             $taxonomies An array of taxonomies.
		 * @param array<string,mixed>  $args       An array of terms query arguments.
		 */
		$pre_pieces = apply_filters( 'graphql_pre_wp_term_query_cursor_pagination_support', null, $pieces, $taxonomies, $args );
		if ( null !== $pre_pieces ) {
			return $pre_pieces;
		}

		// Bail early if disabled by connection.
		if ( isset( $args['graphql_apply_cursor_pagination_where'] )
			&& false === $args['graphql_apply_cursor_pagination_where'] ) {
			return $pieces;
		}

		// Bail early if the cursor "graphql_cursor_compare" arg is not in the query,
		if ( ! isset( $args['graphql_cursor_compare'] ) ) {
			return $pieces;
		}

		// Determine the limit for the query
		if ( isset( $args['number'] ) && absint( $args['number'] ) ) {
			$pieces['limits'] = sprintf( ' LIMIT 0, %d', absint( $args['number'] ) );
		}

		// Apply the after cursor.
		if ( ! empty( $args['graphql_after_cursor'] ) ) {
			$after_cursor    = new TermObjectCursor( $args, 'after' );
			$pieces['where'] = $pieces['where'] . $after_cursor->get_where();
		}

		// Apply the before cursor.
		if ( ! empty( $args['graphql_before_cursor'] ) ) {
			$before_cursor   = new TermObjectCursor( $args, 'before' );
			$pieces['where'] = $pieces['where'] . $before_cursor->get_where();
		}

		// Check the cursor compare order.
		$order = '>' === $args['graphql_cursor_compare'] ? 'ASC' : 'DESC';

		// Get Cursor ID key.
		$cursor = new TermObjectCursor( $args );
		$key    = $cursor->get_cursor_id_key();

		// If there is a cursor compare in the arguments, use it as the stabilizer for cursors.
		if ( ! empty( $pieces['orderby'] ) ) {
			$pieces['orderby'] = "{$pieces['orderby']} {$pieces['order']}, {$key} {$order}";
		} else {
			$pieces['orderby'] = "ORDER BY {$key} {$order}";
		}

		$pieces['order'] = '';

		return $pieces;
	}

	/**
	 * This returns a modified version of the $pieces of the comment query clauses if the request
	 * is a GraphQL Request and before or after cursors are passed to the query
	 *
	 * @param array<string,mixed> $pieces A compacted array of comment query clauses.
	 * @param \WP_Comment_Query   $query Current instance of WP_Comment_Query, passed by reference.
	 *
	 * @return array<string,mixed> $pieces
	 */
	public function graphql_wp_comments_query_cursor_pagination_support( array $pieces, WP_Comment_Query $query ) {

		// Bail early if it's not a GraphQL Request.
		if ( true !== is_graphql_request() ) {
			return $pieces;
		}

		/**
		 * If pre-filter hooked, return $pre_pieces.
		 *
		 * @param ?array<string,mixed> $pre_pieces The pre-filtered comment query clauses.
		 * @param array<string,mixed>  $pieces     A compacted array of comment query clauses.
		 * @param \WP_Comment_Query    $query      Current instance of WP_Comment_Query, passed by reference.
		 */
		$pre_pieces = apply_filters( 'graphql_pre_wp_comments_query_cursor_pagination_support', null, $pieces, $query );
		if ( null !== $pre_pieces ) {
			return $pre_pieces;
		}

		// Bail early if disabled by connection.
		if ( isset( $query->query_vars['graphql_apply_cursor_pagination_where'] )
			&& false === $query->query_vars['graphql_apply_cursor_pagination_where'] ) {
			return $pieces;
		}

		// Apply the after cursor, moving forward through results.
		if ( ! empty( $query->query_vars['graphql_after_cursor'] ) ) {
			$after_cursor     = new CommentObjectCursor( $query->query_vars, 'after' );
			$pieces['where'] .= $after_cursor->get_where();
		}

		// Apply the after cursor, moving backward through results.
		if ( ! empty( $query->query_vars['graphql_before_cursor'] ) ) {
			$before_cursor    = new CommentObjectCursor( $query->query_vars, 'before' );
			$pieces['where'] .= $before_cursor->get_where();
		}

		return $pieces;
	}
}
