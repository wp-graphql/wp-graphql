<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;

/**
 * Class AbstractConnectionResolver
 *
 * ConnectionResolvers should extend this to make returning data in proper shape for
 * connections easier, ensure data is passed through consistent filters, etc.
 *
 * @package WPGraphQL\Data\Connection
 */
abstract class AbstractConnectionResolver {

	/**
	 * The source from the field calling the connection
	 *
	 * @var mixed
	 */
	protected $source;

	/**
	 * The args input on the field calling the connection
	 *
	 * @var array
	 */
	protected $args;

	/**
	 * The AppContext for the GraphQL Request
	 *
	 * @var AppContext
	 */
	protected $context;

	/**
	 * The ResolveInfo for the GraphQL Request
	 *
	 * @var ResolveInfo
	 */
	protected $info;

	/**
	 * The query args used to query for data to resolve the connection
	 *
	 * @var array
	 */
	protected $query_args;

	/**
	 * Whether the connection resolver should execute
	 *
	 * @var bool
	 */
	protected $should_execute = true;

	/**
	 * The Query class/array/object used to fetch the data.
	 *
	 * Examples:
	 *   return new WP_Query( $this->query_args );
	 *   return new WP_Comment_Query( $this->query_args );
	 *   return new WP_Term_Query( $this->query_args );
	 *
	 * Whatever it is will be passed through filters so that fields throughout
	 * have context from what was queried and can make adjustments as needed, such
	 * as exposing `totalCount` in pageInfo, etc.
	 *
	 * @var mixed
	 */
	protected $query;

	/**
	 * @var array
	 */
	protected $items;

	/**
	 * @var array
	 */
	protected $nodes;

	/**
	 * @var array
	 */
	protected $edges;

	/**
	 * @var int
	 */
	protected $query_amount;

	/**
	 * ConnectionResolver constructor.
	 *
	 * @param $source
	 * @param $args
	 * @param $context
	 * @param $info
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, $args, $context, $info ) {

		/**
		 * Set the source (the root object) for the resolver
		 */
		$this->source = $source;

		/**
		 * Set the args for the resolver
		 */
		$this->args = $args;

		/**
		 * Set the context of the resolver
		 */
		$this->context = $context;

		/**
		 * Set the resolveInfo for the resolver
		 */
		$this->info = $info;

		/**
		 * Determine the query amount for the resolver.
		 *
		 * This is the amount of items to query from the database. We determine this by
		 * determining how many items were asked for (first/last), then compare with the
		 * max amount allowed to query (default is 100), and then we fetch 1 more than
		 * that amount, so we know whether hasNextPage/hasPreviousPage should be true.
		 *
		 * If there are more items than were asked for, then there's another page.
		 */
		$this->query_amount = $this->get_query_amount();

