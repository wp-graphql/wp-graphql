<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Loader\AbstractDataLoader;
use WPGraphQL\Model\Model;
use WPGraphQL\Model\Post;

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
	 * The loader the resolver is configured to use.
	 *
	 * @var AbstractDataLoader
	 */
	protected $loader;

	/**
	 * Whether the connection is a one to one connection. Default false.
	 *
	 * @var bool
	 */
	public $one_to_one = false;

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
	protected $ids;

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
	 * @param mixed       $source  source passed down from the resolve tree
	 * @param array       $args    array of arguments input in the field as part of the GraphQL
	 *                             query
	 * @param AppContext  $context Object containing app context that gets passed down the resolve
	 *                             tree
	 * @param ResolveInfo $info    Info about fields passed down the resolve tree
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {

		// Bail if the Post->ID is empty, as that indicates a private post.
		if ( $source instanceof Post && empty( $source->ID ) ) {
			$this->should_execute = false;
		}

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
		 * Get the loader for the Connection
		 */
		$this->loader = $this->getLoader();

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
		 * @param array                      $query_args                   The query args to be used with the executable query to get data.
		 *                                                                 This should take in the GraphQL args and return args for use in fetching the data.
		 * @param AbstractConnectionResolver $connection_resolver          Instance of the ConnectionResolver
		 */
		$this->query_args = apply_filters( 'graphql_connection_query_args', $this->get_query_args(), $this );

	}

	/**
	 * Returns the source of the connection
	 *
	 * @return mixed
	 */
	public function getSource() {
		return $this->source;
	}

	/**
	 * Get the loader name
	 *
	 * @return AbstractDataLoader
	 * @throws Exception
	 */
	protected function getLoader() {
		$name = $this->get_loader_name();
		if ( empty( $name ) || ! is_string( $name ) ) {
			throw new Exception( __( 'The Connection Resolver needs to define a loader name', 'wp-graphql' ) );
		}

		return $this->context->get_loader( $name );
	}

	/**
	 * Returns the $args passed to the connection
	 *
	 * @return array
	 */
	public function getArgs(): array {
		return $this->args;
	}

	/**
	 * Returns the AppContext of the connection
	 *
	 * @return AppContext
	 */
	public function getContext(): AppContext {
		return $this->context;
	}

	/**
	 * Returns the ResolveInfo of the connection
	 *
	 * @return ResolveInfo
	 */
	public function getInfo(): ResolveInfo {
		return $this->info;
	}

	/**
	 * Returns whether the connection should execute
	 *
	 * @return bool
	 */
	public function getShouldExecute(): bool {
		return $this->should_execute;
	}

	/**
	 * @param string $key   The key of the query arg to set
	 * @param mixed  $value The value of the query arg to set
	 *
	 * @return AbstractConnectionResolver
	 *
	 * @deprecated in favor of set_query_arg
	 */
	public function setQueryArg( $key, $value ) {
		return $this->set_query_arg( $key, $value );
	}

	/**
	 * Given a key and value, this sets a query_arg which will modify the query_args used by
	 * the connection resolvers get_query();
	 *
	 * @param string $key   The key of the query arg to set
	 * @param mixed  $value The value of the query arg to set
	 *
	 * @return AbstractConnectionResolver
	 */
	public function set_query_arg( $key, $value ) {
		$this->query_args[ $key ] = $value;

		return $this;
	}

	/**
	 * Whether the connection should resolve as a one-to-one connection.
	 *
	 * @return AbstractConnectionResolver
	 */
	public function one_to_one() {
		$this->one_to_one = true;

		return $this;
	}

	/**
	 * Get_loader_name
	 *
	 * Return the name of the loader to be used with the connection resolver
	 *
	 * @return string
	 */
	abstract public function get_loader_name();

	/**
	 * Get_query_args
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
	 * Get_query
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
	 * Get_ids
	 *
	 * Return an array of ids from the query
	 *
	 * Each Query class in WP and potential datasource handles this differently, so each connection
	 * resolver should handle getting the items into a uniform array of items.
	 *
	 * @return array
	 */
	abstract public function get_ids();

	/**
	 * Should_execute
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
	 * Is_valid_offset
	 *
	 * Determine whether or not the the offset is valid, i.e the item corresponding to the offset
	 * exists. Offset is equivalent to WordPress ID (e.g post_id, term_id). So this function is
	 * equivalent to checking if the WordPress object exists for the given ID.
	 *
	 * @param mixed $offset The offset to validate. Typically a WordPress Database ID
	 *
	 * @return bool
	 */
	abstract public function is_valid_offset( $offset );

	/**
	 * Given an ID, return the model for the entity or null
	 *
	 * @param mixed $id The ID to identify the object by. Could be a database ID or an in-memory ID
	 *                  (like post_type name)
	 *
	 * @return mixed|Model|null
	 * @throws Exception
	 */
	public function get_node_by_id( $id ) {
		return $this->loader->load( $id );
	}

	/**
	 * Get_query_amount
	 *
	 * Returns the max between what was requested and what is defined as the $max_query_amount to
	 * ensure that queries don't exceed unwanted limits when querying data.
	 *
	 * @return int
	 * @throws Exception
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
	 * Get_amount_requested
	 *
	 * This checks the $args to determine the amount requested, and if
	 *
	 * @return int|null
	 * @throws Exception
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

		/**
		 * This filter allows to modify the requested connection page size
		 *
		 * @param int                        $amount the requested amount
		 * @param AbstractConnectionResolver $this   Instance of the connection resolver class
		 */
		return max( 0, apply_filters( 'graphql_connection_amount_requested', $amount_requested, $this ) );

	}

	/**
	 * @return int|null
	 */
	public function get_after_offset(): ?int {
		if ( isset( $this->args['after'] ) && ! empty( $this->args['after'] ) ) {
			return ArrayConnection::cursorToOffset( $this->args['after'] );
		}

		return null;
	}

	/**
	 * @return int|null
	 */
	public function get_before_offset(): ?int {
		if ( isset( $this->args['before'] ) && ! empty( $this->args['before'] ) ) {
			return ArrayConnection::cursorToOffset( $this->args['before'] );
		}

		return null;
	}

	/**
	 * Get_offset
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
	 * Has_next_page
	 *
	 * Whether there is a next page in the connection.
	 *
	 * If there are more "items" than were asked for in the "first" argument
	 * ore if there are more "items" after the "before" argument, has_next_page()
	 * will be set to true
	 *
	 * @return boolean
	 */
	public function has_next_page() {
		if ( ! empty( $this->args['first'] ) ) {
			return ! empty( $this->ids ) ? count( $this->ids ) > $this->query_amount : false;
		}

		if ( ! empty( $this->args['before'] ) ) {
			return $this->is_valid_offset( $this->get_offset() );
		}

		return false;
	}

	/**
	 * Has_previous_page
	 *
	 * Whether there is a previous page in the connection.
	 *
	 * If there are more "items" than were asked for in the "last" argument
	 * or if there are more "items" before the "after" argument, has_previous_page()
	 * will be set to true.
	 *
	 * @return boolean
	 */
	public function has_previous_page() {
		if ( ! empty( $this->args['last'] ) ) {
			return ! empty( $this->ids ) ? count( $this->ids ) > $this->query_amount : false;
		}

		if ( ! empty( $this->args['after'] ) ) {
			return $this->is_valid_offset( $this->get_offset() );
		}

		return false;
	}

	/**
	 * Get_start_cursor
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
	 * Get_end_cursor
	 *
	 * Determine the end cursor from the connection
	 *
	 * @return mixed string|null
	 */
	public function get_end_cursor() {
		$last_edge = ! empty( $this->edges ) ? $this->edges[ count( $this->edges ) - 1 ] : null;

		return isset( $last_edge['cursor'] ) ? $last_edge['cursor'] : null;
	}

	/**
	 * Get_nodes
	 *
	 * Get the nodes from the query.
	 *
	 * We slice the array to match the amount of items that was asked for, as we over-fetched
	 * by 1 item to calculate pageInfo.
	 *
	 * For backward pagination, we reverse the order of nodes.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_nodes() {
		if ( empty( $this->ids ) ) {
			return [];
		}

		$nodes = [];

		$ids = $this->ids;
		$ids = array_slice( $ids, 0, $this->query_amount, true );

		// If pagination is going backwards, revers the array of IDs
		$ids = ! empty( $this->args['last'] ) ? array_reverse( $ids ) : $ids;

		if ( ! empty( $this->get_offset() ) ) {
			// Determine if the offset is in the array
			$key = array_search( $this->get_offset(), $ids, true );
			// If the offset is in the array
			if ( false !== $key ) {
				$key = absint( $key );
				// Slice the array from the back
				if ( ! empty( $this->args['before'] ) ) {
					$ids = array_slice( $ids, 0, $key, true );
					// Slice the array from the front
				} else {
					$key ++;
					$ids = array_slice( $ids, $key, null, true );
				}
			}
		}

		foreach ( $ids as $id ) {
			$model = $this->get_node_by_id( $id );
			if ( true === $this->is_valid_model( $model ) ) {
				$nodes[ $id ] = $model;
			}
		}

		return $nodes;
	}

	/**
	 * Validates Model.
	 *
	 * If model isn't a class with a `fields` member, this function with have be overridden in
	 * the Connection class.
	 *
	 * @param mixed $model The model being validated
	 *
	 * @return bool
	 */
	protected function is_valid_model( $model ) {

		return isset( $model->fields ) && ! empty( $model->fields );
	}

	/**
	 * Given an ID, a cursor is returned
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	protected function get_cursor_for_node( $id ) {
		return base64_encode( 'arrayconnection:' . $id );
	}

	/**
	 * Get_edges
	 *
	 * This iterates over the nodes and returns edges
	 *
	 * @return array
	 */
	public function get_edges() {
		$edges = [];
		if ( ! empty( $this->nodes ) ) {

			foreach ( $this->nodes as $id => $node ) {

				$edge = [
					'cursor'     => $this->get_cursor_for_node( $id ),
					'node'       => $node,
					'source'     => $this->source,
					'connection' => $this,
				];

				/**
				 * Create the edge, pass it through a filter.
				 *
				 * @param array                      $edge                The edge within the connection
				 * @param AbstractConnectionResolver $connection_resolver Instance of the connection resolver class
				 */
				$edge = apply_filters(
					'graphql_connection_edge',
					$edge,
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

		// If first is set:
		//  If first is less than 0:
		//   Throw an error.
		//  If edges has length greater than than first:
		//   Slice edges to be of length first by removing edges from the end of edges.
		if ( isset( $this->args['first'] ) && $this->args['first'] <= count( $edges ) ) {
			$edges = array_slice( $edges, 0, absint( $this->args['first'] ) );
		}

		if ( isset( $this->args['last'] ) && $this->args['last'] <= count( $edges ) ) {
			$edges = array_slice( $edges, ( count( $edges ) - absint( $this->args['last'] ) ), absint( $this->args['last'] ) );
		}

		return $edges;
	}

	/**
	 * Get_page_info
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

	/**
	 * Execute the resolver query and get the data for the connection
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function execute_and_get_ids() {

		/**
		 * If should_execute is explicitly set to false already, we can
		 * prevent execution quickly. If it's not, we need to
		 * call the should_execute() method to execute any situational logic
		 * to determine if the connection query should execute or not
		 */
		$should_execute = false === $this->should_execute ? false : $this->should_execute();

		/**
		 * Check if the connection should execute. If conditions are met that should prevent
		 * the execution, we can bail from resolving early, before the query is executed.
		 *
		 * Filter whether the connection should execute.
		 *
		 * @param bool                       $should_execute      Whether the connection should execute
		 * @param AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
		 */
		$this->should_execute = apply_filters( 'graphql_connection_should_execute', $should_execute, $this );
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
		 *
		 * @param mixed                      $query               Instance of the Query for the resolver
		 * @param AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
		 */
		$this->query = apply_filters( 'graphql_connection_query', $this->get_query(), $this );

		/**
		 * Filter the connection IDs
		 *
		 * @param array                      $ids                 Array of IDs this connection will be resolving
		 * @param AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
		 */
		$this->ids = apply_filters( 'graphql_connection_ids', $this->get_ids(), $this );

		if ( empty( $this->ids ) ) {
			return [];
		}

		/**
		 * Buffer the IDs for deferred resolution
		 */
		$this->loader->buffer( $this->ids );

		return $this->ids;

	}

	/**
	 * Get_connection
	 *
	 * Get the connection to return to the Connection Resolver
	 *
	 * @return mixed|array|Deferred
	 *
	 * @throws Exception
	 */
	public function get_connection() {

		$this->execute_and_get_ids();

		/**
		 * Return a Deferred function to load all buffered nodes before
		 * returning the connection.
		 */
		return new Deferred(
			function() {

				if ( ! empty( $this->ids ) ) {
					$this->loader->load_many( $this->ids );
				}

				/**
				 * Set the items. These are the "nodes" that make up the connection.
				 *
				 * Filters the nodes in the connection
				 *
				 * @param array                      $nodes               The nodes in the connection
				 * @param AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
				 */
				$this->nodes = apply_filters( 'graphql_connection_nodes', $this->get_nodes(), $this );

				/**
				 * Filters the edges in the connection
				 *
				 * @param array                      $nodes               The nodes in the connection
				 * @param AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
				 */
				$this->edges = apply_filters( 'graphql_connection_edges', $this->get_edges(), $this );

				if ( true === $this->one_to_one ) {
					// For one to one connections, return the first edge.
					$connection = ! empty( $this->edges[ array_key_first( $this->edges ) ] ) ? $this->edges[ array_key_first( $this->edges ) ] : null;
				} else {
					// For plural connections (default) return edges/nodes/pageInfo
					$connection = [
						'nodes'    => $this->nodes,
						'edges'    => $this->edges,
						'pageInfo' => $this->get_page_info(),
					];
				}

				/**
				 * Filter the connection. In some cases, connections will want to provide
				 * additional information other than edges, nodes, and pageInfo
				 *
				 * This filter allows additional fields to be returned to the connection resolver
				 *
				 * @param array                      $connection          The connection data being returned
				 * @param AbstractConnectionResolver $connection_resolver The instance of the connection resolver
				 */
				return apply_filters( 'graphql_connection', $connection, $this );

			}
		);

	}

}
