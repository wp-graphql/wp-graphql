<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Types;

/**
 * Class TermObjectConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class TermObjectConnectionResolver extends AbstractConnectionResolver {

	/**
	 * The name of the Taxonomy the resolver is intended to be used for
	 *
	 * @var
	 */
	protected $taxonomy;

	/**
	 * TermObjectConnectionResolver constructor.
	 *
	 * @param $source
	 * @param $args
	 * @param $context
	 * @param $info
	 * @param $taxonomy
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, $args, $context, $info, $taxonomy ) {
		$this->taxonomy = $taxonomy;
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function get_query_args() {

		/**
		 * Set the taxonomy for the $args
		 */
		$query_args['taxonomy'] = ! empty( $this->taxonomy ) ? $this->taxonomy : 'category';

		/**
		 * Prepare for later use
		 */
		$last  = ! empty( $this->args['last'] ) ? $this->args['last'] : null;
		$first = ! empty( $this->args['first'] ) ? $this->args['first'] : null;

		/**
		 * Set the default parent for TermObject Queries to be "0" to only get top level terms, unless
		 * includeChildren is set
		 */
		$query_args['parent'] = 0;

		/**
		 * Set hide_empty as false by default
		 */
		$query_args['hide_empty'] = false;

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$query_args['number'] = min( max( absint( $first ), absint( $last ), 10 ), $this->get_query_amount() ) + 1;

		/**
		 * Orderby Name by default
		 */
		$query_args['orderby'] = 'name';

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

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( $query_args['order'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'DESC' : 'ASC';
		}

		/**
		 * Set the graphql_cursor_offset
		 */
		$query_args['graphql_cursor_offset']  = $this->get_offset();
		$query_args['graphql_cursor_compare'] = ( ! empty( $last ) ) ? '>' : '<';

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $this->args;

		/**
		 * If the source of the Query is a Post object, adjust the query args to only query terms
		 * connected to the post object
		 *
		 * @since 0.0.5
		 */
		global $post;
		if ( true === is_object( $this->source ) ) {
			switch ( true ) {
				case $this->source instanceof Post:
					$post                                  = $this->source;
					$post->shouldOnlyIncludeConnectedItems = isset( $input_fields['shouldOnlyIncludeConnectedItems'] ) ? $input_fields['shouldOnlyIncludeConnectedItems'] : true;
					$query_args['object_ids']              = $this->source->ID;
					break;
				case $this->source instanceof Term:
					if ( is_a( $GLOBALS['post'], 'WP_Post' ) && isset( $GLOBALS['post']->ID ) ) {
						$query_args['object_ids'] = $GLOBALS['post']->ID;
					}

					$query_args['parent'] = ! empty( $this->source->term_id ) ? $this->source->term_id : 0;
					break;
				default:
					break;
			}
		}

		/**
		 * IF the connection is set to NOT ONLY include connected items (default behavior), unset the $object_ids arg
		 */
		if ( isset( $post->shouldOnlyIncludeConnectedItems ) && false === $post->shouldOnlyIncludeConnectedItems ) {
			unset( $query_args['object_ids'] );
		}

		/**
		 * If the connection is set to output in a flat list, unset the parent
		 */
		if ( isset( $input_fields['shouldOutputInFlatList'] ) && true === $input_fields['shouldOutputInFlatList'] ) {
			unset( $query_args['parent'] );
			if ( $this->source instanceof Post ) {
				$connected             = wp_get_object_terms( $this->source->ID, $this->taxonomy, [ 'fields' => 'ids' ] );
				$query_args['include'] = ! empty( $connected ) ? $connected : [];
			}
		}

		/**
		 * If the query is a search, the source isn't another Term, and the parent $arg is not explicitly set in the query,
		 * unset the $query_args['parent'] so the search can search all posts, not just top level posts.
		 */
		if ( ! $this->source instanceof Term && isset( $query_args['search'] ) && ! isset( $input_fields['parent'] ) ) {
			unset( $query_args['parent'] );
		}

		/**
		 * NOTE: We query for JUST the IDs here as deferred resolution of the nodes gets the full
		 * object from the cache or a follow-up request for the full object if it's not cached.
		 */
		$query_args['fields'] = 'ids';

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
	 * @return mixed|\WP_Term_Query
	 * @throws \Exception
	 */
	public function get_query() {
		$query = new \WP_Term_Query( $this->query_args );

		return $query;
	}

	/**
	 * This gets the items from the query. Different queries return items in different ways, so this
	 * helps normalize the items into an array for use by the get_nodes() function.
	 *
	 * @return array
	 */
	public function get_items() {
		return ! empty( $this->query->get_terms() ) ? $this->query->get_terms() : [];
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
	 * @access public
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
	 * @access public
	 *
	 * @param int $offset The ID of the node used in the cursor for offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return ! empty( get_term( absint( $offset ) ) );
	}

}
