<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WP_Comment_Query;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

/**
 * Class CommentConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class CommentConnectionResolver extends AbstractConnectionResolver {

	/**
	 * {@inheritDoc}
	 *
	 * @var \WP_Comment_Query
	 */
	protected $query;

	/**
	 * {@inheritDoc}
	 */
	public function get_query_args() {

		/**
		 * Prepare for later use
		 */
		$last  = ! empty( $this->args['last'] ) ? $this->args['last'] : null;
		$first = ! empty( $this->args['first'] ) ? $this->args['first'] : null;

		$query_args = [];

		/**
		 * Don't calculate the total rows, it's not needed and can be expensive
		 */
		$query_args['no_found_rows'] = true;

		/**
		 * Set the default comment_status for Comment Queries to be "comment_approved"
		 */
		$query_args['status'] = 'approve';

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 *
		 * @since 0.0.6
		 */
		$query_args['number'] = min( max( absint( $first ), absint( $last ), 10 ), $this->get_query_amount() ) + 1;

		/**
		 * Set the default order
		 */
		$query_args['orderby'] = 'comment_date';

		/**
		 * Take any of the $this->args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Term_Query names to be used in the WP_Term_Query
		 *
		 * @since 0.0.5
		 */
		$input_fields = [];
		if ( ! empty( $this->args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields( $this->args['where'] );
		}

		/**
		 * Merge the default $query_args with the $this->args that were entered
		 * in the query.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $input_fields ) ) {
			$query_args = array_merge( $query_args, $input_fields );
		}

		/**
		 * If the current user cannot moderate comments, do not include unapproved comments
		 */
		if ( ! current_user_can( 'moderate_comments' ) ) {
			$query_args['status']             = [ 'approve' ];
			$query_args['include_unapproved'] = get_current_user_id() ? [ get_current_user_id() ] : [];
			if ( empty( $query_args['include_unapproved'] ) ) {
				unset( $query_args['include_unapproved'] );
			}
		}

		/**
		 * Throw an exception if the query is attempted to be queried by
		 */
		if ( 'comment__in' === $query_args['orderby'] && empty( $query_args['comment__in'] ) ) {
			throw new UserError( __( 'In order to sort by comment__in, an array of IDs must be passed as the commentIn argument', 'wp-graphql' ) );
		}

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( $query_args['order'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'ASC' : 'DESC';
		}

		/**
		 * Set the graphql_cursor_compare to determine
		 * whether the data is being paginated forward (>) or backward (<)
		 * default to forward
		 */
		$query_args['graphql_cursor_compare'] = ( isset( $last ) ) ? '>' : '<';

		// these args are used by the cursor builder to generate the proper SQL needed to respect the cursors
		$query_args['graphql_after_cursor']  = $this->get_after_offset();
		$query_args['graphql_before_cursor'] = $this->get_before_offset();

		/**
		 * Pass the graphql $this->args to the WP_Query
		 */
		$query_args['graphql_args'] = $this->args;

		// encode the graphql args as a cache domain to ensure the
		// graphql_args are used to identify different queries.
		// see: https://core.trac.wordpress.org/ticket/35075
		$encoded_args               = wp_json_encode( $this->args );
		$query_args['cache_domain'] = ! empty( $encoded_args ) ? 'graphql:' . md5( $encoded_args ) : 'graphql';

		/**
		 * We only want to query IDs because deferred resolution will resolve the full
		 * objects.
		 */
		$query_args['fields'] = 'ids';

		/**
		 * Filter the query_args that should be applied to the query. This filter is applied AFTER the input args from
		 * the GraphQL Query have been applied and has the potential to override the GraphQL Query Input Args.
		 *
		 * @param array       $query_args array of query_args being passed to the
		 * @param mixed       $source     source passed down from the resolve tree
		 * @param array       $args       array of arguments input in the field as part of the GraphQL query
		 * @param \WPGraphQL\AppContext $context object passed down the resolve tree
		 * @param \GraphQL\Type\Definition\ResolveInfo $info info about fields passed down the resolve tree
		 *
		 * @since 0.0.6
		 */
		return apply_filters( 'graphql_comment_connection_query_args', $query_args, $this->source, $this->args, $this->context, $this->info );
	}

	/**
	 * Get_query
	 *
	 * Return the instance of the WP_Comment_Query
	 *
	 * @return \WP_Comment_Query
	 * @throws \Exception
	 */
	public function get_query() {
		return new WP_Comment_Query( $this->query_args );
	}

	/**
	 * Return the name of the loader
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'comment';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		/** @var array $ids */
		$ids = ! empty( $this->query->get_comments() ) ? $this->query->get_comments() : [];

		// If we're going backwards, we need to reverse the array.
		if ( ! empty( $this->args['last'] ) ) {
			$ids = array_reverse( $ids );
		}

		return $ids;
	}

	/**
	 * This can be used to determine whether the connection query should even execute.
	 *
	 * For example, if the $source were a post_type that didn't support comments, we could prevent
	 * the connection query from even executing. In our case, we prevent comments from even showing
	 * in the Schema for post types that don't have comment support, so we don't need to worry
	 * about that, but there may be other situations where we'd need to prevent it.
	 *
	 * @return boolean
	 */
	public function should_execute() {
		return true;
	}


	/**
	 * Filters the GraphQL args before they are used in get_query_args().
	 *
	 * @return array
	 */
	public function get_args(): array {
		$args = $this->args;

		if ( ! empty( $args['where'] ) ) {
			// Ensure all IDs are converted to database IDs.
			foreach ( $args['where'] as $input_key => $input_value ) {
				if ( empty( $input_value ) ) {
					continue;
				}

				switch ( $input_key ) {
					case 'authorIn':
					case 'authorNotIn':
					case 'commentIn':
					case 'commentNotIn':
					case 'parentIn':
					case 'parentNotIn':
					case 'contentAuthorIn':
					case 'contentAuthorNotIn':
					case 'contentId':
					case 'contentIdIn':
					case 'contentIdNotIn':
					case 'contentAuthor':
					case 'userId':
						if ( is_array( $input_value ) ) {
							$args['where'][ $input_key ] = array_map( function ( $id ) {
								return Utils::get_database_id_from_id( $id );
							}, $input_value );
							break;
						}
						$args['where'][ $input_key ] = Utils::get_database_id_from_id( $input_value );
						break;
					case 'includeUnapproved':
						if ( is_string( $input_value ) ) {
							$input_value = [ $input_value ];
						}
						$args['where'][ $input_key ] = array_map( function ( $id ) {
							if ( is_email( $id ) ) {
								return $id;
							}

							return Utils::get_database_id_from_id( $id );
						}, $input_value );
						break;
				}
			}
		}

		/**
		 *
		 * Filters the GraphQL args before they are used in get_query_args().
		 *
		 * @param array                     $args                The GraphQL args passed to the resolver.
		 * @param \WPGraphQL\Data\Connection\CommentConnectionResolver $connection_resolver Instance of the ConnectionResolver
		 *
		 * @since 1.11.0
		 */
		return apply_filters( 'graphql_comment_connection_args', $args, $this );
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to
	 * WP_Comment_Query friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be
	 * down to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array $args The array of query arguments
	 *
	 * @since  0.0.5
	 * @return array
	 */
	public function sanitize_input_fields( array $args ) {

		$arg_mapping = [
			'authorEmail'        => 'author_email',
			'authorIn'           => 'author__in',
			'authorNotIn'        => 'author__not_in',
			'authorUrl'          => 'author_url',
			'commentIn'          => 'comment__in',
			'commentNotIn'       => 'comment__not_in',
			'commentType'        => 'type',
			'commentTypeIn'      => 'type__in',
			'commentTypeNotIn'   => 'type__not_in',
			'contentAuthor'      => 'post_author',
			'contentAuthorIn'    => 'post_author__in',
			'contentAuthorNotIn' => 'post_author__not_in',
			'contentId'          => 'post_id',
			'contentIdIn'        => 'post__in',
			'contentIdNotIn'     => 'post__not_in', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn
			'contentName'        => 'post_name',
			'contentParent'      => 'post_parent',
			'contentStatus'      => 'post_status',
			'contentType'        => 'post_type',
			'includeUnapproved'  => 'include_unapproved',
			'parentIn'           => 'parent__in',
			'parentNotIn'        => 'parent__not_in',
			'userId'             => 'user_id',
		];

		/**
		 * Map and sanitize the input args to the WP_Comment_Query compatible args
		 */
		$query_args = Utils::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 *
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_comment_query', $query_args, $args, $this->source, $this->args, $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

	/**
	 * Determine whether or not the the offset is valid, i.e the comment corresponding to the
	 * offset exists. Offset is equivalent to comment_id. So this function is equivalent to
	 * checking if the comment with the given ID exists.
	 *
	 * @param int $offset The ID of the node used for the cursor offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return ! empty( get_comment( $offset ) );
	}

}
