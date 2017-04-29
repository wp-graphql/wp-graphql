<?php
namespace WPGraphQL\Type\PostObject\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;
use WPGraphQL\Data\ConnectionResolver;
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
		 * The post_type is set based on the type being queried.
		 */
		$query_args['post_type'] = ! empty( self::$post_type ) ? self::$post_type : 'post';

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $args;

		/**
		 * Sticky posts are off by default to reduce unexpected results in queries.
		 */
		$query_args['ignore_sticky_posts'] = true;

		/**
		 * Cursor based pagination allows us to efficiently page through data without having to know the total number
		 * of items matching the query.
		 */
		$query_args['no_found_rows'] = true;

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
		 * Set the orderby to orderby DATE, DESC by default
		 */
		$id_order              = ! empty( $query_args['graphql_cursor_compare'] ) && '>' === $query_args['graphql_cursor_compare'] ? 'ASC' : 'DESC';
		$query_args['orderby'] = [
			'date' => esc_html( $id_order ),
		];

		/**
		 * We only need to calculate the totalItems matching the query, if it's been specifically asked
		 * for in the Query response.
		 */
		$field_selection = $info->getFieldSelection( 10 );

		if ( ! empty( $field_selection['debug']['totalItems'] ) ) {
			$query_args['no_found_rows'] = false;
		}

		/**
		 * Take any of the input $args (under the "where" input) that were part of the GraphQL query and map and
		 * sanitize their GraphQL input to apply to the WP_Query
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
		if ( ! empty( $input_fields ) ) {
			$query_args = array_merge( $query_args, $input_fields );
		}

		/**
		 * Map the orderby Input args to the WP_Query args
		 */
		if ( ! empty( $args['where']['orderby'] ) && is_array( $args['where']['orderby'] ) ) {
			foreach ( $args['where']['orderby'] as $orderby_input ) {
				if ( ! empty( $orderby_input['field'] ) ) {
					$query_args['orderby'][ esc_html( $orderby_input['field'] ) ] = ! empty( $orderby_input['order'] ) ? esc_html( $orderby_input['order'] ) : 'DESC';
				}
			}
		}

		/**
		 * Handle setting dynamic $query_args based on the source (higher level query)
		 */
		if ( true === is_object( $source ) ) {
			switch ( true ) {
				case $source instanceof \WP_Post:
					$query_args['post_type'] = $source->name;
					break;
				case $source instanceof \WP_Term:
					$query_args['tax_query'] = [
						[
							'taxonomy' => $source->taxonomy,
							'terms'    => [ $source->term_id ],
							'field'    => 'term_id',
						],
					];
					break;
				case $source instanceof \WP_User:
					$query_args['author'] = $source->ID;
					break;
				default:
					break;
			}
		}

		/**
		 * If the post_type is "attachment" set the default "post_status" $query_arg to "inherit"
		 */
		if ( 'attachment' === self::$post_type ) {
			$query_args['post_status'] = 'inherit';
		}

		/**
		 * Ensure that the query is ordered by ID in addition to any other orderby options
		 */
		$query_args['orderby'] = array_merge( $query_args['orderby'], [
			'ID' => esc_html( $id_order ),
		] );

		/**
		 * Set the posts_per_page, ensuring it doesn't exceed the amount set as the $max_query_amount
		 *
		 * @since 0.0.6
		 */
		$pagination_increase = ! empty( $args['first'] ) && ( empty( $args['after'] ) && empty( $args['before'] ) ) ? 0 : 1;
		$query_args['posts_per_page'] = self::get_query_amount( $source, $args, $context, $info ) + absint( $pagination_increase );

		return $query_args;

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

		return $query;
	}

	/**
	 * This takes an array of items, the $args and the $query and returns the connection including
	 * the edges and page info
	 *
	 * @param array $items The items
	 * @param array $args  The $args that were passed to the query
	 * @param mixed $query The query that
	 *
	 * @return array
	 */
	public static function get_connection( $query,  array $items, $source, array $args, AppContext $context, ResolveInfo $info ) {

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
		$edges = self::get_edges( $items, $source, $args, $context, $info );

		/**
		 * Find the first_edge and last_edge
		 */
		$first_edge = $edges ? $edges[0] : null;
		$last_edge  = $edges ? $edges[ count( $edges ) - 1 ] : null;

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
			]
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

		if ( ! empty( $items ) && is_array( $items ) ) {
			foreach ( $items as $item ) {
				$edges[] = [
					'cursor' => ArrayConnection::offsetToCursor( $item->ID ),
					'node'   => $item,
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
