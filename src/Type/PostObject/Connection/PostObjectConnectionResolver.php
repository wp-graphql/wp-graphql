<?php
namespace WPGraphQL\Type\PostObject\Connection;

use GraphQL\Type\Definition\ResolveInfo;
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
	 * This runs the query and returns the repsonse
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
		 * Set the $query_args based on various defaults and primary input $args
		 */
		$query_args['ignore_sticky_posts'] = true;
		$query_args['no_found_rows']       = true;
		$query_args['post_type']           = self::$post_type;
		$query_args['offset']              = self::get_offset( $args );

		/**
		 * If pagination info is requested as part of the query, we need to run the query with no_found_rows
		 * set to false, so that we can get the amount of posts matching the query to accurately return
		 * pagination info
		 */
		$field_selection = $info->getFieldSelection( 3 );
		if ( ! empty( $field_selection['pageInfo'] ) || ! empty( $field_selection['edges']['cursor'] ) ) {
			$query_args['no_found_rows'] = false;
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
		 * Set the posts_per_page, ensuring it doesn't exceed the amount set as the $max_query_amount
		 *
		 * @since 0.0.6
		 */
		$query_args['posts_per_page'] = self::get_query_amount( $source, $args, $context, $info );

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
		 * Filter the query_args that should be applied to the query. This filter is applied AFTER the input args from
		 * the GraphQL Query have been applied and has the potential to override the GraphQL Query Input Args.
		 *
		 * @param array       $query_args array of query_args being passed to the
		 * @param mixed       $source     source passed down from the resolve tree
		 * @param array       $args       array of arguments input in the field as part of the GraphQL query
		 * @param AppContext  $context    object passed down zthe resolve tree
		 * @param ResolveInfo $info       info about fields passed down the resolve tree
		 *
		 * @since 0.0.6
		 */
		$query_args = apply_filters( 'graphql_post_object_connection_query_args', $query_args, $source, $args, $context, $info );

		/**
		 * Return the $query_args
		 */
		return $query_args;
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_Query
	 * friendly keys. There's probably a cleaner/more dynamic way to approach this, but
	 * this was quick. I'd be down to explore more dynamic ways to map this, but for
	 * now this gets the job done.
	 *
	 * @param array       $args      Query "where" args
	 * @param string      $post_type The post type for the query
	 * @param mixed       $source    The query results for a query calling this
	 * @param array       $all_args  All of the arguments for the query (not just the "where" args)
	 * @param AppContext  $context   The AppContext object
	 * @param ResolveInfo $info      The ResolveInfo object
	 *
	 * @since  0.0.5
	 * @access public
	 * @return array
	 */
	public static function sanitize_input_fields( array $args, $source, array $all_args, AppContext $context, ResolveInfo $info ) {

		$arg_mapping = [
			'authorName'    => 'author_name',
			'authorIn'      => 'author__in',
			'authorNotIn'   => 'author__not_in',
			'categoryName'  => 'category_name',
			'categoryAnd'   => 'category__and',
			'categoryIn'    => 'category__in',
			'categoryNotIn' => 'category__not_in',
			'tagId'         => 'tag_id',
			'tagIds'        => 'tag__and',
			'tagNotIn'      => 'tag__not_in',
			'tagSlugAnd'    => 'tag_slug__and',
			'tagSlugIn'     => 'tag_slug__in',
			'search'        => 's',
			'id'            => 'p',
			'parent'        => 'post_parent',
			'parentIn'      => 'post_parent__in',
			'parentNotIn'   => 'post_parent__not_in',
			'in'            => 'post__in',
			'notIn'         => 'post__not_in',
			'nameIn'        => 'post_name__in',
			'hasPassword'   => 'has_password',
			'password'      => 'post_password',
			'status'        => 'post_status',
			'dateQuery'     => 'date_query',
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