		/**
		 * Get the Query Args. This accepts the input args and maps it to how it should be
		 * used in the WP_Query
		 *
		 * Filters the args
		 *
		 * @param array                      $query_args The query args to be used with the executable query to get data.
		 *                                               This should take in the GraphQL args and return args for use in fetching the data.
		 * @param AbstractConnectionResolver $this       Instance of the ConnectionResolver
		 */
		$this->query_args = apply_filters( 'graphql_connection_query_args', $this->get_query_args(), $this );

	}

	/**
	 * Given a key and value, this sets a query_arg which will modify the query_args used by
	 * the connection resolvers get_query();
	 *
	 * @param string $key The key of the query arg to set
	 * @param mixed  $value The value of the query arg to set
	 */
	public function setQueryArg( $key, $value ) {
		$this->query_args[ $key ] = $value;
	}

	/**
	 * get_query_args
	 *
	 * This method is used to accept the GraphQL Args input to the connection and return args
	 * that can be used in the Query to the datasource.
	 *
	 * For example, if the ConnectionResolver uses WP_Query to fetch the data, this
	 * should return $args for use in `new WP_Query`
	 *
	 * @return array
	 */
	abstract public function get_query_args();

	/**
	 * get_query
	 *
	 * The Query used to get items from the database (or even external datasource) are all
	 * different.
	 *
	 * Each connection resolver should be responsible for defining the Query object that
	 * is used to fetch items.
	 *
	 * @return mixed
	 */
	abstract public function get_query();

	/**
	 * get_items
	 *
	 * Return an array of items from the query
	 *
	 * Each Query class in WP and potential datasource handles this differently, so each connection
	 * resolver should handle getting the items into a uniform array of items.
	 *
	 * @return array
	 */
	abstract public function get_items();

	/**
	 * should_execute
	 *
	 * Determine whether or not the query should execute.
	 *
	 * Return true to exeucte, return false to prevent execution.
	 *
	 * Various criteria can be used to determine whether a Connection Query should
	 * be executed.
	 *
	 * For example, if a user is requesting revisions of a Post, and the user doesn't have
	 * permission to edit the post, they don't have permission to view the revisions, and therefore
	 * we can prevent the query to fetch revisions from executing in the first place.
	 *
	 * @return bool
	 */
	abstract public function should_execute();

	/**
	 * get_query_amount
	 *
	 * Returns the max between what was requested and what is defined as the $max_query_amount to
	 * ensure that queries don't exceed unwanted limits when querying data.
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function get_query_amount() {

		/**
		 * Filter the maximum number of posts per page that should be quried. The default is 100 to prevent queries from
		 * being exceedingly resource intensive, however individual systems can override this for their specific needs.
		 *
		 * This filter is intentionally applied AFTER the query_args filter, as
		 *
		 * @param array       $query_args array of query_args being passed to the
		 * @param mixed       $source     source passed down from the resolve tree
		 * @param array       $args       array of arguments input in the field as part of the GraphQL query
		 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
		 * @param ResolveInfo $info       Info about fields passed down the resolve tree
		 *
		 * @since 0.0.6
		 */
		$max_query_amount = apply_filters( 'graphql_connection_max_query_amount', 100, $this->source, $this->args, $this->context, $this->info );

		return min( $max_query_amount, absint( $this->get_amount_requested() ) );

	}

	/**
	 * get_amount_requested
	 *
	 * This checks the $args to determine the amount requested, and if
	 *
	 * @return int|null
	 * @throws \Exception
	 */
	public function get_amount_requested() {

		/**
		 * Set the default amount
		 */
		$amount_requested = 10;

		/**
		 * If both first & last are used in the input args, throw an exception as that won't
		 * work properly
		 */
		if ( ! empty( $this->args['first'] ) && ! empty( $this->args['last'] ) ) {
			throw new UserError( esc_html__( 'first and last cannot be used together. For forward pagination, use first & after. For backward pagination, use last & before.', 'wp-graphql' ) );
		}

		/**
		 * If first is set, and is a positive integer, use it for the $amount_requested
		 * but if it's set to anything that isn't a positive integer, throw an exception
		 */
		if ( ! empty( $this->args['first'] ) && is_int( $this->args['first'] ) ) {
			if ( 0 > $this->args['first'] ) {
				throw new UserError( esc_html__( 'first must be a positive integer.', 'wp-graphql' ) );
			} else {
				$amount_requested = $this->args['first'];
			}
		}

		/**
		 * If last is set, and is a positive integer, use it for the $amount_requested
		 * but if it's set to anything that isn't a positive integer, throw an exception
		 */
		if ( ! empty( $this->args['last'] ) && is_int( $this->args['last'] ) ) {
			if ( 0 > $this->args['last'] ) {
				throw new UserError( esc_html__( 'last must be a positive integer.', 'wp-graphql' ) );
			} else {
				$amount_requested = $this->args['last'];
			}
		}

		return max( 0, $amount_requested );

	}

	/**
	 * get_offset
	 *
	 * This returns the offset to be used in the $query_args based on the $args passed to the
	 * GraphQL query.
	 *
	 * @return int|mixed
	 */
	public function get_offset() {

		/**
		 * Defaults
		 */
		$offset = 0;

		/**
		 * Get the $after offset
		 */
		if ( ! empty( $this->args['after'] ) ) {
			$offset = ArrayConnection::cursorToOffset( $this->args['after'] );
		} elseif ( ! empty( $this->args['before'] ) ) {
			$offset = ArrayConnection::cursorToOffset( $this->args['before'] );
		}

		/**
		 * Return the higher of the two values
		 */
		return max( 0, $offset );

	}

	/**
	 * has_next_page
	 *
	 * Whether there is a next page in the connection.
	 *
	 * If there are more "items" than were asked for in the "first" argument, has_next_page()
	 * will be set to true
	 *
	 * @return boolean
	 */
	public function has_next_page() {
		return ! empty( $this->args['first'] ) && ( count( $this->items ) > $this->query_amount ) ? true : false;
	}

	/**
	 * has_previous_page
	 *
	 * Whether there is a previous page in the connection.
	 *
	 * If there are more "items" than were asked for in the "last" argument, has_previous_page()
	 * will be set to true
	 *
	 * @return boolean
	 */
	public function has_previous_page() {
		return ! empty( $this->args['last'] ) && ( count( $this->items ) > $this->query_amount ) ? true : false;
	}

	/**
	 * get_start_cursor
	 *
	 * Determine the start cursor from the connection
	 *
	 * @return mixed string|null
	 */
	public function get_start_cursor() {
		$first_edge = $this->edges && ! empty( $this->edges ) ? $this->edges[0] : null;

		return isset( $first_edge['cursor'] ) ? $first_edge['cursor'] : null;
	}

	/**
	 * get_end_cursor
	 *
	 * Determine the end cursor from the connection
	 *
	 * @return mixed string|null
	 */
	public function get_end_cursor() {
		$last_edge = $this->edges && ! empty( $this->edges ) ? $this->edges[ count( $this->edges ) - 1 ] : null;

		return isset( $last_edge['cursor'] ) ? $last_edge['cursor'] : null;
	}

	/**
	 * get_nodes
	 *
	 * Get the nodes from the query.
	 *
	 * We slice the array to match the amount of items that was asked for, as we over-fetched
	 * by 1 item to calculate pageInfo.
	 *
	 * For backward pagination, we reverse the order of nodes.
	 *
	 * @return array
	 */
	public function get_nodes() {
		if ( empty( $this->items ) ) {
			return [];
		}
		$nodes = array_slice( array_values( $this->items ), 0, $this->query_amount );

		return ! empty( $this->args['last'] ) ? array_reverse( $nodes ) : $nodes;
	}

	/**
	 * @param $node
	 * @param $key
	 *
	 * @return string
	 */
	protected function get_cursor_for_node( $node, $key = null ) {
		return base64_encode( 'arrayconnection:' . $node );
	}

	/**
	 * get_edges
	 *
	 * This iterates over the nodes and returns edges
	 *
	 * @return mixed
	 */
	public function get_edges() {
		$edges = [];
		if ( ! empty( $this->nodes ) ) {
			foreach ( $this->nodes as $key => $node ) {

				/**
				 * Create the edge, pass it through a filter.
				 *
				 * @param object $this Instance of the connection resolver class
				 */
				$edge = apply_filters(
					'graphql_connection_edge',
					[
						'cursor' => $this->get_cursor_for_node( $node, $key ),
						'node'   => $node,
						'source' => $this->source,
					],
					$this
				);

				/**
				 * If not empty, add the edge to the edges
				 */
				if ( ! empty( $edge ) ) {
					$edges[] = $edge;
				}
			}
		}

		return $edges;
	}

	/**
	 * get_page_info
	 *
	 * Returns pageInfo for the connection
	 *
	 * @return array
	 */
	public function get_page_info() {

		$page_info = [
			'startCursor'     => $this->get_start_cursor(),
			'endCursor'       => $this->get_end_cursor(),
			'hasNextPage'     => (bool) $this->has_next_page(),
			'hasPreviousPage' => (bool) $this->has_previous_page(),
		];

		/**
		 * Filter the pageInfo that is returned to the connection.
		 *
		 * This filter allows for additional fields to be filtered into the pageInfo
		 * of a connection, such as "totalCount", etc, because the filter has enough
		 * context of the query, args, request, etc to be able to calcuate and return
		 * that information.
		 *
		 * example:
		 *
		 * You would want to register a "total" field to the PageInfo type, then filter
		 * the pageInfo to return the total for the query, something to this tune:
		 *
		 * add_filter( 'graphql_connection_page_info', function( $page_info, $connection ) {
		 *
		 *   $page_info['total'] = null;
		 *
		 *   if ( $connection->query instanceof WP_Query ) {
		 *      if ( isset( $connection->query->found_posts ) {
		 *          $page_info['total'] = (int) $connection->query->found_posts;
		 *      }
		 *   }
		 *
		 *   return $page_info;
		 *
		 * });
		 */
		return apply_filters( 'graphql_connection_page_info', $page_info, $this );

	}

	protected function execute_and_get_data() {

		/**
		 * Check if the connection should execute. If conditions are met that should prevent
		 * the execution, we can bail from resolving early, before the query is executed.
		 *
		 * Filter whether the connection should execute.
		 *
		 * @param bool                       $should_execute Whether the connection should execute
		 * @param AbstractConnectionResolver $this           Instance of the Connection Resolver
		 */
		$this->should_execute = apply_filters( 'graphql_connection_should_execute', $this->should_execute(), $this );
		if ( false === $this->should_execute ) {
			return [];
		}

		/**
		 * Set the query for the resolver, for use as reference in filters, etc
		 *
		 * Filter the query. For core data, the query is typically an instance of:
		 *
		 *   WP_Query
		 *   WP_Comment_Query
		 *   WP_User_Query
		 *   WP_Term_Query
		 *   ...
		 *
		 * But in some cases, the actual mechanism for querying data should be overridden. For
		 * example, perhaps you're using ElasticSearch or Solr (hypothetical) and want to offload
		 * the query to that instead of a native WP_Query class. You could override this with a
		 * query to that datasource instead.
		 */
		$this->query = apply_filters( 'graphql_connection_query', $this->get_query(), $this );

		/**
		 * The items returned from the query. This array of items will be passed
		 * to `get_nodes`
		 *
		 * Filter the items.
		 *
		 * @param array                      $items The items returned from the query
		 * @param AbstractConnectionResolver $this  Instance of the Connection Resolver
		 */
		$items       = ! empty( $this->get_items() ) ? $this->get_items() : [];
		$this->items = apply_filters( 'graphql_connection_items', $items, $this );

		/**
		 * Set the items. These are the "nodes" that make up the connection.
		 *
		 * Filters the nodes in the connection
		 *
		 * @param array                      $nodes The nodes in the connection
		 * @param AbstractConnectionResolver $this  Instance of the Connection Resolver
		 */
		$this->nodes = apply_filters( 'graphql_connection_nodes', $this->get_nodes(), $this );

		/**
		 * Filters the edges in the connection
		 *
		 * @param array                      $nodes The nodes in the connection
		 * @param AbstractConnectionResolver $this  Instance of the Connection Resolver
		 */
		$this->edges = apply_filters( 'graphql_connection_edges', $this->get_edges(), $this );

	}

	/**
	 * get_connection
	 *
	 * Get the connection to return to the Connection Resolver
	 *
	 * @return array
	 */
	public function get_connection() {
		$this->execute_and_get_data();
		$connection = [
			'edges'    => $this->get_edges(),
			'pageInfo' => $this->get_page_info(),
			'nodes'    => $this->get_nodes(),
		];

		/**
		 * Filter the connection. In some cases, connections will want to provide
		 * additional information other than edges, nodes, and pageInfo
		 *
		 * This filter allows additional fields to be returned to the connection resolver
		 *
		 * @param array                      $connection The connection data being returned
		 * @param AbstractConnectionResolver $this       The instance of the connection resolver
		 */
		return apply_filters( 'graphql_connection', $connection, $this );
	}

}
