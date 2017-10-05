<?php

namespace WPGraphQL\Type\PostObject\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;
use WPGraphQL\Data\ConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class PostObjectConnection - connects posts to other types
 *
 * @package WPGraphQL\Data\Resolvers
 * @since   0.0.5
 */
class PostObjectConnectionResolver extends ConnectionResolver {

	/**
	 * Stores the name of the $post_type being resolved
	 *
	 * @var $post_type
	 */
	public static $post_type;

	/**
	 * Holds the maximum number of items that can be queried per request
	 *
	 * @var int $max_query_amount
	 */
	public static $max_query_amount = 100;

	/**
	 * Holds the args that will be passed to the WP_Query
	 *
	 * @var array $query_args
	 */
	protected static $query_args = [];

	protected static $last_arg;
	protected static $first_arg;

	/**
	 * PostObjectConnectionResolver constructor.
	 *
	 * @param $post_type
	 */
	public function __construct( $post_type ) {
		self::$post_type = $post_type;
	}

	/**
	 * This returns the $query_args that should be used when querying for posts in the postObjectConnectionResolver.
	 * This checks what input $args are part of the query, combines them with various filters, etc and returns an
	 * array of $query_args to be used in the \WP_Query call
	 *
	 * @param mixed       $source  The query source being passed down to the resolver
	 * @param array       $args    The arguments that were provided to the query
	 * @param AppContext  $context Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info    Info about fields passed down the resolve tree
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Prepare for later use
		 */
		self::$last_arg  = ! empty( $args['last'] ) ? $args['last'] : null;
		self::$first_arg = ! empty( $args['first'] ) ? $args['first'] : null;

		self::set_default_query_args( $source, $args, $context, $info );
		self::set_cache_query_args( $source, $args, $context, $info );
		self::set_contextual_query_args( $source, $args, $context, $info );
		self::set_input_query_args( $source, $args, $context, $info );
		self::set_order_query_args( $source, $args, $context, $info );

		/**
		 * Filter the $query args to allow folks to customize queries programmatically
		 *
		 * @param $query_args The args that will be passed to the WP_Query
		 * @param $source     The source that's passed down the GraphQL queries
		 * @param $args       The inputArgs on the field
		 * @param $context    The AppContext passed down the GraphQL tree
		 * @param $info       The ResolveInfo passed down the GraphQL tree
		 */
		self::$query_args = apply_filters( 'graphql_post_object_connection_query_args', self::$query_args, $source, $args, $context, $info );

