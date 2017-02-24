<?php
namespace WPGraphQL\Type\User\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class UserConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
 */
class UserConnectionResolver {

	/**
	 * Creates the connections for users
	 *
	 * @param mixed       $source  The results of the query calling this relation
	 * @param array       $args    The Query args
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since  0.5.0
	 * @throws \Exception
	 * @access public
	 */
	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {

		/**
		 * Get the subfields that were queried so we can make proper decisions
		 */
		$field_selection = $info->getFieldSelection( 5 );

		/**
		 * Get the cursor offset based on the Cursor passed to the after/before args
		 * @since 0.0.5
		 */
		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : null;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : null;

		/**
		 * Ensure the first/last values max at 100 items so that "number" query_arg doesn't exceed 100
		 * @since 0.0.5
		 */
		$first = 100 >= intval( $args['first'] ) ? intval( $args['first'] ) : 10;
		$last  = 100 >= intval( $args['last'] ) ? intval( $args['last'] ) : 10;

		/**
		 * Throw an error if mixed pagination paramaters are used that will lead to poor/confusing
		 * results.
		 *
		 * @since 0.0.5
		 */
		if ( ( ! empty( $args['first'] ) && ! empty( $args['before'] ) ) || ( ! empty( $args['last'] ) && ! empty( $args['after'] ) ) ) {
			throw new \Exception( __( 'Please provide only (first & after) OR (last & before). This can otherwise lead to confusing behavior', 'wp-graphql' ) );
		}
		if ( ! empty( $args['after'] ) && ! empty( $args['before'] ) ) {
			throw new \Exception( __( '"Before" and "After" should not be used together in arguments.', 'wp-graphql' ) );
		}
		if ( ! empty( $first ) && ! empty( $last ) ) {
			throw new \Exception( __( '"First" and "Last" should not be used together in arguments.', 'wp-graphql' ) );
		}

		/**
		 * Determine the number, order and offset to query based on the $first/$last/$before/$after args
		 * @since 0.0.5
		 */
		$query_args['number'] = 10;
		$query_args['offset'] = 0;

		if ( ! empty( $first ) ) {
			$query_args['order']  = 'DESC';
			$query_args['number'] = absint( $first );
			if ( ! empty( $before ) ) {
				$query_args['offset'] = 0;
			} elseif ( ! empty( $after ) ) {
				$query_args['offset'] = absint( $after + 1 );
			}
		} elseif ( ! empty( $last ) ) {
			$query_args['order']  = 'ASC';
			$query_args['number'] = absint( $last );
			if ( ! empty( $before ) ) {
				$query_args['order']  = 'DESC';
				$query_args['offset'] = ( $before - $last );
			} elseif ( ! empty( $after ) ) {
				$query_args['offset'] = 0;
			}
		}

		/**
		 * Set count_total to false by default to make queries more efficient by not having to
		 * calculate the entire set of data.
		 *
		 * @since 0.0.5
		 */
		$query_args['count_total'] = false;

		/**
		 * If "pageInfo" is in the fieldSelection, we need to calculate the pagination details, so
		 * we need to run the query with count_total set to true.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $args ) || ! empty( $field_selection['pageInfo'] ) ) {
			$query_args['count_total'] = true;
		}

		/**
		 * If the source of the Query is a Post object, adjust the query args to only query the
		 * user connected to the post object
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Post ) {
			$query_args['include'] = [ $source->post_author ];
		}

		/**
		 * If the source of the Query is a Comment object, adjust the query args to only query the
		 * user that is marked as the comment user ID
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Comment ) {
			$query_args['include'] = [ $source->user_ID ];
		}

		/**
		 * Take any of the $args that were part of the GraphQL query and map their GraphQL names to
		 * the WP_Term_Query names to be used in the WP_Term_Query
		 *
		 * @since 0.0.5
		 */
		$entered_args = [];
		if ( ! empty( $args['where'] ) ) {
			$entered_args = self::map_input_fields_to_get_terms( $args['where'], $source, $args, $context, $info );
		}

		/**
		 * Merge the default $query_args with the $args that were entered in the query.
		 * @since 0.0.5
		 */
		$query_args = array_merge( $query_args, $entered_args );

		/**
		 * Run the query
		 * @since 0.0.5
		 */
		$users_query = new \WP_User_Query( $query_args );
		$users_query->query();
		$user_results = $users_query->get_results();

		/**
		 * Throw an exception if no results were found.
		 * @since 0.0.5
		 */
		if ( empty( $user_results ) ) {
			throw new \Exception( __( 'No results were found for the query. Try broadening the arguments.', 'wp-graphql' ) );
		}

		/**
		 * If pagination info was selected and we know the entire length of the data set, we need to
		 * build the offsets based on the details we received back from the query and query_args
		 */
		$edge_count          = ! empty( $users_query->total_users ) ? absint( $users_query->total_users ) : count( $user_results );
		$meta['arrayLength'] = $edge_count;
		$meta['sliceStart']  = 0;

		/**
		 * Build the pagination details based on the arguments passed.
		 * @since 0.0.5
		 */
		if ( ! empty( $last ) ) {
			$meta['sliceStart'] = ( $edge_count - $last );
			$user_results       = array_reverse( $user_results );
			if ( ! empty( $before ) ) {
				$meta['sliceStart'] = absint( $before - $last );
			} elseif ( ! empty( $after ) ) {
				$meta['sliceStart'] = absint( $after );
			}
		} elseif ( ! empty( $first ) ) {
			if ( ! empty( $before ) ) {
				$meta['sliceStart'] = absint( 0 );
			} elseif ( ! empty( $after ) ) {
				$meta['sliceStart'] = absint( $after + 1 );
			}
		}

		/**
		 * Generate the array of posts with keys representing the position of the post in the
		 * greater array of data
		 *
		 * @since 0.0.5
		 */
		$users_array = [];
		if ( is_array( $user_results ) && ! empty( $user_results ) ) {
			$index = $meta['sliceStart'];
			foreach ( $user_results as $user ) {
				$users_array[ $index ] = $user;
				$index ++;
			}
		}

		/**
		 * Generate the Relay fields (pageInfo, Edges, Cursor, etc)
		 * @since 0.0.5
		 */
		$users = Relay::connectionFromArraySlice( $users_array, $args, $meta );

		/**
		 * Return the connection
		 * @since 0.0.5
		 */
		return $users;

	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_User_Query
	 * friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be
	 * down to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array       $args     The query "where" args
	 * @param mixed       $source   The query results of the query calling this relation
	 * @param array       $all_args Array of all the query args (not just the "where" args)
	 * @param AppContext  $context  The AppContext object
	 * @param ResolveInfo $info     The ResolveInfo object
	 *
	 * @since  0.0.5
	 * @return array
	 * @access private
	 */
	private static function map_input_fields_to_get_terms( $args, $source, $all_args, $context, $info ) {

		/**
		 * Start a fresh array
		 */
		$query_args = [];

		if ( ! empty( $args['role'] ) ) {
			$query_args['role'] = $args['role'];
		}

		if ( ! empty( $args['roleIn'] ) ) {
			$query_args['role__in'] = $args['roleIn'];
		}

		if ( ! empty( $args['roleNotIn'] ) ) {
			$query_args['role__not_in'] = $args['roleNotIn'];
		}

		if ( ! empty( $args['include'] ) ) {
			$query_args['include'] = $args['include'];
		}

		if ( ! empty( $args['exclude'] ) ) {
			$query_args['exclude'] = $args['exclude'];
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = $args['search'];
		}

		if ( ! empty( $args['searchColumns'] ) ) {
			$query_args['search_columns'] = $args['searchColumns'];
		}

		if ( ! empty( $args['orderby'] ) ) {
			$query_args['orderby'] = $args['orderby'];
		}

		if ( ! empty( $args['hasPublishedPosts'] ) ) {
			$query_args['has_published_posts'] = $args['hasPublishedPosts'];
		}

		if ( ! empty( $args['nicenameIn'] ) ) {
			$query_args['nicename__in'] = $args['nicenameIn'];
		}

		if ( ! empty( $args['nicenameNotIn'] ) ) {
			$query_args['nicename__not_in'] = $args['nicenameNotIn'];
		}

		if ( ! empty( $args['login'] ) ) {
			$query_args['login'] = $args['login'];
		}

		if ( ! empty( $args['loginIn'] ) ) {
			$query_args['login__in'] = $args['loginIn'];
		}

		if ( ! empty( $args['loginNotIn'] ) ) {
			$query_args['login__not_in'] = $args['loginNotIn'];
		}

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
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_comment_query', $query_args, $args, $source, $all_args, $context, $info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

}
