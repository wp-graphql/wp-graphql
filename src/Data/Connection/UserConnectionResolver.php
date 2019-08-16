<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Types;

/**
 * Class UserConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class UserConnectionResolver extends AbstractConnectionResolver {

	/**
	 * Determines whether the query should execute at all. It's possible that in some
	 * situations we may want to prevent the underlying query from executing at all.
	 *
	 * In those cases, this would be set to false.
	 *
	 * @return bool
	 */
	public function should_execute() {
		return true;
	}

	/**
	 * Converts the args that were input to the connection into args that can be executed
	 * by WP_User_Query
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_query_args() {
		/**
		 * Set the $query_args based on various defaults and primary input $args
		 */
		$query_args['count_total'] = false;
		$query_args['offset']      = $this->get_offset();
		$query_args['order']       = ! empty( $this->args['last'] ) ? 'ASC' : 'DESC';

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$query_args['number'] = $this->get_query_amount();

		/**
		 * Take any of the input $args (under the "where" input) that were part of the GraphQL query and map and
		 * sanitize their GraphQL input to apply to the WP_Query
		 */
		$input_fields = [];
		if ( ! empty( $this->args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields( $this->args['where'] );
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
		 * Only query the IDs and let deferred resolution query the nodes
		 */
		$query_args['fields'] = 'ID';

		/**
		 * If the request is not authenticated, limit the query to users that have
		 * published posts, as they're considered publicly facing users.
		 */
		if ( ! is_user_logged_in() ) {
			$query_args['has_published_posts'] = true;
		}

		return $query_args;
	}

	/**
	 * Return an instance of the WP_User_Query with the args for the connection being executed
	 *
	 * @return mixed|\WP_User_Query
	 * @throws \Exception
	 */
	public function get_query() {
		return new \WP_User_Query( $this->query_args );
	}

	/**
	 * Returns an array of items from the query being executed.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_items() {
		$results = $this->query->get_results();

		return ! empty( $results ) ? $results : [];
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_User_Query
	 * friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be
	 * down to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array $args The query "where" args
	 *
	 * @since  0.0.5
	 * @return array
	 * @access protected
	 */
	protected function sanitize_input_fields( array $args ) {

		/**
		 * Only users with the "list_users" capability can filter users by roles
		 */
		if ( (
				 ! empty( $args['roleIn'] ) ||
				 ! empty( $args['roleNotIn'] ) ||
				 ! empty( $args['role'] )
			 ) &&
			 ! current_user_can( 'list_users' )
		) {
			throw new UserError( __( 'Sorry, you are not allowed to filter users by role.', 'wp-graphql' ) );
		}

		$arg_mapping = [
			'roleIn'            => 'role__in',
			'roleNotIn'         => 'role__not_in',
			'searchColumns'     => 'search_columns',
			'hasPublishedPosts' => 'has_published_posts',
			'nicenameIn'        => 'nicename__in',
			'nicenameNotIn'     => 'nicename__not_in',
			'loginIn'           => 'login__in',
			'loginNotIn'        => 'login__not_in',
		];

		/**
		 * Map and sanitize the input args to the WP_User_Query compatible args
		 */
		$query_args = Types::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 *
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @param array       $query_args The mapped query args
		 * @param array       $args       The query "where" args
		 * @param mixed       $source     The query results of the query calling this relation
		 * @param array       $all_args   Array of all the query args (not just the "where" args)
		 * @param AppContext  $context    The AppContext object
		 * @param ResolveInfo $info       The ResolveInfo object
		 *
		 * @since 0.0.5
		 * @return array
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_comment_query', $query_args, $args, $this->source, $this->args, $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}
}
