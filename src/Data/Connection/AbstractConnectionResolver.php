<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;

/**
 * Class AbstractConnectionResolver
 *
 * Individual Connection Resolvers should extend this to make returning data in proper shape for Relay-compliant connections easier, ensure data is passed through consistent filters, etc.
 *
 * @package WPGraphQL\Data\Connection
 */
abstract class AbstractConnectionResolver {
	/**
	 * The source from the field calling the connection.
	 *
	 * @var \WPGraphQL\Model\Model|mixed[]|mixed
	 */
	protected $source;

	/**
	 * The args input before it is filtered and prepared by the constructor.
	 *
	 * @var array<string,mixed>
	 */
	protected $unfiltered_args;

	/**
	 * The args input on the field calling the connection.
	 *
	 * Filterable by `graphql_connection_args`.
	 *
	 * @var array<string,mixed>
	 */
	protected $args;

	/**
	 * The AppContext for the GraphQL Request
	 *
	 * @var \WPGraphQL\AppContext
	 */
	protected $context;

	/**
	 * The ResolveInfo for the GraphQL Request
	 *
	 * @var \GraphQL\Type\Definition\ResolveInfo
	 */
	protected $info;

	/**
	 * The query args used to query for data to resolve the connection.
	 *
	 * @var array<string,mixed>
	 */
	protected $query_args;

	/**
	 * Whether the connection resolver should execute.
	 *
	 * @var bool
	 */
	protected $should_execute = true;

	/**
	 * The loader name.
	 *
	 * Defaults to `loader_name()` and filterable by `graphql_connection_loader_name`.
	 *
	 * @var ?string
	 */
	protected $loader_name;

	/**
	 * The loader the resolver is configured to use.
	 *
	 * @var ?\WPGraphQL\Data\Loader\AbstractDataLoader
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
	 * @var mixed[]|object|mixed
	 */
	protected $query;

	/**
	 * @var mixed[]
	 */
	protected $items;

	/**
	 * The IDs returned from the query.
	 *
	 * @var int[]|string[]
	 */
	protected $ids;

	/**
	 * The nodes (usually GraphQL models) returned from the query.
	 *
	 * @var \WPGraphQL\Model\Model[]|mixed[]
	 */
	protected $nodes;

	/**
	 * The edges for the connection.
	 *
	 * @var array<string,mixed>[]
	 */
	protected $edges;

	/**
	 * The page info for the connection.
	 *
	 * Filterable by `graphql_connection_page_info`.
	 *
	 * @var ?array<string,mixed>
	 */
	protected $page_info;

	/**
	 * The query amount to return for the connection.
	 *
	 * @var ?int
	 */
	protected $query_amount;

	/**
	 * ConnectionResolver constructor.
	 *
	 * @param mixed                                $source  Source passed down from the resolve tree
	 * @param array<string,mixed>                  $args    Array of arguments input in the field as part of the GraphQL query.
	 * @param \WPGraphQL\AppContext                $context The app context that gets passed down the resolve tree.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info    Info about fields passed down the resolve tree.
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		// Set the source (the root object), context, resolveInfo, and unfiltered args for the resolver.
		$this->source          = $source;
		$this->unfiltered_args = $args;
		$this->context         = $context;
		$this->info            = $info;

		/**
		 * @todo This exists for b/c, where extenders may be directly accessing `$this->args` in ::get_loader() or even `::get_args()`.
		 * We can remove this once the rest of lifecycle has been updated.
		 */
		$this->args = $args;

		// Bail if the Post->ID is empty, as that indicates a private post.
		if ( $source instanceof Post && empty( $source->ID ) ) {
			$this->should_execute = false;
		}

		// Get the loader for the Connection.
		$this->loader = $this->get_loader();

		/**
		 *
		 * Filters the GraphQL args before they are used in get_query_args().
		 *
		 * @param array<string,mixed>                                   $args                The GraphQL args passed to the resolver.
		 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the ConnectionResolver.
		 * @param array<string,mixed>                                   $unfiltered_args     Array of arguments input in the field as part of the GraphQL query.
		 *
		 * @since 1.11.0
		 */
		$this->args = apply_filters( 'graphql_connection_args', $this->get_args(), $this, $this->get_unfiltered_args() );