		return self::$query_args;

	}

	protected static function set_default_query_args( $source, $args, $context, $info ) {

		/**
		 * Set the post_type for the query based on the type of post being queried
		 */
		self::$query_args['post_type'] = ! empty( self::$post_type ) ? self::$post_type : 'post';

		/**
		 * Don't calculate the total rows, it's not needed and can be expensive
		 */
		self::$query_args['no_found_rows'] = true;

		/**
		 * Set the post_status to "publish" by default
		 */
		self::$query_args['post_status'] = 'publish';

		/**
		 * Set posts_per_page the highest value of $first and $last, with a (filterable) max of 100
		 */
		self::$query_args['posts_per_page'] = min( max( absint( self::$first_arg ), absint( self::$last_arg ), 10 ), self::get_query_amount( $source, $args, $context, $info ) ) + 1;

		/**
		 * Set the default to only query posts with no post_parent set
		 */
		self::$query_args['post_parent'] = 0;

		/**
		 * Set the graphql_cursor_offset which is used by Config::graphql_wp_query_cursor_pagination_support
		 * to filter the WP_Query to support cursor pagination
		 */
		self::$query_args['graphql_cursor_offset']  = self::get_offset( $args );
		self::$query_args['graphql_cursor_compare'] = ( ! empty( self::$last_arg ) ) ? '>' : '<';

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		self::$query_args['graphql_args'] = $args;


	}

	protected static function set_cache_query_args( $source, $args, $context, $info ) {

		/**
		 * Don't update caches by default
		 */
		$node_fields = ! empty( $info->getFieldSelection( 3 )['edges']['node'] ) ? $info->getFieldSelection( 3 )['edges']['node'] : [];

		/**
		 * Prime the caches if any fields are requested that are NOT post_object_fields.
		 *
		 * By defaulting to not prime the caches, we get slight performance gains when no terms
		 * or meta are also queried, but when terms or meta are queried we get performance gains
		 * if the caches are primed with the initial query
		 */
		if ( ! empty( $node_fields ) && is_array( $node_fields ) ) {

			$post_object_fields = [
				'id',
				'title',
				'date',
				'dateGmt',
				'content',
				'excerpt',
				'guid',
				'link',
				'menuOrder',
				'modified',
				'modifiedGmt',
				'parent',
				'pingStatus',
				'pinged',
				'slug',
				'status',
				'title',
				'toPing',
				self::$post_type . 'Id',
			];

			$prime_caches = array_filter( array_keys( $node_fields ), function( $field ) use ( $post_object_fields ) {
				if ( in_array( $field, $post_object_fields, true ) ) {
					return false;
				} else {
					return true;
				}
			} );

			self::$query_args['update_post_meta_cache'] = ! empty( $prime_caches ) ? true : false;
			self::$query_args['update_post_term_cache'] = ! empty( $prime_caches ) ? true : false;

		}

	}

	protected static function set_contextual_query_args( $source, $args, $context, $info ) {

		/**
		 * If the post_type is "attachment" set the default "post_status" $query_arg to "inherit"
		 */
		if ( 'attachment' === self::$post_type ) {
			self::$query_args['post_status'] = 'inherit';

			/**
			 * Unset the "post_parent" for attachments, as we don't really care if they
			 * have a post_parent set by default
			 */
			unset( self::$query_args['post_parent'] );
		}

		/**
		 * Determine where we're at in the Graph and adjust the query context appropriately.
		 *
		 * For example, if we're querying for posts as a field of termObject query, this will automatically
		 * set the query to pull posts that belong to that term.
		 */
		if ( true === is_object( $source ) ) :
			switch ( true ) {
				case $source instanceof \WP_Post:
					self::$query_args['post_parent'] = $source->ID;
					break;
				case $source instanceof \WP_Post_Type:
					self::$query_args['post_type'] = $source->name;
					break;
				case $source instanceof \WP_Term:
					self::$query_args['tax_query'] = [
						[
							'taxonomy' => $source->taxonomy,
							'terms'    => [ $source->term_id ],
							'field'    => 'term_id',
						],
					];
					break;
				case $source instanceof \WP_User:
					self::$query_args['author'] = absint( $source->ID );
					break;
				default:
					break;
			}
		endif;

	}

	protected static function set_input_query_args( $source, $args, $context, $info ) {

		/**
		 * Collect the input_fields and sanitize them to prepare them for sending to the WP_Query
		 */
		$input_fields = [];
		if ( ! empty( $args['where'] ) ) {
			$input_fields = self::sanitize_input_fields( $args['where'], $source, $args, $context, $info );
		}

		/**
		 * Merge the input_fields with the default query_args
		 */
		if ( ! empty( $input_fields ) ) {
			self::$query_args = array_merge( self::$query_args, $input_fields );
		}

	}

	protected static function set_order_query_args( $source, $args, $context, $info ) {

		/**
		 * Map the orderby inputArgs to the WP_Query
		 */
		if ( ! empty( $args['where']['orderby'] ) && is_array( $args['where']['orderby'] ) ) {
			self::$query_args['orderby'] = [];
			foreach ( $args['where']['orderby'] as $orderby_input ) {
				if ( ! empty( $orderby_input['field'] ) ) {
					self::$query_args['orderby'] = [
						esc_sql( $orderby_input['field'] ) => esc_sql( $orderby_input['order'] ),
					];
				}
			}
		}

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( self::$query_args['orderby'] ) ) {
			self::$query_args['order'] = ! empty( self::$last_arg ) ? 'ASC' : 'DESC';
		}

	}

	/**
	 * This runs the query and returns the response
	 *
	 * @param $query_args
	 *
	 * @return \WP_Query
	 */
	public static function get_query( $query_args ) {

		$query = new \WP_Query( $query_args );

		if ( ! empty( $query_args['author'] ) ) {
			var_dump( $query );
		}

		return $query;
	}

	/**
	 * This takes an array of items, the $args and the $query and returns the connection including
	 * the edges and page info
	 *
	 * @param mixed       $query   The Query that was processed to get the connection data
	 * @param array       $items   The array of items being connected
	 * @param array       $args    The $args that were passed to the query
	 * @param mixed       $source  The source being passed down the resolve tree
	 * @param AppContext  $context The AppContext being passed down the resolve tree
	 * @param ResolveInfo $info    the ResolveInfo passed down the resolve tree
	 *
	 * @return array
	 */
	public static function get_connection( $query, array $items, $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Get the $posts from the query
		 */
		$items = ! empty( $items ) && is_array( $items ) ? $items : [];

		$info = self::get_query_info( $query );

		/**
		 * Set whether there is or is not another page
		 */
		$has_previous_page = ( ! empty( $args['last'] ) && ( $info['total_items'] >= self::get_query_amount( $source, $args, $context, $info ) ) ) ? true : false;
		$has_next_page     = ( ! empty( $args['first'] ) && ( $info['total_items'] >= self::get_query_amount( $source, $args, $context, $info ) ) ) ? true : false;

		/**
		 * Slice the array to the amount of items that were requested
		 */
		$items = array_slice( $items, 0, self::get_query_amount( $source, $args, $query, $info ) );

		/**
		 * Get the edges from the $items
		 */
		$edges = self::get_edges( $items, $source, $args, $context, $info );

		/**
		 * Find the first_edge and last_edge
		 */
		$first_edge      = $edges ? $edges[0] : null;
		$last_edge       = $edges ? $edges[ count( $edges ) - 1 ] : null;
		$edges_to_return = $edges;

		/**
		 * Create the connection to return
		 */
		$connection = [
			'edges'    => $edges_to_return,
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
	public static function get_edges( $items, $source, $args, $context, $info ) {
		$edges = [];

		/**
		 * If we're doing backward pagination we want to reverse the array before
		 * returning it to the edges
		 */
		if ( ! empty( $args['last'] ) ) {
			$items = array_reverse( $items );
		}

		if ( ! empty( $items ) && is_array( $items ) ) {
			foreach ( $items as $item ) {
				$edges[] = [
					'cursor' => ArrayConnection::offsetToCursor( $item->ID ),
					'node'   => DataSource::resolve_post_object( $item->ID, $item->post_type ),
				];
			}
		}

		return $edges;
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_Query
	 * friendly keys. There's probably a cleaner/more dynamic way to approach this, but
	 * this was quick. I'd be down to explore more dynamic ways to map this, but for
	 * now this gets the job done.
	 *
	 * @param array       $args     Query "where" args
	 * @param mixed       $source   The query results for a query calling this
	 * @param array       $all_args All of the arguments for the query (not just the "where" args)
	 * @param AppContext  $context  The AppContext object
	 * @param ResolveInfo $info     The ResolveInfo object
	 *
	 * @since  0.0.5
	 * @access public
	 * @return array
	 */
	public static function sanitize_input_fields( array $args, $source, array $all_args, AppContext $context, ResolveInfo $info ) {

		$arg_mapping = [
			'authorName'   => 'author_name',
			'authorIn'     => 'author__in',
			'authorNotIn'  => 'author__not_in',
			'categoryId'   => 'cat',
			'categoryName' => 'category_name',
			'categoryIn'   => 'category__in',
			'tagId'        => 'tag_id',
			'tagIds'       => 'tag__and',
			'tagSlugAnd'   => 'tag_slug__and',
			'tagSlugIn'    => 'tag_slug__in',
			'search'       => 's',
			'id'           => 'p',
			'parent'       => 'post_parent',
			'parentIn'     => 'post_parent__in',
			'parentNotIn'  => 'post_parent__not_in',
			'in'           => 'post__in',
			'notIn'        => 'post__not_in',
			'nameIn'       => 'post_name__in',
			'hasPassword'  => 'has_password',
			'password'     => 'post_password',
			'status'       => 'post_status',
			'dateQuery'    => 'date_query',
		];

		/**
		 * Map and sanitize the input args to the WP_Query compatible args
		 */
		$query_args = Types::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the WP_Query
		 *
		 * @param array       $query_args The mapped query arguments
		 * @param array       $args       Query "where" args
		 * @param string      $post_type  The post type for the query
		 * @param mixed       $source     The query results for a query calling this
		 * @param array       $all_args   All of the arguments for the query (not just the "where" args)
		 * @param AppContext  $context    The AppContext object
		 * @param ResolveInfo $info       The ResolveInfo object
		 *
		 * @since 0.0.5
		 * @return array
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_query', $query_args, $args, $source, $all_args, $context, $info );

		/**
		 * Return the Query Args
		 */
		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

}
