<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

/**
 * Class TermObjectConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<\WP_Term_Query>
 */
class TermObjectConnectionResolver extends AbstractConnectionResolver {
	/**
	 * The name of the Taxonomy the resolver is intended to be used for
	 *
	 * @var array<string>|string
	 */
	protected $taxonomy;

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed|array<string>|string|null $taxonomy The name of the Taxonomy the resolver is intended to be used for.
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info, $taxonomy = null ) {
		$this->taxonomy = $taxonomy;
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_query_args( array $args ): array {
		$all_taxonomies = \WPGraphQL::get_allowed_taxonomies();

		if ( ! is_array( $this->taxonomy ) ) {
			$taxonomy = ! empty( $this->taxonomy ) && in_array( $this->taxonomy, $all_taxonomies, true ) ? [ $this->taxonomy ] : $all_taxonomies;
		} else {
			$taxonomy = $this->taxonomy;
		}

		if ( ! empty( $args['where']['taxonomies'] ) ) {
			/**
			 * Set the taxonomy for the $args
			 */
			$requested_taxonomies = $args['where']['taxonomies'];
			$taxonomy             = array_intersect( $all_taxonomies, $requested_taxonomies );
		}

		$query_args = [
			'taxonomy' => $taxonomy,
		];

		/**
		 * Prepare for later use
		 */
		$last = ! empty( $args['last'] ) ? $args['last'] : null;

		/**
		 * Set hide_empty as false by default
		 */
		$query_args['hide_empty'] = false;

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$query_args['number'] = $this->get_query_amount() + 1;

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
		if ( ! empty( $args['where'] ) ) {
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
		$query_args['graphql_args'] = $args;

		/**
		 * NOTE: We query for JUST the IDs here as deferred resolution of the nodes gets the full
		 * object from the cache or a follow-up request for the full object if it's not cached.
		 */
		$query_args['fields'] = 'ids';

		/**
		 * If there's no orderby params in the inputArgs, default to ordering by name.
		 */
		if ( empty( $query_args['orderby'] ) ) {
			$query_args['orderby'] = 'name';
		}

		/**
		 * If orderby params set but not order, default to ASC if going forward, DESC if going backward.
		 */
		if ( empty( $query_args['order'] ) && 'name' === $query_args['orderby'] ) {
			$query_args['order'] = ! empty( $last ) ? 'DESC' : 'ASC';
		} elseif ( empty( $query_args['order'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'ASC' : 'DESC';
		}

		/**
		 * Filters the query args used by the connection.
		 *
		 * @param array<string,mixed>                  $query_args array of query_args being passed to the
		 * @param mixed                                $source     source passed down from the resolve tree
		 * @param array<string,mixed>                  $args       array of arguments input in the field as part of the GraphQL query
		 * @param \WPGraphQL\AppContext                $context    object passed down the resolve tree
		 * @param \GraphQL\Type\Definition\ResolveInfo $info       info about fields passed down the resolve tree
		 *
		 * @since 0.0.6
		 */
		$query_args = apply_filters( 'graphql_term_object_connection_query_args', $query_args, $this->source, $args, $this->context, $this->info );

		return $query_args;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function query_class(): string {
		return \WP_Term_Query::class;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		/**
		 * @todo This is for b/c. We can just use $this->get_query().
		 */
		$queried = isset( $this->query ) ? $this->query : $this->get_query();

		/** @var string[] $ids */
		$ids = ! empty( $queried->get_terms() ) ? $queried->get_terms() : [];

		// If we're going backwards, we need to reverse the array.
		$args = $this->get_args();
		if ( ! empty( $args['last'] ) ) {
			$ids = array_reverse( $ids );
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'term';
	}

	/**
	 * This maps the GraphQL "friendly" args to get_terms $args.
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be down
	 * to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @since  0.0.5
	 * @return array<string,mixed>
	 */
	public function sanitize_input_fields() {
		$arg_mapping = [
			'objectIds'           => 'object_ids',
			'hideEmpty'           => 'hide_empty',
			'excludeTree'         => 'exclude_tree',
			'termTaxonomId'       => 'term_taxonomy_id',
			'termTaxonomyId'      => 'term_taxonomy_id',
			'nameLike'            => 'name__like',
			'descriptionLike'     => 'description__like',
			'padCounts'           => 'pad_counts',
			'childOf'             => 'child_of',
			'cacheDomain'         => 'cache_domain',
			'updateTermMetaCache' => 'update_term_meta_cache',
			'taxonomies'          => 'taxonomy',
		];

		$args       = $this->get_args();
		$where_args = ! empty( $args['where'] ) ? $args['where'] : null;

		// Deprecate usage of 'termTaxonomId'.
		if ( ! empty( $where_args['termTaxonomId'] ) ) {
			_deprecated_argument( 'where.termTaxonomId', '1.11.0', 'The `termTaxonomId` where arg is deprecated. Use `termTaxonomyId` instead.' );

			// Only convert value if 'termTaxonomyId' isnt already set.
			if ( empty( $where_args['termTaxonomyId'] ) ) {
				$where_args['termTaxonomyId'] = $where_args['termTaxonomId'];
			}
		}

		/**
		 * Map and sanitize the input args to the WP_Term_Query compatible args
		 */
		$query_args = Utils::map_input( $where_args, $arg_mapping );

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @param array<string,mixed>                  $query_args Array of mapped query args
		 * @param array<string,mixed>                  $where_args Array of query "where" args
		 * @param array<string>|string                 $taxonomy   The name of the taxonomy
		 * @param mixed                                $source     The query results
		 * @param array<string,mixed>                  $all_args   All of the query arguments (not just the "where" args)
		 * @param \WPGraphQL\AppContext                $context   The AppContext object
		 * @param \GraphQL\Type\Definition\ResolveInfo $info      The ResolveInfo object
		 *
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_get_terms', $query_args, $where_args, $this->taxonomy, $this->source, $args, $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_args( array $args ): array {
		if ( ! empty( $args['where'] ) ) {
			// Ensure all IDs are converted to database IDs.
			foreach ( $args['where'] as $input_key => $input_value ) {
				if ( empty( $input_value ) ) {
					continue;
				}

				switch ( $input_key ) {
					case 'exclude':
					case 'excludeTree':
					case 'include':
					case 'objectIds':
					case 'termTaxonomId':
					case 'termTaxonomyId':
						if ( is_array( $input_value ) ) {
							$args['where'][ $input_key ] = array_map(
								static function ( $id ) {
									return Utils::get_database_id_from_id( $id );
								},
								$input_value
							);
							break;
						}

						$args['where'][ $input_key ] = Utils::get_database_id_from_id( $input_value );
						break;
				}
			}
		}

		/**
		 * Filters the GraphQL args before they are used in get_query_args().
		 *
		 * @param array<string,mixed> $args            The GraphQL args passed to the resolver.
		 * @param self                $resolver        Instance of the ConnectionResolver.
		 * @param array<string,mixed> $unfiltered_args Array of arguments input in the field as part of the GraphQL query.
		 *
		 * @since 1.11.0
		 */
		return apply_filters( 'graphql_term_object_connection_args', $args, $this, $this->get_unfiltered_args() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $offset The ID of the node used in the cursor for offset.
	 */
	public function is_valid_offset( $offset ) {
		return get_term( absint( $offset ) ) instanceof \WP_Term;
	}
}