		// Get the query amount for the connection.
		$this->query_amount = $this->get_query_amount();

		/**
		 * Get the Query Args. This accepts the input args and maps it to how it should be
		 * used in the WP_Query
		 *
		 * Filters the args
		 *
		 * @param array<string,mixed>                                   $query_args          The query args to be used with the executable query to get data.
		 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the ConnectionResolver
		 * @param array<string,mixed>                                   $unfiltered_args Array of arguments input in the field as part of the GraphQL query.
		 */
		$this->query_args = apply_filters( 'graphql_connection_query_args', $this->get_query_args(), $this, $args );
	}

	/**
	 * The name of the loader to use for this connection.
	 *
	 * Filterable by `graphql_connection_loader_name`.
	 *
	 * @todo This is protected for backwards compatibility, but should be abstract and implemented by the child classes.
	 */
	protected function loader_name(): string {
		return '';
	}

	/**
	 * Returns the $args passed to the connection.
	 *
	 * Useful for modifying the $args before they are passed to $this->get_query_args().
	 *
	 * @return array<string,mixed>
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * Get_query_args
	 *
	 * This method is used to accept the GraphQL Args input to the connection and return args
	 * that can be used in the Query to the datasource.
	 *
	 * For example, if the ConnectionResolver uses WP_Query to fetch the data, this
	 * should return $args for use in `new WP_Query`
	 *
	 * @return array<string,mixed>
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
	 * Should_execute
	 *
	 * Determine whether or not the query should execute.
	 *
	 * Return true to execute, return false to prevent execution.
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
	 * The maximum number of items that should be returned by the query.
	 *
	 * This is filtered by `graphql_connection_max_query_amount` in ::get_query_amount().
	 */
	protected function max_query_amount(): int {
		return 100;
	}

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
	 * Return an array of ids from the query
	 *
	 * Each Query class in WP and potential datasource handles this differently, so each connection
	 * resolver should handle getting the items into a uniform array of items.
	 *
	 * Note: This is not an abstract function to prevent backwards compatibility issues, so it
	 * instead throws an exception. Classes that extend AbstractConnectionResolver should
	 * override this method, instead of AbstractConnectionResolver::get_ids().
	 *
	 * @since 1.9.0
	 *
	 * @throws \Exception If child class forgot to implement this.
	 *
	 * @return int[]|string[] the array of IDs.
	 */
	public function get_ids_from_query() {
		throw new Exception(
			sprintf(
				// translators: %s is the name of the connection resolver class.
				esc_html__( 'Class %s does not implement a valid method `get_ids_from_query()`.', 'wp-graphql' ),
				static::class
			)
		);
	}

	/**
	 * Returns the offset for a given cursor.
	 *
	 * Connections that use a string-based offset should override this method.
	 *
	 * @param ?string $cursor The cursor to convert to an offset.
	 *
	 * @return int|mixed
	 */
	public function get_offset_for_cursor( string $cursor = null ) { // phpcs:ignore PHPCompatibility.FunctionDeclarations.RemovedImplicitlyNullableParam.Deprecated -- This is a breaking change to fix.
		$offset = false;

		// We avoid using ArrayConnection::cursorToOffset() because it assumes an `int` offset.
		if ( ! empty( $cursor ) ) {
			$offset = substr( base64_decode( $cursor ), strlen( 'arrayconnection:' ) );
		}

		/**
		 * We assume a numeric $offset is an integer ID.
		 * If it isn't this method should be overridden by the child class.
		 */
		return is_numeric( $offset ) ? absint( $offset ) : $offset;
	}

	/**
	 * Validates Model.
	 *
	 * If model isn't a class with a `fields` member, this function with have be overridden in
	 * the Connection class.
	 *
	 * @param \WPGraphQL\Model\Model|mixed $model The model being validated
	 *
	 * @return bool
	 */
	protected function is_valid_model( $model ) {
		return isset( $model->fields ) && ! empty( $model->fields );
	}

	/**
	 * Returns the source of the connection
	 *
	 * @return mixed
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * Returns the AppContext of the connection.
	 */
	public function get_context(): AppContext {
		return $this->context;
	}

	/**
	 * Returns the ResolveInfo of the connection.
	 */
	public function get_info(): ResolveInfo {
		return $this->info;
	}

	/**
	 * Returns the loader name.
	 *
	 * If $loader_name is not initialized, this plugin will initialize it.
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	public function get_loader_name() {
		// Only initialize the loader_name property once.
		if ( ! isset( $this->loader_name ) ) {
			$name = $this->loader_name();

			// This is a b/c check because `loader_name()` is not abstract.
			if ( empty( $name ) ) {
				throw new \Exception(
					sprintf(
						// translators: %s is the name of the connection resolver class.
						esc_html__( 'Class %s does not implement a valid method `loader_name()`.', 'wp-graphql' ),
						esc_html( static::class )
					)
				);
			}

			/**
			 * Filters the loader name.
			 * This is the name of the registered DataLoader that will be used to load the data for the connection.
			 *
			 * @param string $loader_name The name of the loader.
			 * @param self   $resolver    The AbstractConnectionResolver instance.
			 */
			$name = apply_filters( 'graphql_connection_loader_name', $name, $this );

			// Bail if the loader name is invalid.
			if ( empty( $name ) || ! is_string( $name ) ) {
				throw new \Exception( esc_html__( 'The Connection Resolver needs to define a loader name', 'wp-graphql' ) );
			}

			$this->loader_name = $name;
		}

		return $this->loader_name;
	}

	/**
	 * Returns whether the connection should execute.
	 */
	public function get_should_execute(): bool {
		return $this->should_execute;
	}

	/**
	 * Returns the $args passed to the connection, before any modifications.
	 *
	 * @return array<string,mixed>
	 */
	public function get_unfiltered_args(): array {
		return $this->unfiltered_args;
	}

	/**
	 * Returns the amount of items to query from the database.
	 *
	 * The amount is calculated as the the max between what was requested and what is defined as the $max_query_amount to ensure that queries don't exceed unwanted limits when querying data.
	 *
	 * If the amount requested is greater than the max query amount, a debug message will be included in the GraphQL response.
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function get_query_amount() {
		if ( ! isset( $this->query_amount ) ) {
			/**
			 * Filter the maximum number of posts per page that should be queried. This prevents queries from being exceedingly resource intensive.
			 *
			 * The default is 100 - unless overloaded by ::max_query_amount() in the child class.
			 *
			 * @param int                                  $max_posts  the maximum number of posts per page.
			 * @param mixed                                $source     source passed down from the resolve tree
			 * @param array<string,mixed>                  $args       array of arguments input in the field as part of the GraphQL query
			 * @param \WPGraphQL\AppContext                $context    Object containing app context that gets passed down the resolve tree
			 * @param \GraphQL\Type\Definition\ResolveInfo $info       Info about fields passed down the resolve tree
			 *
			 * @since 0.0.6
			 */
			$max_query_amount = (int) apply_filters( 'graphql_connection_max_query_amount', $this->max_query_amount(), $this->source, $this->args, $this->context, $this->info );

			// We don't want the requested amount to be lower than 0.
			$requested_query_amount = (int) max(
				0,
				/**
				 * This filter allows to modify the number of nodes the connection should return.
				 *
				 * @param int                        $amount   the requested amount
				 * @param self $resolver Instance of the connection resolver class
				 */
				apply_filters( 'graphql_connection_amount_requested', $this->get_amount_requested(), $this )
			);

			if ( $requested_query_amount > $max_query_amount ) {
				graphql_debug(
					sprintf( 'The number of items requested by the connection (%s) exceeds the max query amount. Only the first %s items will be returned.', $requested_query_amount, $max_query_amount ),
					[ 'connection' => static::class ]
				);
			}

			$this->query_amount = (int) min( $max_query_amount, $requested_query_amount );
		}

		return $this->query_amount;
	}


	/**
	 * Returns an array of IDs for the connection.
	 *
	 * These IDs have been fetched from the query with all the query args applied,
	 * then sliced (overfetching by 1) by pagination args.
	 *
	 * @return int[]|string[]
	 */
	public function get_ids() {
		$ids = $this->get_ids_from_query();

		return $this->apply_cursors_to_ids( $ids );
	}

	/**
	 * Get the nodes from the query.
	 *
	 * @uses AbstractConnectionResolver::get_ids_for_nodes()
	 *
	 * @return array<int|string,mixed|\WPGraphQL\Model\Model|null>
	 *
	 * @throws \Exception
	 */
	public function get_nodes() {
		$nodes = [];

		// These are already sliced and ordered, we're just populating node data.
		$ids = $this->get_ids_for_nodes();

		foreach ( $ids as $id ) {
			$model = $this->get_node_by_id( $id );
			if ( true === $this->is_valid_model( $model ) ) {
				$nodes[ $id ] = $model;
			}
		}

		return $nodes;
	}

	/**
	 * Get the edges from the nodes.
	 *
	 * @return array<string,mixed>[]
	 */
	public function get_edges() {
		// Bail early if there are no nodes.
		if ( empty( $this->nodes ) ) {
			return [];
		}

		$edges = [];

		// The nodes are already ordered, sliced, and populated. What's left is to populate the edge data for each one.
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
			 * @param array<string,mixed>                                   $edge                The edge within the connection
			 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the connection resolver class
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

		return $edges;
	}

	/**
	 * Returns pageInfo for the connection
	 *
	 * @return array<string,mixed>
	 */
	public function get_page_info() {
		if ( ! isset( $this->page_info ) ) {
			$page_info = $this->prepare_page_info();

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
			$this->page_info = apply_filters( 'graphql_connection_page_info', $page_info, $this );
		}

		return $this->page_info;
	}

	/**
	 * Given a key and value, this sets a query_arg which will modify the query_args used by
	 * the connection resolvers get_query();
	 *
	 * @param string $key   The key of the query arg to set
	 * @param mixed  $value The value of the query arg to set
	 *
	 * @return self
	 */
	public function set_query_arg( $key, $value ) {
		$this->query_args[ $key ] = $value;

		return $this;
	}

	/**
	 * Whether the connection should resolve as a one-to-one connection.
	 *
	 * @return self
	 */
	public function one_to_one() {
		$this->one_to_one = true;

		return $this;
	}

	/**
	 * Returns the loader.
	 *
	 * If $loader is not initialized, this method will initialize it.
	 *
	 * @return \WPGraphQL\Data\Loader\AbstractDataLoader
	 */
	protected function get_loader() {
		// If the loader isn't set, set it.
		if ( ! isset( $this->loader ) ) {
			$name = $this->get_loader_name();

			$this->loader = $this->context->get_loader( $name );
		}

		return $this->loader;
	}

	/**
	 * Returns the amount of items requested from the connection.
	 *
	 * @return int
	 *
	 * @throws \GraphQL\Error\UserError If the `first` or `last` args are used together.
	 */
	public function get_amount_requested() {
		/**
		 * Filters the default query amount for a connection, if no `first` or `last` GraphQL argument is supplied.
		 *
		 * @param int  $amount_requested The default query amount for a connection.
		 * @param self $resolver         Instance of the Connection Resolver.
		 */
		$amount_requested = apply_filters( 'graphql_connection_default_query_amount', 10, $this );

		/**
		 * If both first & last are used in the input args, throw an exception.
		 */
		if ( ! empty( $this->args['first'] ) && ! empty( $this->args['last'] ) ) {
			throw new UserError( esc_html__( 'The `first` and `last` connection args cannot be used together. For forward pagination, use `first` & `after`. For backward pagination, use `last` & `before`.', 'wp-graphql' ) );
		}

		/**
		 * Get the key to use for the query amount.
		 * We avoid a ternary here for unit testing.
		 */
		$args_key = ! empty( $this->args['first'] ) && is_int( $this->args['first'] ) ? 'first' : null;
		if ( null === $args_key ) {
			$args_key = ! empty( $this->args['last'] ) && is_int( $this->args['last'] ) ? 'last' : null;
		}

		/**
		 * If the key is set, and is a positive integer, use it for the $amount_requested
		 * but if it's set to anything that isn't a positive integer, throw an exception
		 */
		if ( null !== $args_key && isset( $this->args[ $args_key ] ) ) {
			if ( 0 > $this->args[ $args_key ] ) {
				throw new UserError(
					sprintf(
						// translators: %s: The name of the arg that was invalid
						esc_html__( '%s must be a positive integer.', 'wp-graphql' ),
						esc_html( $args_key )
					)
				);
			}

			$amount_requested = $this->args[ $args_key ];
		}

		return (int) $amount_requested;
	}

	/**
	 * Get the connection to return to the Connection Resolver
	 *
	 * @return \GraphQL\Deferred
	 *
	 * @throws \Exception
	 */
	public function get_connection() {
		$this->execute_and_get_ids();

		/**
		 * Return a Deferred function to load all buffered nodes before
		 * returning the connection.
		 */
		return new Deferred(
			function () {
				if ( ! empty( $this->ids ) ) {
					$this->get_loader()->load_many( $this->ids );
				}

				/**
				 * Set the items. These are the "nodes" that make up the connection.
				 *
				 * Filters the nodes in the connection
				 *
				 * @param array<int|string,mixed|\WPGraphQL\Model\Model|null>   $nodes               The nodes in the connection
				 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
				 */
				$this->nodes = apply_filters( 'graphql_connection_nodes', $this->get_nodes(), $this );

				/**
				 * Filters the edges in the connection
				 *
				 * @param array<int|string,mixed|\WPGraphQL\Model\Model|null>   $nodes               The nodes in the connection
				 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
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
				 * @param array<string,mixed>                                   $connection          The connection data being returned
				 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver The instance of the connection resolver
				 */
				return apply_filters( 'graphql_connection', $connection, $this );
			}
		);
	}

	/**
	 * Execute the resolver query and get the data for the connection
	 *
	 * @return int[]|string[]
	 *
	 * @throws \Exception
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
		 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
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
		 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
		 */
		$this->query = apply_filters( 'graphql_connection_query', $this->get_query(), $this );

		/**
		 * Filter the connection IDs
		 *
		 * @param int[]|string[]                                        $ids                 Array of IDs this connection will be resolving
		 * @param \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver Instance of the Connection Resolver
		 */
		$this->ids = apply_filters( 'graphql_connection_ids', $this->get_ids(), $this );

		if ( empty( $this->ids ) ) {
			return [];
		}

		/**
		 * Buffer the IDs for deferred resolution
		 */
		$this->get_loader()->buffer( $this->ids );

		return $this->ids;
	}


	/**
	 * Returns an array slice of IDs, per the Relay Cursor Connection spec.
	 *
	 * The resulting array should be overfetched by 1.
	 *
	 * @see https://relay.dev/graphql/connections.htm#sec-Pagination-algorithm
	 *
	 * @param int[]|string[] $ids The array of IDs from the query to slice, ordered as expected by the GraphQL query.
	 *
	 * @since 1.9.0
	 *
	 * @return int[]|string[]
	 */
	public function apply_cursors_to_ids( array $ids ) {
		if ( empty( $ids ) ) {
			return [];
		}

		// First we slice the array from the front.
		if ( ! empty( $this->args['after'] ) ) {
			$offset = $this->get_offset_for_cursor( $this->args['after'] );
			$index  = $this->get_array_index_for_offset( $offset, $ids );

			if ( false !== $index ) {
				// We want to start with the first id after the index.
				$ids = array_slice( $ids, $index + 1, null, true );
			}
		}

		// Then we slice the array from the back.
		if ( ! empty( $this->args['before'] ) ) {
			$offset = $this->get_offset_for_cursor( $this->args['before'] );
			$index  = $this->get_array_index_for_offset( $offset, $ids );

			if ( false !== $index ) {
				// Because array indexes start at 0, we can overfetch without adding 1 to $index.
				$ids = array_slice( $ids, 0, $index, true );
			}
		}

		return $ids;
	}

	/**
	 * Gets the array index for the given offset.
	 *
	 * @param int|string|false $offset The cursor pagination offset.
	 * @param int[]|string[]   $ids    The array of ids from the query.
	 *
	 * @return int|false $index The array index of the offset.
	 */
	public function get_array_index_for_offset( $offset, $ids ) {
		if ( false === $offset ) {
			return false;
		}

		// We use array_values() to ensure we're getting a positional index, and not a key.
		return array_search( $offset, array_values( $ids ), true );
	}

	/**
	 * Gets the IDs for the currently-paginated slice of nodes.
	 *
	 * We slice the array to match the amount of items that was asked for, as we over-fetched by 1 item to calculate pageInfo.
	 *
	 * @used-by AbstractConnectionResolver::get_nodes()
	 *
	 * @return int[]|string[]
	 */
	public function get_ids_for_nodes() {
		if ( empty( $this->ids ) ) {
			return [];
		}

		// If we're going backwards then our overfetched ID is at the front.
		if ( ! empty( $this->args['last'] ) && count( $this->ids ) > absint( $this->args['last'] ) ) {
			return array_slice( $this->ids, count( $this->ids ) - absint( $this->args['last'] ), $this->get_query_amount(), true );
		}

		// If we're going forwards, our overfetched ID is at the back.
		return array_slice( $this->ids, 0, $this->get_query_amount(), true );
	}

	/**
	 * Given an ID, return the model for the entity or null
	 *
	 * @param int|string|mixed $id The ID to identify the object by. Could be a database ID or an in-memory ID (like post_type name)
	 *
	 * @return mixed|\WPGraphQL\Model\Model|null
	 * @throws \Exception
	 */
	public function get_node_by_id( $id ) {
		return $this->get_loader()->load( $id );
	}

	/**
	 * Given an ID, a cursor is returned.
	 *
	 * @param int|string $id The ID to get the cursor for.
	 *
	 * @return string
	 */
	protected function get_cursor_for_node( $id ) {
		return base64_encode( 'arrayconnection:' . (string) $id );
	}

	/**
	 * Prepares the page info for the connection.
	 *
	 * @used-by self::get_page_info()
	 *
	 * @return array<string,mixed>
	 */
	protected function prepare_page_info(): array {
		return [
			'startCursor'     => $this->get_start_cursor(),
			'endCursor'       => $this->get_end_cursor(),
			'hasNextPage'     => $this->has_next_page(),
			'hasPreviousPage' => $this->has_previous_page(),
		];
	}

	/**
	 * Determine the start cursor from the connection
	 *
	 * @return mixed|string|null
	 */
	public function get_start_cursor() {
		$first_edge = $this->edges && ! empty( $this->edges ) ? $this->edges[0] : null;

		return isset( $first_edge['cursor'] ) ? $first_edge['cursor'] : null;
	}

	/**
	 * Determine the end cursor from the connection
	 *
	 * @return mixed|string|null
	 */
	public function get_end_cursor() {
		$last_edge = ! empty( $this->edges ) ? $this->edges[ count( $this->edges ) - 1 ] : null;

		return isset( $last_edge['cursor'] ) ? $last_edge['cursor'] : null;
	}

	/**
	 * Gets the offset for the `after` cursor.
	 *
	 * @return int|string|null
	 */
	public function get_after_offset() {
		if ( ! empty( $this->args['after'] ) ) {
			return $this->get_offset_for_cursor( $this->args['after'] );
		}

		return null;
	}

	/**
	 * Gets the offset for the `before` cursor.
	 *
	 * @return int|string|null
	 */
	public function get_before_offset() {
		if ( ! empty( $this->args['before'] ) ) {
			return $this->get_offset_for_cursor( $this->args['before'] );
		}

		return null;
	}

	/**
	 * Whether there is a next page in the connection.
	 *
	 * If there are more "items" than were asked for in the "first" argument
	 * ore if there are more "items" after the "before" argument, has_next_page() will be set to true.
	 *
	 * @return bool
	 */
	public function has_next_page() {
		if ( ! empty( $this->args['first'] ) ) {
			return ! empty( $this->ids ) && count( $this->ids ) > $this->get_query_amount();
		}

		$before_offset = $this->get_before_offset();

		if ( $before_offset ) {
			return $this->is_valid_offset( $before_offset );
		}

		return false;
	}

	/**
	 * Whether there is a previous page in the connection.
	 *
	 * If there are more "items" than were asked for in the "last" argument
	 * or if there are more "items" before the "after" argument, has_previous_page() will be set to true.
	 *
	 * @return bool
	 */
	public function has_previous_page() {
		if ( ! empty( $this->args['last'] ) ) {
			return ! empty( $this->ids ) && count( $this->ids ) > $this->get_query_amount();
		}

		$after_offset = $this->get_after_offset();
		if ( $after_offset ) {
			return $this->is_valid_offset( $after_offset );
		}

		return false;
	}

	/**
	 * DEPRECATED METHODS
	 *
	 * These methods are deprecated and will be removed in a future release.
	 */

	/**
	 * Returns the $args passed to the connection
	 *
	 * @deprecated Deprecated since v1.11.0 in favor of $this->get_args();
	 *
	 * @return array<string,mixed>
	 *
	 * @codeCoverageIgnore
	 */
	public function getArgs(): array {
		_deprecated_function( __METHOD__, '1.11.0', static::class . '::get_args()' );
		return $this->get_args();
	}

	/**
	 * @param string $key   The key of the query arg to set
	 * @param mixed  $value The value of the query arg to set
	 *
	 * @return \WPGraphQL\Data\Connection\AbstractConnectionResolver
	 *
	 * @deprecated 0.3.0
	 *
	 * @codeCoverageIgnore
	 */
	public function setQueryArg( $key, $value ) {
		_deprecated_function( __METHOD__, '0.3.0', static::class . '::set_query_arg()' );

		return $this->set_query_arg( $key, $value );
	}

	/**
	 * Get_offset
	 *
	 * This returns the offset to be used in the $query_args based on the $args passed to the
	 * GraphQL query.
	 *
	 * @deprecated 1.9.0
	 *
	 * @codeCoverageIgnore
	 *
	 * @return int|mixed
	 */
	public function get_offset() {
		_deprecated_function( __METHOD__, '1.9.0', static::class . '::get_offset_for_cursor()' );

		// Using shorthand since this is for deprecated code.
		$cursor = $this->args['after'] ?? null;
		$cursor = $cursor ?: ( $this->args['before'] ?? null );

		return $this->get_offset_for_cursor( $cursor );
	}

	/**
	 * Returns the source of the connection.
	 *
	 * @deprecated 1.24.0 in favor of $this->get_source().
	 *
	 * @return mixed
	 */
	public function getSource() {
		_deprecated_function( __METHOD__, '1.24.0', static::class . '::get_source()' );

		return $this->get_source();
	}

	/**
	 * Returns the AppContext of the connection.
	 *
	 * @deprecated 1.24.0 in favor of $this->get_context().
	 */
	public function getContext(): AppContext {
		_deprecated_function( __METHOD__, '1.24.0', static::class . '::get_context()' );

		return $this->get_context();
	}

	/**
	 * Returns the ResolveInfo of the connection.
	 *
	 * @deprecated 1.24.0 in favor of $this->get_info().
	 */
	public function getInfo(): ResolveInfo {
		_deprecated_function( __METHOD__, '1.24.0', static::class . '::get_info()' );

		return $this->get_info();
	}

	/**
	 * Returns whether the connection should execute.
	 *
	 * @deprecated 1.24.0 in favor of $this->get_should_execute().
	 */
	public function getShouldExecute(): bool {
		_deprecated_function( __METHOD__, '1.24.0', static::class . '::should_execute()' );

		return $this->get_should_execute();
	}

	/**
	 * Returns the loader.
	 *
	 * @deprecated 1.24.0 in favor of $this->get_loader().
	 *
	 * @return \WPGraphQL\Data\Loader\AbstractDataLoader
	 * @throws \Exception
	 */
	protected function getLoader() {
		_deprecated_function( __METHOD__, '1.24.0', static::class . '::get_loader()' );

		return $this->get_loader();
	}
}
