<?php

namespace WPGraphQL\Type\TermObject\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;
use WPGraphQL\Data\ConnectionResolver;
use WPGraphQL\Types;

/**
 * Class TermObjectConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since   0.0.5
 */
class TermObjectConnectionResolver extends ConnectionResolver {

	/**
	 * Stores the name of the taxonomy for the connection being resolved
	 *
	 * @var string $taxonomy
	 */
	public static $taxonomy;

	/**
	 * TermObjectConnectionResolver constructor.
	 *
	 * @param $taxonomy
	 */
	public function __construct( $taxonomy ) {
		self::$taxonomy = $taxonomy;
	}

	/**
	 * Returns an array of query_args to use in the WP_Term_Query to fetch the necessary terms for the connection
	 *
	 * @param             $source
	 * @param array       $args
	 * @param AppContext  $context
	 * @param ResolveInfo $info
	 *
	 * @return array
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Set the taxonomy for the $args
		 */
		$query_args['taxonomy'] = ! empty( self::$taxonomy ) ? self::$taxonomy : 'category';

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $args;

		/**
		 * Set the graphql_cursor_offset
		 */
		$query_args['graphql_cursor_offset'] = self::get_offset( $args );

		/**
		 * Set the cursor compare direction
		 */
		if ( ! empty( $args['before'] ) ) {
			$query_args['graphql_cursor_compare'] = '<';
		} elseif ( ! empty( $args['after'] ) ) {
			$query_args['graphql_cursor_compare'] = '>';
		}

		/**
		 * Orderby Name by default
		 */
		$query_args['orderby'] = 'count';

		/**
		 * Set the order
		 */
		$order               = ! empty( $query_args['graphql_cursor_compare'] ) && '>' === $query_args['graphql_cursor_compare'] ? 'ASC' : 'DESC';
		$query_args['order'] = $order;

		/**
		 * Take any of the $args that were part of the GraphQL query and map their GraphQL names to
		 * the WP_Term_Query names to be used in the WP_Term_Query
		 *
		 * @since 0.0.5
		 */
		$input_fields = [];
		if ( ! empty( $args['where'] ) ) {
			$input_fields = self::sanitize_input_fields( $args['where'], $source, $args, $context, $info );
		}

		/**
		 * Merge the default $query_args with the $args that were entered in the query.
		 *
		 * @since 0.0.5
		 */
		$query_args = array_merge( $query_args, $input_fields );


		/**
		 * If the source of the Query is a Post object, adjust the query args to only query terms
		 * connected to the post object
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Post ) {
			$query_args['object_ids'] = $source->ID;
		}

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$pagination_increase  = ! empty( $args['first'] ) && ( empty( $args['after'] ) && empty( $args['before'] ) ) ? 0 : 1;
		$query_args['number'] = self::get_query_amount( $source, $args, $context, $info ) + $pagination_increase;

		return $query_args;

	}

	/**
	 * This runs the query and returns the response
	 *
	 * @param $query_args
	 *
	 * @return \WP_Term_Query
	 */
	public static function get_query( $query_args ) {
		$query = new \WP_Term_Query( $query_args );


		return $query;
	}

	/**
	 * This gets the connection to return
	 *
	 * @param array|mixed $query       The query that was processed to get data
	 * @param array       $array_slice The array slice that was returned
	 * @param mixed       $source      The source being passed down the resolve tress
	 * @param array       $args        The input args for the resolving field
	 * @param AppContext  $context     The context being passed down the resolve tree
	 * @param ResolveInfo $info        The ResolveInfo passed down the resolve tree
	 *
	 * @return array
	 */
	public static function get_connection( $query, array $array_slice, $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Get the $posts from the query
		 */
		$items = ! empty( $items ) && is_array( $items ) ? $items : [];

		/**
		 * Set whether there is or is not another page
		 */
		$has_previous_page = ( ! empty( $args['last'] ) && count( $items ) > self::get_amount_requested( $args ) ) ? true : false;
		$has_next_page     = ( ! empty( $args['first'] ) && count( $items ) > self::get_amount_requested( $args ) ) ? true : false;

		/**
		 * Slice the array to the amount of items that were requested
		 */
		$items = array_slice( $items, 0, self::get_amount_requested( $args ) );
		$items = array_reverse( $items );

		/**
		 * Get the edges from the $items
		 */
		$edges = self::get_edges( $items );

		/**
		 * Find the first_edge and last_edge
		 */
		$first_edge = $edges ? $edges[0] : null;
		$last_edge  = $edges ? $edges[ count( $edges ) - 1 ] : null;

		/**
		 * Create the connection to return
		 */
		$connection = [
			'edges'    => $edges,
			'debug'    => [
				'queryRequest' => ! empty( $query->request ) ? $query->request : null,
			],
			'pageInfo' => [
				'hasPreviousPage' => $has_previous_page,
				'hasNextPage'     => $has_next_page,
				'startCursor'     => ! empty( $first_edge['cursor'] ) ? $first_edge['cursor'] : null,
				'endCursor'       => ! empty( $last_edge['cursor'] ) ? $last_edge['cursor'] : null,
			],
		];

		return $connection;

	}

	/**
	 * Takes an array of items and returns the edges
	 *
	 * @param $items
	 *
	 * @return array
	 */
	public static function get_edges( $items ) {
		$edges = [];
		if ( ! empty( $items ) && is_array( $items ) ) {
			foreach ( $items as $item ) {
				$edges[] = [
					'cursor' => ArrayConnection::offsetToCursor( $item->term_id ),
					'node'   => $item,
				];
			}
		}

		return $edges;
	}

	/**
	 * This maps the GraphQL "friendly" args to get_terms $args.
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be down
	 * to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array       $args     Array of query "where" args
	 * @param mixed       $source   The query results
	 * @param array       $all_args All of the query arguments (not just the "where" args)
	 * @param AppContext  $context  The AppContext object
	 * @param ResolveInfo $info     The ResolveInfo object
	 *
	 * @since  0.0.5
	 * @return array
	 * @access public
	 */
	public static function sanitize_input_fields( array $args, $source, array $all_args, AppContext $context, ResolveInfo $info ) {

		$arg_mapping = [
			'objectIds'           => 'object_ids',
			'hideEmpty'           => 'hide_empty',
			'excludeTree'         => 'exclude_tree',
			'termTaxonomId'       => 'term_taxonomy_id',
			'nameLike'            => 'name__like',
			'descriptionLike'     => 'description__like',
			'padCounts'           => 'pad_counts',
			'childOf'             => 'child_of',
			'cacheDomain'         => 'cacheDomain',
			'updateTermMetaCache' => 'update_term_meta_cache',
		];

		/**
		 * Map and sanitize the input args to the WP_Term_Query compatible args
		 */
		$query_args = Types::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @param array       $query_args Array of mapped query args
		 * @param array       $args       Array of query "where" args
		 * @param string      $taxonomy   The name of the taxonomy
		 * @param mixed       $source     The query results
		 * @param array       $all_args   All of the query arguments (not just the "where" args)
		 * @param AppContext  $context    The AppContext object
		 * @param ResolveInfo $info       The ResolveInfo object
		 *
		 * @since 0.0.5
		 * @return array
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_get_terms', $query_args, $args, self::$taxonomy, $source, $all_args, $context, $info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

}
