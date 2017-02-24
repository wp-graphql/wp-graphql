<?php
namespace WPGraphQL\Type\Comment\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class CommentConnectionResolver - Connects the comments to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 */
class CommentConnectionResolver {

	/**
	 * Runs the query for comments
	 *
	 * @param mixed       $source  Data returned from the query
	 * @param array       $args    Args for the query
	 * @param AppContext  $context AppContext object for the query
	 * @param ResolveInfo $info    ResolveInfo object
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
		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : 0;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : 0;

		/**
		 * Ensure the first/last values max at 100 items so that "number" query_arg doesn't exceed 100
		 * @since 0.0.5
		 */
		$first = 100 >= intval( $args['first'] ) ? $args['first'] : 10;
		$last  = 100 >= intval( $args['last'] ) ? $args['last'] : 10;


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
		if ( ! empty( $args['first'] ) && ! empty( $args['last'] ) ) {
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
		 * Set no_found_rows to true by default to make queries more efficient by not having to calculate
		 * the entire set of data.
		 * @since 0.0.5
		 */
		$query_args['no_found_rows'] = true;

		/**
		 * If "pageInfo" is in the fieldSelection, we need to calculate the pagination details, so we need to run
		 * the query with no_found_rows set to false.
		 * @since 0.0.5
		 */
		if ( ! empty( $args ) || ! empty( $field_selection['pageInfo'] ) ) {
			$query_args['no_found_rows'] = false;
		}

		/**
		 * If the query source is a WP_Post object,
		 * adjust the query args to only query for comments connected
		 * to that post_object
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Post && absint( $source->ID ) ) {
			$query_args['post_id'] = absint( $source->ID );
		}

		/**
		 * If the query source is a WP_User object,
		 * adjust the query args to only query for the comments connected
		 * to that user
		 */
		if ( $source instanceof \WP_User && absint( $source->ID ) ) {
			$query_args['user_id'] = $source->ID;
		}

		/**
		 * If the query source is a WP_Comment object,
		 * adjust the query args to only query for comments that have
		 * the source ID as their parent
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Comment && absint( $source->comment_ID ) ) {
			$query_args['parent'] = absint( $source->comment_ID );
		}

		/**
		 * Take any of the $args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Term_Query names to be used in the WP_Term_Query
		 * @since 0.0.5
		 */
		$entered_args = [];
		if ( ! empty( $args['where'] ) ) {
			$entered_args = self::map_input_fields_to_get_terms( $args['where'], $source, $args, $context, $info );
		}

		/**
		 * Merge the default $query_args with the $args that were entered
		 * in the query.
		 * @since 0.0.5
		 */
		$query_args = array_merge( $query_args, $entered_args );

		$comments_query   = new \WP_Comment_Query( $query_args );
		$comments_results = $comments_query->comments;

		/**
		 * Throw an exception if no results were found.
		 * @since 0.0.5
		 */
		if ( empty( $comments_results ) ) {
			throw new \Exception( __( 'No results were found for the query. Try broadening the arguments.', 'wp-graphql' ) );
		}

		/**
		 * If pagination info was selected and we know the entire length of the data set, we need to build the offsets
		 * based on the details we received back from the query and query_args
		 */
		$edge_count          = ! empty( $comments_query->found_comments ) ? absint( $comments_query->found_comments ) : count( $comments_results );
		$meta['arrayLength'] = $edge_count;
		$meta['sliceStart']  = 0;

		/**
		 * Build the pagination details based on the arguments passed.
		 * @since 0.0.5
		 */
		if ( ! empty( $last ) ) {
			$meta['sliceStart'] = ( $edge_count - $last );
			$comments_results   = array_reverse( $comments_results );
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
		 * Generate the array of posts with keys representing the position
		 * of the post in the greater array of data
		 * @since 0.0.5
		 */
		$comments_array = [];
		if ( is_array( $comments_results ) && ! empty( $comments_results ) ) {
			$index = ( 0 <= $meta['sliceStart'] ) ? $meta['sliceStart'] : 0;
			foreach ( $comments_results as $post ) {
				$comments_array[ $index ] = $post;
				$index ++;
			}
		}

		/**
		 * Generate the Relay fields (pageInfo, Edges, Cursor, etc)
		 * @since 0.0.5
		 */
		$comments = Relay::connectionFromArraySlice( $comments_array, $args, $meta );

		/**
		 * Return the connection
		 * @since 0.0.5
		 */
		return $comments;

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
	private static function map_input_fields_to_get_terms( $args, $source, $all_args, $context, $info ) {

		/**
		 * Start a fresh array
		 */
		$query_args = [];

		if ( ! empty( $args['authorEmail'] ) ) {
			$query_args['author_email'] = $args['authorEmail'];
		}

		if ( ! empty( $args['authorUrl'] ) ) {
			$query_args['author_url'] = $args['authorUrl'];
		}

		if ( ! empty( $args['authorIn'] ) ) {
			$query_args['author__in'] = $args['authorIn'];
		}

		if ( ! empty( $args['authorNotIn'] ) ) {
			$query_args['author__not_in'] = $args['authorNotIn'];
		}

		if ( ! empty( $args['commentIn'] ) ) {
			$query_args['comment__in'] = $args['commentIn'];
		}

		if ( ! empty( $args['commentNotIn'] ) ) {
			$query_args['comment__not_in'] = $args['commentNotIn'];
		}

		if ( ! empty( $args['includeUnapproved'] ) ) {
			$query_args['include_unapproved'] = $args['includeUnapproved'];
		}

		if ( ! empty( $args['karma'] ) ) {
			$query_args['karma'] = $args['karma'];
		}

		if ( ! empty( $args['parent'] ) ) {
			$query_args['parent'] = $args['parent'];
		}

		if ( ! empty( $args['parentIn'] ) ) {
			$query_args['parent__in'] = $args['parentIn'];
		}

		if ( ! empty( $args['parentNotIn'] ) ) {
			$query_args['parent__not_in'] = $args['parentNotIn'];
		}

		if ( ! empty( $args['contentAuthorIn'] ) ) {
			$query_args['post_author__in'] = $args['contentAuthorIn'];
		}

		if ( ! empty( $args['contentAuthorNotIn'] ) ) {
			$query_args['post_author__not_in'] = $args['contentAuthorNotIn'];
		}

		if ( ! empty( $args['contentId'] ) ) {
			$query_args['post_id'] = $args['contentId'];
		}

		if ( ! empty( $args['contentIdIn'] ) ) {
			$query_args['post__in'] = $args['contentIdIn'];
		}

		if ( ! empty( $args['contentIdNotIn'] ) ) {
			$query_args['post__not_in'] = $args['contentIdNotIn'];
		}

		if ( ! empty( $args['contentAuthor'] ) ) {
			$query_args['post_author'] = $args['contentAuthor'];
		}

		if ( ! empty( $args['contentStatus'] ) ) {
			$query_args['post_status'] = $args['contentStatus'];
		}

		if ( ! empty( $args['contentType'] ) ) {
			$query_args['post_type'] = $args['contentType'];
		}

		if ( ! empty( $args['contentName'] ) ) {
			$query_args['post_name'] = $args['contentName'];
		}

		if ( ! empty( $args['contentParent'] ) ) {
			$query_args['post_parent'] = $args['contentParent'];
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = $args['search'];
		}

		if ( ! empty( $args['userId'] ) ) {
			$query_args['user_id'] = $args['userId'];
		}

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
