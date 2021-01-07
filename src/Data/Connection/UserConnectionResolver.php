<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\User;
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

	public function get_loader_name() {
		return 'user';
	}

	/**
	 * Converts the args that were input to the connection into args that can be executed
	 * by WP_User_Query
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_query_args() {
		$query_args = [];

		/**
		 * Prepare for later use
		 */
		$last = ! empty( $this->args['last'] ) ? $this->args['last'] : null;

		/**
		 * Set the $query_args based on various defaults and primary input $args
		 */
		$query_args['count_total'] = false;

		/**
		 * Set the graphql_cursor_offset which is used by Config::graphql_wp_user_query_cursor_pagination_support
		 * to filter the WP_User_Query to support cursor pagination
		 */
		$cursor_offset                        = $this->get_offset();
		$query_args['graphql_cursor_offset']  = $cursor_offset;
		$query_args['graphql_cursor_compare'] = ( ! empty( $last ) ) ? '>' : '<';

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 *
		 * We query one extra than what is being asked for so that we can determine if there is a next
		 * page.
		 */
		$query_args['number'] = $this->get_query_amount() + 1;

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
		 * Map the orderby inputArgs to the WP_User_Query
		 */
		if ( ! empty( $this->args['where']['orderby'] ) && is_array( $this->args['where']['orderby'] ) ) {
			$query_args['orderby'] = [];
			foreach ( $this->args['where']['orderby'] as $orderby_input ) {
				/**
				 * These orderby options should not include the order parameter.
				 */
				if ( in_array(
					$orderby_input['field'],
					[
						'login__in',
						'nicename__in',
					],
					true
				) ) {
					$query_args['orderby'] = esc_sql( $orderby_input['field'] );
				} elseif ( ! empty( $orderby_input['field'] ) ) {
					$query_args['orderby'] = [
						esc_sql( $orderby_input['field'] ) => esc_sql( $orderby_input['order'] ),
					];
				}
			}
		}

		/**
		 * Convert meta_value_num to seperate meta_value value field which our
		 * graphql_wp_term_query_cursor_pagination_support knowns how to handle
		 */
		if ( isset( $query_args['orderby'] ) && 'meta_value_num' === $query_args['orderby'] ) {
			$query_args['orderby'] = [
				'meta_value' => empty( $query_args['order'] ) ? 'DESC' : $query_args['order'],
			];
			unset( $query_args['order'] );
			$query_args['meta_type'] = 'NUMERIC';
		}

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( $query_args['orderby'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'ASC' : 'DESC';
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
	 * Returns an array of ids from the query being executed.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_ids() {
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
	 */
	protected function sanitize_input_fields( array $args ) {

		/**
		 * Only users with the "list_users" capability can filter users by roles
		 */
		if (
			(
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
		 * from a GraphQL Query to the WP_User_Query
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
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_user_query', $query_args, $args, $this->source, $this->args, $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

	/**
	 * Determine whether or not the the offset is valid, i.e the user corresponding to the offset
	 * exists. Offset is equivalent to user_id. So this function is equivalent to checking if the
	 * user with the given ID exists.
	 *
	 * @param int $offset The ID of the node used as the offset in the cursor
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return ! empty( get_user_by( 'ID', absint( $offset ) ) );
	}
}
