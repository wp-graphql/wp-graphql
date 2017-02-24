<?php
namespace WPGraphQL\Type\TermObject\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;

/**
 * Class TermObjectConnectionResolver
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class TermObjectConnectionResolver {

	/**
	 * resolve
	 * This handles resolving a query for post objects (of any specified $taxonomy) from the
	 * root_query or from any connection where term_objects are queryable. This resolver takes in
	 * the Relay standard args (before, after, first, last) and uses them to query from the
	 * get_posts query and return results according to the Relay spec.
	 *
	 * PAGINATION DETAILS: For backward pagination, last and before should be used together.
	 * - last should be a non-negative integer
	 * - before should be a cursor which contains the offset of the position in the overall
	 *   collection of data For forward pagination, first and after should be used together.
	 * - first should be a non-negative integer
	 * - after should be a cursor which contains the offset of the position in the overall
	 *   collection of data
	 *
	 * PAGINATION ALGORITHM: If $first is set:
	 * - if $first is less than 0, throw an error
	 * - if $edges has length greater than first, slice the $edges to be the length of $first be
	 *   removing $edges from the end of $edges If $last is set:
	 * - If $last is less than 0, throw an error
	 * - if $edges has length greater than $last, slice the $edges to be the length of $last by
	 *   removing $edges from the start of $edges ADDITIONAL ARGUMENTS: Additional "where" arguments
	 *   are mapped from the GraphQL friendly names to get_terms allowed names and are applied to the
	 *   get_terms query appropriately.
	 *
	 * @param string      $taxonomy Name of the taxonomy we are making a connection for
	 * @param mixed       $source   Results of the query calling this connection
	 * @param array       $args     Query arguments
	 * @param AppContext  $context  The AppContext object
	 * @param ResolveInfo $info     The ResolveInfo object
	 *
	 * @return array
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve( $taxonomy, $source, array $args, $context, ResolveInfo $info ) {

		/**
		 * Get the sub fields that were queried so we can make proper decisions
		 */
		$field_selection = $info->getFieldSelection( 5 );

		/**
		 * Get the cursor offset based on the Cursor passed to the after/before args
		 * @since 0.0.5
		 */
		$after = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : null;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : null;

		/**
		 * Ensure the first/last values max at 100 items so that "number" query_arg doesn't
		 * exceed 100
		 * @since 0.0.5
		 */
		$first = 100 >= intval( $args['first'] ) ? $args['first'] : 10;
		$last = 100 >= intval( $args['last'] ) ? $args['last'] : 10;

		/**
		 * Throw an error if both First and Last were used, as they should not be used together as the
		 * first/last determines the order of the query results.
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
		$query_args['number'] = 10;
		$query_args['offset'] = 0;

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
		 * If the source of the Query is a Post object, adjust the query args to only query terms
		 * connected to the post object
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Post ) {
			$query_args['object_ids'] = $source->ID;
		}

		/**
		 * Take any of the $args that were part of the GraphQL query and map their GraphQL names to
		 * the WP_Term_Query names to be used in the WP_Term_Query
		 * @since 0.0.5
		 */
		$entered_args = [];
		if ( ! empty( $args['where'] ) ) {
			$entered_args = self::map_input_fields_to_get_terms( $args['where'], $taxonomy, $source, $args, $context, $info );
		}

		/**
		 * Merge the default $query_args with the $args that were entered in the query.
		 * @since 0.0.5
		 */
		$query_args = array_merge( $query_args, $entered_args );

		/**
		 * Run the query
		 * NOTE: We were using new \WP_Term_Query and it was working fine, but with get_terms the
		 * performance is ~300% faster... seems interesting as get_terms is just a wrapper for
		 * \WP_Term_Query... so might be worth investigating further what causes the
		 * difference in performance and if we should stick with this or move back
		 * to \WP_Term_Query...
		 *
		 * @since 0.0.5
		 */
		$term_query = get_terms( $query_args );

		/**
		 * Grab the terms from the results of the query
		 * @since 0.0.5
		 */
		$term_results = $term_query;

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
		 * If "pageInfo" is in the fieldSelection, we need to calculate the pagination details, so
		 * we need to run the query with no_found_rows set to false.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $args ) || ! empty( $field_selection['pageInfo'] ) ) {
			$count_args = $query_args;
			unset( $count_args['number'], $count_args['offset'] );
			$edge_count = wp_count_terms( $taxonomy, $count_args );
		}

		/**
		 * If pagination info was selected and we know the entire length of the data set, we need to
		 * build the offsets based on the details we received back from the query and query_args
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
		 * Generate the array of terms with keys representing the position of the term in the
		 * greater array of data
		 *
		 * @since 0.0.5
		 */
		$terms_array = [];
		if ( is_array( $term_results ) && ! empty( $term_results ) ) {
			$index = $meta['sliceStart'];
			foreach ( $term_results as $term ) {
				$terms_array[ $index ] = $term;
				$index ++;
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
	 * This maps the GraphQL "friendly" args to get_terms $args.
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be down
	 * to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array       $args     Array of query "where" args
	 * @param string      $taxonomy The name of the taxonomy
	 * @param mixed       $source   The query results
	 * @param array       $all_args All of the query arguments (not just the "where" args)
	 * @param AppContext  $context  The AppContext object
	 * @param ResolveInfo $info     The ResolveInfo object
	 *
	 * @since  0.0.5
	 * @return array
	 * @access public
	 */
	public static function map_input_fields_to_get_terms( $args, $taxonomy, $source, $all_args, $context, $info ) {

		/**
		 * Start a fresh array
		 */
		$query_args = [];

		if ( ! empty( $args['taxonomy'] ) ) {
			$query_args['taxonomy'] = $args['taxonomy'];
		}

		if ( ! empty( $args['objectIds'] ) ) {
			$query_args['object_ids'] = $args['objectIds'];
		}

		if ( ! empty( $args['orderby'] ) ) {
			$query_args['orderby'] = $args['orderby'];
		}

		if ( ! empty( $args['hideEmpty'] ) ) {
			$query_args['hide_empty'] = $args['hideEmpty'];
		}

		if ( ! empty( $args['include'] ) ) {
			$query_args['include'] = $args['include'];
		}

		if ( ! empty( $args['exclude'] ) ) {
			$query_args['exclude'] = $args['exclude'];
		}

		if ( ! empty( $args['excludeTree'] ) ) {
			$query_args['exclude_tree'] = $args['excludeTree'];
		}

		if ( ! empty( $args['name'] ) ) {
			$query_args['name'] = $args['name'];
		}

		if ( ! empty( $args['slug'] ) ) {
			$query_args['slug'] = $args['slug'];
		}

		if ( ! empty( $args['termTaxonomId'] ) ) {
			$query_args['term_taxonomy_id'] = $args['termTaxonomId'];
		}

		if ( ! empty( $args['hierarchical'] ) ) {
			$query_args['hierarchical'] = $args['hierarchical'];
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = $args['search'];
		}

		if ( ! empty( $args['nameLike'] ) ) {
			$query_args['name__like'] = $args['nameLike'];
		}

		if ( ! empty( $args['descriptionLike'] ) ) {
			$query_args['description__like'] = $args['descriptionLike'];
		}

		if ( ! empty( $args['padCounts'] ) ) {
			$query_args['pad_counts'] = $args['padCounts'];
		}

		if ( ! empty( $args['childOf'] ) ) {
			$query_args['child_of'] = $args['childOf'];
		}

		if ( ! empty( $args['parent'] ) ) {
			$query_args['parent'] = $args['parent'];
		}

		if ( ! empty( $args['childless'] ) ) {
			$query_args['childless'] = $args['childless'];
		}

		if ( ! empty( $args['cacheDomain'] ) ) {
			$query_args['cache_domain'] = $args['cacheDomain'];
		}

		if ( ! empty( $args['updateTermMetaCache'] ) ) {
			$query_args['update_term_meta_cache'] = $args['updateTermMetaCache'];
		}

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @param array       $query_args Array of mapped query args
		 * @param array       $args       Array of query "where" args
		 * @param string      $taxonomy   The name of the taxonomy
		 * @param mixed       $source     The query results
		 * @param array       $all_args   All of the query arguments (not just the "where" args)
		 * @param AppContext  $context    The AppContext object
		 * @param ResolveInfo $info       The ResolveInfo object
		 *
		 * @since 0.0.5
		 * @return array
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_get_terms', $query_args, $args, $taxonomy, $source, $all_args, $context, $info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

}
