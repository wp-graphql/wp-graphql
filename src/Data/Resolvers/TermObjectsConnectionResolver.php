<?php
namespace WPGraphQL\Data\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;

class TermObjectsConnectionResolver {

	public function __construct( $taxonomy, $source, array $args, $context, ResolveInfo $info ) {
		$this->resolve( $taxonomy, $source, $args, $context, $info );
	}

	public static function resolve( $taxonomy, $source, array $args, $context, ResolveInfo $info ) {

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
		$last   = ( ! empty( $args['last'] ) ) ? $args['last'] : null;
		$first  = ( ! empty( $args['first'] ) ) ? $args['first'] : null;

		/**
		 * Throw an error if both First and Last were used, as they should not be used together as the
		 * first/last determines the order of the query results.
		 *
		 * @since 0.0.5
		 */
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
		if ( ! empty( $first ) ) {
			$query_args['order'] = 'DESC';
			$query_args['number'] = absint( $first );
			if ( ! empty( $before ) ) {
				$query_args['offset'] = 0;
			} elseif ( ! empty( $after ) ) {
				$query_args['offset'] = absint( $after + 1 );
			}
		} elseif ( ! empty( $last ) ) {
			$query_args['order'] = 'ASC';
			$query_args['number'] = absint( $last );
			if ( ! empty( $before ) ) {
				$query_args['order'] = 'DESC';
				$query_args['offset'] = ( $before - $last );
			} elseif ( ! empty( $after ) ) {
				$query_args['offset'] = 0;
			}
		}

		/**
		 * Set the post_type based on the $post_type passed to the resolver
		 * @since 0.0.5
		 */
		$query_args['taxonomy'] = $taxonomy;

		/**
		 * If the source of the Query is a Post object, adjust the query args
		 * to only query terms connected to the post object
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Post ) {
			$query_args['object_ids'] = $source->ID;
		}

		/**
		 * Take any of the $args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Term_Query names to be used in the WP_Term_Query
		 * @since 0.0.5
		 */
		$entered_args = [];
		if ( ! empty( $args['where'] ) ) {
			$entered_args = self::map_args_to_query_args( $args['where'] );
		}

		/**
		 * Merge the default $query_args with the $args that were entered
		 * in the query.
		 * @since 0.0.5
		 */
		$query_args = array_merge( $query_args, $entered_args );

		/**
		 * Run the query
		 * @since 0.0.5
		 */
		$term_query = new \WP_Term_Query( $query_args );

		/**
		 * Grab the terms from the results of the query
		 * @since 0.0.5
		 */
		$term_results = $term_query->terms;

		/**
		 * Throw an exception if no results were found.
		 * @since 0.0.5
		 */
		if ( empty( $term_results ) ) {
			throw new \Exception( __( 'No results were found for the query. Try broadening the 
			arguments.', 'wp-graphql' ) );
		}

		/**
		 * Default the $edge_count to the number of results returned from the query.
		 * @since 0.0.5
		 */
		$edge_count = count( $term_results );

		/**
		 * If "pageInfo" is in the fieldSelection, we need to calculate the pagination details, so we need to run
		 * the query with no_found_rows set to false.
		 * @since 0.0.5
		 */
		if ( ! empty( $args ) || ! empty( $field_selection['pageInfo'] ) ) {
			$count_args = $query_args;
			unset( $count_args['number'], $count_args['offset'] );
			$edge_count = wp_count_terms( $taxonomy, $count_args );
		}

		/**
		 * If pagination info was selected and we know the entire length of the data set, we need to build the offsets
		 * based on the details we received back from the query and query_args
		 */
		$meta['arrayLength'] = $edge_count;
		$meta['sliceStart'] = 0;

		/**
		 * Build the pagination details based on the arguments passed.
		 * @since 0.0.5
		 */
		if ( ! empty( $last ) ) {
			$meta['sliceStart'] = ( $edge_count - $last );
			$term_results = array_reverse( $term_results );
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
		 * Generate the array of terms with keys representing the position
		 * of the term in the greater array of data
		 * @since 0.0.5
		 */
		$terms_array = [];
		if ( is_array( $term_results ) && ! empty( $term_results ) ) {
			$index = $meta['sliceStart'];
			foreach ( $term_results as $term ) {
				$terms_array[ $index ] = $term;
				$index++;
			}
		}

		/**
		 * Generate the Relay fields (pageInfo, Edges, Cursor, etc)
		 * @since 0.0.5
		 */
		$terms = ArrayConnection::connectionFromArraySlice( $terms_array, $args, $meta );

		/**
		 * Return the connection
		 * @since 0.0.5
		 */
		return $terms;

	}

	/**
	 * map_args_to_query_args
	 *
	 * This maps the GraphQL "friendly" args to WP_Term_Query $args.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be down to explore
	 * more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @since 0.0.5
	 */
	public static function map_args_to_query_args( $args ) {
		return [];
	}

}