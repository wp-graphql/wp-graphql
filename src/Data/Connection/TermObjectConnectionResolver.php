<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Types;

/**
 * Class TermObjectConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class TermObjectConnectionResolver extends AbstractConnectionResolver {

	/**
	 * {@inheritDoc}
	 *
	 * @var \WP_Term_Query
	 */
	protected $query;

	/**
	 * The name of the Taxonomy the resolver is intended to be used for
	 *
	 * @var string
	 */
	protected $taxonomy;

	/**
	 * TermObjectConnectionResolver constructor.
	 *
	 * @param mixed       $source     source passed down from the resolve tree
	 * @param array       $args       array of arguments input in the field as part of the GraphQL query
	 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info       Info about fields passed down the resolve tree
	 * @param mixed|string|null $taxonomy The name of the Taxonomy the resolver is intended to be used for
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info, $taxonomy = null ) {
		$this->taxonomy = $taxonomy;
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_query_args() {

		/**
		 * Set the taxonomy for the $args
		 */
		$all_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		$query_args     = [
			'taxonomy' => ! empty( $this->taxonomy ) ? $this->taxonomy : $all_taxonomies,
		];

		/**
		 * Prepare for later use
		 */
		$last  = ! empty( $this->args['last'] ) ? $this->args['last'] : null;
		$first = ! empty( $this->args['first'] ) ? $this->args['first'] : null;

		/**
		 * Set hide_empty as false by default
		 */
		$query_args['hide_empty'] = false;

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$query_args['number'] = min( max( absint( $first ), absint( $last ), 10 ), $this->query_amount ) + 1;

		/**
		 * Orderby Name by default
		 */
		$query_args['orderby'] = 'name';
		$query_args['order']   = 'ASC';

		/**
		 * Don't calculate the total rows, it's not needed and can be expensive
		 */
		$query_args['count'] = false;

		/**
		 * Take any of the $args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Term_Query names to be used in the WP_Term_Query
		 *
		 * @since 0.0.5
		 */
		$input_fields = [];
		if ( ! empty( $this->args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields();
		}

		/**
		 * Merge the default $query_args with the $args that were entered
		 * in the query.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $input_fields ) ) {
			$query_args = array_merge( $query_args, $input_fields );
		}

		$query_args['graphql_cursor_compare'] = ( ! empty( $last ) ) ? '>' : '<';
		$query_args['graphql_after_cursor']   = $this->get_after_offset();
		$query_args['graphql_before_cursor']  = $this->get_before_offset();

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $this->args;

		/**
		 * NOTE: We query for JUST the IDs here as deferred resolution of the nodes gets the full
		 * object from the cache or a follow-up request for the full object if it's not cached.
		 */
		$query_args['fields'] = 'ids';

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( ! empty( $query_args['order'] ) ) {

			if ( ! empty( $last ) ) {
				if ( 'ASC' === $query_args['order'] ) {
					$query_args['order'] = 'DESC';
				} else {
					$query_args['order'] = 'ASC';
				}
			}
		}

		/**
		 * Filter the query_args that should be applied to the query. This filter is applied AFTER the input args from
		 * the GraphQL Query have been applied and has the potential to override the GraphQL Query Input Args.
		 *
		 * @param array       $query_args array of query_args being passed to the
		 * @param mixed       $source     source passed down from the resolve tree
		 * @param array       $args       array of arguments input in the field as part of the GraphQL query
		 * @param AppContext  $context    object passed down the resolve tree
		 * @param ResolveInfo $info       info about fields passed down the resolve tree
		 *
		 * @since 0.0.6
		 */
		$query_args = apply_filters( 'graphql_term_object_connection_query_args', $query_args, $this->source, $this->args, $this->context, $this->info );

		return $query_args;
	}

	/**
	 * Return an instance of WP_Term_Query with the args mapped to the query
	 *
	 * @return \WP_Term_Query
	 * @throws Exception
	 */
	public function get_query() {
		return new \WP_Term_Query( $this->query_args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		/** @var string[] $ids **/
		$ids = ! empty( $this->query->get_terms() ) ? $this->query->get_terms() : [];

		// If we're going backwards, we need to reverse the array.
		if ( ! empty( $this->args['last'] ) ) {
			$ids = array_reverse( $ids );
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_loader_name() {
		return 'term';
	}

	/**
	 * Whether the connection query should execute. Certain contexts _may_ warrant
	 * restricting the query to execute at all. Default is true, meaning any time
	 * a TermObjectConnection resolver is asked for, it will execute.
	 *
	 * @return bool
	 */
	public function should_execute() {
		return true;
	}

	/**
	 * This maps the GraphQL "friendly" args to get_terms $args.
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be down
	 * to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @since  0.0.5
	 * @return array
	 */
	public function sanitize_input_fields() {

		$arg_mapping = [
			'objectIds'           => 'object_ids',
			'hideEmpty'           => 'hide_empty',
			'excludeTree'         => 'exclude_tree',
			'termTaxonomId'       => 'term_taxonomy_id',
			'nameLike'            => 'name__like',
			'descriptionLike'     => 'description__like',
			'padCounts'           => 'pad_counts',
			'childOf'             => 'child_of',
			'cacheDomain'         => 'cache_domain',
			'updateTermMetaCache' => 'update_term_meta_cache',
			'taxonomies'          => 'taxonomy',
		];

		$where_args = ! empty( $this->args['where'] ) ? $this->args['where'] : null;

		/**
		 * Map and sanitize the input args to the WP_Term_Query compatible args
		 */
		$query_args = Types::map_input( $where_args, $arg_mapping );

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @param array       $query_args Array of mapped query args
		 * @param array       $where_args Array of query "where" args
		 * @param string      $taxonomy   The name of the taxonomy
		 * @param mixed       $source     The query results
		 * @param array       $all_args   All of the query arguments (not just the "where" args)
		 * @param AppContext  $context    The AppContext object
		 * @param ResolveInfo $info       The ResolveInfo object
		 *
		 * @since 0.0.5
		 * @return array
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_get_terms', $query_args, $where_args, $this->taxonomy, $this->source, $this->args, $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

	/**
	 * Determine whether or not the the offset is valid, i.e the term corresponding to the offset
	 * exists. Offset is equivalent to term_id. So this function is equivalent to checking if the
	 * term with the given ID exists.
	 *
	 * @param int $offset The ID of the node used in the cursor for offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return get_term( absint( $offset ) ) instanceof \WP_Term;
	}

}
