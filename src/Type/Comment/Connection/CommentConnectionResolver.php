<?php
namespace WPGraphQL\Type\Comment\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\ConnectionResolver;
use WPGraphQL\Types;

/**
 * Class CommentConnectionResolver - Connects the comments to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 */
class CommentConnectionResolver extends ConnectionResolver {

	public static function get_query( $query_args ) {
		$query = new \WP_Comment_Query( $query_args );

		return $query;
	}

	/**
	 * This prepares the $query_args for use in the connection query. This is where default $args are set, where dynamic
	 * $args from the $source get set, and where mapping the input $args to the actual $query_args occurs.
	 *
	 * @param             $source
	 * @param array       $args
	 * @param AppContext  $context
	 * @param ResolveInfo $info
	 *
	 * @return mixed
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Set the $query_args based on various defaults and primary input $args
		 */
		$query_args['no_found_rows'] = true;
		$query_args['offset']        = self::get_offset( $args );

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
					$query_args['post_id'] = absint( $source->ID );
					break;
				case $source instanceof \WP_User:
					$query_args['user_id'] = absint( $source->ID );
					break;
				case $source instanceof \WP_Comment:
					$query_args['parent'] = absint( $source->comment_ID );
					break;
				default:
					break;
			}
		}

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 *
		 * @since 0.0.6
		 */
		$query_args['number'] = self::get_query_amount( $source, $args, $context, $info );

		/**
		 * Take any of the $args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Term_Query names to be used in the WP_Term_Query
		 *
		 * @since 0.0.5
		 */
		$input_fields = [];
		if ( ! empty( $args['where'] ) ) {
			$input_fields = self::sanitize_input_fields( $args['where'], $source, $args, $context, $info );
		}

		/**
		 * Merge the default $query_args with the $args that were entered
		 * in the query.
		 *
		 * @since 0.0.5
		 */
		$query_args = array_merge( $query_args, $input_fields );

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
		$query_args = apply_filters( 'graphql_comment_connection_query_args', $query_args, $source, $args, $context, $info );

		return $query_args;
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to
	 * WP_Comment_Query friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be
	 * down to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array       $args     The array of query arguments
	 * @param mixed       $source   The query results
	 * @param array       $all_args Array of all of the original arguments (not just the "where"
	 *                              args)
	 * @param AppContext  $context  The AppContext object
	 * @param ResolveInfo $info     The ResolveInfo object for the query
	 *
	 * @since  0.0.5
	 * @access private
	 * @return array
	 */
	public static function sanitize_input_fields( array $args, $source, array $all_args, AppContext $context, ResolveInfo $info ) {

		$arg_mapping = [
			'authorEmail'        => 'author_email',
			'authorIn'           => 'author__in',
			'authorNotIn'        => 'author__not_in',
			'authorUrl'          => 'author_url',
			'commentIn'          => 'comment__in',
			'commentNotIn'       => 'comment__not_in',
			'contentAuthor'      => 'post_author',
			'contentAuthorIn'    => 'post_author__in',
			'contentAuthorNotIn' => 'post_author__not_in',
			'contentId'          => 'post_id',
			'contentIdIn'        => 'post__in',
			'contentIdNotIn'     => 'post__not_in',
			'contentName'        => 'post_name',
			'contentParent'      => 'post_parent',
			'contentStatus'      => 'post_status',
			'contentType'        => 'post_type',
			'includeUnapproved'  => 'includeUnapproved',
			'parentIn'           => 'parent__in',
			'parentNotIn'        => 'parent__not_in',
			'userId'             => 'user_id',
		];

		/**
		 * Map and sanitize the input args to the WP_Comment_Query compatible args
		 */
		$query_args = Types::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 *
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_comment_query', $query_args, $args, $source, $all_args, $context, $info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}
}
