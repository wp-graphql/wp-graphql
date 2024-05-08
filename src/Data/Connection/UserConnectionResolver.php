<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Error\UserError;
use WPGraphQL\Utils\Utils;

/**
 * Class UserConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<\WP_User_Query>
 */
class UserConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'user';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Exception
	 */
	protected function prepare_query_args( array $args ): array {
		$query_args = [];

		/**
		 * Prepare for later use
		 */
		$last = ! empty( $args['last'] ) ? $args['last'] : null;

		/**
		 * Set the $query_args based on various defaults and primary input $args
		 */
		$query_args['count_total'] = false;

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $args;

		/**
		 * Set the graphql_cursor_compare to determine what direction the
		 * query should be paginated
		 */
		$query_args['graphql_cursor_compare'] = ( ! empty( $last ) ) ? '>' : '<';

		$query_args['graphql_after_cursor']  = $this->get_after_offset();
		$query_args['graphql_before_cursor'] = $this->get_before_offset();

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
		if ( ! empty( $args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields( $args['where'] );
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
		if ( ! is_user_logged_in() && empty( $query_args['has_published_posts'] ) ) {
			$query_args['has_published_posts'] = true;
		}

		/**
		 * If `has_published_posts` is set to `attachment`, throw a warning.
		 *
		 * @todo Remove this when the `hasPublishedPosts` enum type changes.
		 *
		 * @see https://github.com/wp-graphql/wp-graphql/issues/2963
		 */
		if ( ! empty( $query_args['has_published_posts'] ) && 'attachment' === $query_args['has_published_posts'] ) {
			graphql_debug(
				__( 'The `hasPublishedPosts` where arg does not support the `ATTACHMENT` value, and will be removed from the possible enum values in a future release.', 'wp-graphql' ),
				[
					'operationName' => $this->context->operationName ?? '',
					'query'         => $this->context->query ?? '',
					'variables'     => $this->context->variables ?? '',
				]
			);
		}

		if ( ! empty( $query_args['search'] ) ) {
			$query_args['search']  = '*' . $query_args['search'] . '*';
			$query_args['orderby'] = 'user_login';
			$query_args['order']   = ! empty( $last ) ? 'DESC' : 'ASC';
		}

		/**
		 * Map the orderby inputArgs to the WP_User_Query
		 */
		if ( ! empty( $args['where']['orderby'] ) && is_array( $args['where']['orderby'] ) ) {
			foreach ( $args['where']['orderby'] as $orderby_input ) {
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
					$order = $orderby_input['order'];
					if ( ! empty( $args['last'] ) ) {
						if ( 'ASC' === $order ) {
							$order = 'DESC';
						} else {
							$order = 'ASC';
						}
					}

					$query_args['orderby'] = esc_sql( $orderby_input['field'] );
					$query_args['order']   = esc_sql( $order );
				}
			}
		}

		/**
		 * Convert meta_value_num to separate meta_value value field which our
		 * graphql_wp_term_query_cursor_pagination_support knowns how to handle
		 */
		if ( isset( $query_args['orderby'] ) && 'meta_value_num' === $query_args['orderby'] ) {
			$query_args['orderby'] = [
				'meta_value' => empty( $query_args['order'] ) ? 'DESC' : $query_args['order'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			];
			unset( $query_args['order'] );
			$query_args['meta_type'] = 'NUMERIC';
		}

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( $query_args['order'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'DESC' : 'ASC';
		}

		return $query_args;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function query_class(): string {
		return \WP_User_Query::class;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		/**
		 * @todo This is for b/c. We can just use $this->get_query().
		 */
		$queried = isset( $this->query ) ? $this->query : $this->get_query();

		/** @var int[] $ids */
		$ids = $queried->get_results();

		// If we're going backwards, we need to reverse the array.
		$args = $this->get_args();
		if ( ! empty( $args['last'] ) ) {
			$ids = array_reverse( $ids );
		}

		return $ids;
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_User_Query
	 * friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be
	 * down to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array<string,mixed> $args The query "where" args
	 *
	 * @return array<string,mixed>
	 * @throws \GraphQL\Error\UserError If the user does not have the "list_users" capability.
	 * @since  0.0.5
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
			throw new UserError( esc_html__( 'Sorry, you are not allowed to filter users by role.', 'wp-graphql' ) );
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
		$query_args = Utils::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 *
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the WP_User_Query
		 *
		 * @param array<string,mixed>                  $query_args The mapped query args
		 * @param array<string,mixed>                  $args       The query "where" args
		 * @param mixed                                $source     The query results of the query calling this relation
		 * @param array<string,mixed>                  $all_args   Array of all the query args (not just the "where" args)
		 * @param \WPGraphQL\AppContext                $context The AppContext object
		 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
		 *
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_user_query', $query_args, $args, $this->source, $this->get_args(), $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $offset The ID of the node used as the offset in the cursor.
	 */
	public function is_valid_offset( $offset ) {
		return (bool) get_user_by( 'ID', absint( $offset ) );
	}
}
