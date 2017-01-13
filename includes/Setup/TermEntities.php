<?php
namespace WPGraphQL\Setup;
use WPGraphQL\Types\TermObject\TermObjectType;
use WPGraphQL\Utils\Fields;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\IntType;

/**
 * Class TermEntities
 *
 * This setus up the PostType entities to be exposed to the RootQuery
 *
 * @package WPGraphQL\Setup
 * @since 0.0.2
 */
class TermEntities {

	/**
	 * allowed_taxonomies
	 *
	 * Holds an array of the taxonomies allowed to be exposed in the GraphQL Queries
	 *
	 * @var array
	 * @since 0.0.2
	 */
	public $allowed_taxonomies = [];

	/**
	 * allowed_taxonomy
	 *
	 * Holds the value of the allowed taxonomy for use in exposing GraphQL fields
	 *
	 * @var
	 */
	public $allowed_taxonomy;

	/**
	 * TermEntities constructor.
	 *
	 * Placeholder
	 *
	 * @since 0.0.2
	 */
	public function __construct() {
		// Placeholder
	}

	/**
	 * init
	 *
	 * Setup the root queries for each of the $allowed_taxonomies
	 */
	public function init() {

		/**
		 * Define what taxonomies should be part of the GraphQL Schema
		 */
		add_action( 'graphql_init', [ $this, 'show_in_graphql' ], 5 );

		/**
		 * Setup the root queries for terms
		 */
		add_action( 'graphql_root_queries', [ $this, 'setup_term_queries' ], 5, 1 );

		/**
		 * Add dynamic fields to terms based on their support for that feature
		 * @since 0.0.2
		 */
		add_action( 'graphql_after_setup_term_object_queries', [ $this, 'dynamic_fields' ], 5 );

	}

	/**
	 * show_in_graphql
	 *
	 * Modify the global $wp_taxonomies, adding the property "show_in_graphql"
	 * to category and post_tag, and providing additional graphql properties
	 *
	 * @since 0.0.2
	 */
	public function show_in_graphql() {

		global $wp_taxonomies;

		if ( isset( $wp_taxonomies['category'] ) ) {
			$wp_taxonomies['category']->show_in_graphql     = true;
			$wp_taxonomies['category']->graphql_name        = 'Category';
			$wp_taxonomies['category']->graphql_plural_name = 'Categories';
		}

		if ( isset( $wp_taxonomies['post_tag'] ) ) {
			$wp_taxonomies['post_tag']->show_in_graphql     = true;
			$wp_taxonomies['post_tag']->graphql_name        = 'PostTag';
			$wp_taxonomies['post_tag']->graphql_plural_name = 'PostTags';
		}

	}

	/**
	 * get_allowed_taxonomies
	 *
	 * Get the taxonomies that are allowed to be used in GraphQL/
	 * This gets all taxonomies that are set to "show_in_graphql" but allows
	 * for external code (plugins/themes) to filter the list of allowed_taxonomies
	 * to add/remove additional taxonomies
	 *
	 * @since 0.0.2
	 */
	public function get_allowed_taxonomies() {

		/**
		 * Get all taxonomies that have been registered to "show_in_graphql"
		 */
		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );

		/**
		 * Define the $allowed_taxonomies to be exposed by GraphQL Queries
		 * Pass through a filter to allow the taxonomies to be modified (for example if
		 * a certain taxonomy should not be exposed to the GraphQL API)
		 *
		 * @since 0.0.2
		 */
		$this->allowed_taxonomies = apply_filters( 'graphql_term_entities_allowed_taxonomies', $taxonomies );

		/**
		 * Returns the array of $allowed_taxonomies
		 */
		return $this->allowed_taxonomies;

	}

	/**
	 * setup_term_queries
	 *
	 * This sets up the term_queries for all $allowed_taxonomies
	 *
	 * @param $fields
	 *
	 * @return array
	 * @since 0.0.2
	 */
	public function setup_term_queries( $fields ) {

		/**
		 * Instantiate the Utils/Fields class
		 */
		$field_utils = new Fields();

		/**
		 * Get the allowed taxonomies that should be part of GraphQL
		 */
		$this->allowed_taxonomies = $this->get_allowed_taxonomies();

		/**
		 * If there's a populated array of taxonomies, setup the proper queries
		 */
		if ( ! empty( $this->allowed_taxonomies ) && is_array( $this->allowed_taxonomies ) ) {

			/**
			 * Loop through each of the allowed_taxonomies
			 */
			foreach ( $this->allowed_taxonomies as $allowed_taxonomy ) {

				/**
				 * Set the value of the allowed_taxonomy
				 */
				$this->allowed_taxonomy = $allowed_taxonomy;

				/**
				 * Get the query class from the allowed_taxonomy object
				 */
				$taxonomy_query_class = get_taxonomy( $allowed_taxonomy )->graphql_query_class;

				/**
				 * If the taxonomy has a "graphql_query_class" defined, use it
				 * otherwise fall back to the standard TermObjectQuery class
				 *
				 * This allows for Plugins/Themes to use a completely different class for their
				 * taxonomy terms instead of inheriting/filtering the TermObjectType
				 *
				 * @since 0.0.2
				 */
				$class = ( ! empty( $taxonomy_query_class ) && class_exists( $taxonomy_query_class ) ) ? $taxonomy_query_class : '\WPGraphQL\Types\TermObject\TermObjectQueryType';

				/**
				 * Configure the field names to pass to the fields
				 */
				$allowed_taxonomy_object = get_taxonomy( $allowed_taxonomy );
				$plural_query_name       = ! empty( $allowed_taxonomy_object->graphql_plural_name ) ? $allowed_taxonomy_object->graphql_plural_name : $this->taxonomy;
				$single_query_name       = ! empty( $allowed_taxonomy_object->graphql_name ) ? $allowed_taxonomy_object->graphql_name : $this->taxonomy . 'Items';

				/**
				 * Make sure the name of the queries are formatted to play nice with GraphQL
				 *
				 * @since 0.0.2
				 */
				$plural_query_name = $field_utils->format_field_name( $plural_query_name );
				$single_query_name = $field_utils->format_field_name( $single_query_name );

				/**
				 * Adds a field to get a single TermObjectType be ID
				 *
				 * ex: PostTag(id: Int!): PostTag
				 *
				 * @since 0.0.2
				 */
				$fields[ $single_query_name ] = [
					'name'    => $single_query_name,
					'type'    => new TermObjectType( [
						'taxonomy'   => $allowed_taxonomy,
						'query_name' => $single_query_name
					] ),
					'args'    => [
						'id' => new NonNullType( new IntType() ),
					],
					'resolve' => function( $value, array $args, ResolveInfo $info ) {
						$term_object = get_term( $args->ID, $this->allowed_taxonomy );

						return ! empty( $term_object ) ? $term_object : null;
					}
				];

				/**
				 * Adds a field to query a lost of Terms with additional
				 * query information returned (for pagination, etc)
				 *
				 * @since 0.0.2
				 */
				$fields[ $plural_query_name ] = new $class([
					'name'            => $plural_query_name,
					'taxonomy_object' => $allowed_taxonomy_object,
					'query_name'      => $plural_query_name,
				]);

				/**
				 * Run an action after each allowed_taxonomy has been added to the root_query
				 * @since 0.0.2
				 */
				do_action( 'graphql_after_setup_post_type_query_' . $allowed_taxonomy, $allowed_taxonomy, $allowed_taxonomy_object, $this->get_allowed_taxonomies() );

			}

		}

		do_action( 'graphql_after_setup_term_object_queries', $this->get_allowed_taxonomies() );

		/**
		 * Return the fields
		 */
		return $fields;

	}

	/**
	 * dynamic_fields
	 *
	 * This adds dynamic fields to TermObjectType based on various WordPress configuration settings.
	 * For example, this adds the "parent" field to taxonomies that are hierarchical
	 *
	 * @param $fields
	 * @return mixed
	 * @since 0.0.2
	 */
	public function dynamic_fields() {

		/**
		 * Get a list of allowed taxonomies
		 */
		$allowed_taxonomies = $this->get_allowed_taxonomies();

		// If there are allowed_post_types
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {

			// Loop through the $allowed_post_types
			foreach ( $allowed_taxonomies as $allowed_taxonomy ) {

				/**
				 * Set the value of the allowed_taxonomy
				 */
				$this->allowed_taxonomy = $allowed_taxonomy;

				// If the taxonomy is hierarchical
				if ( true === is_taxonomy_hierarchical( $this->allowed_taxonomy ) ) {

					/**
					 * Add the parent field to hierarchical taxonomy terms
					 * @since 0.0.2
					 */
					add_filter( 'graphql_term_object_type_fields_' . $this->allowed_taxonomy, function( $fields ) {

						/**
						 * Add the parent field to hierarchical terms
						 */
						$fields['parent'] = [
							'name'        => 'parent',
							'type'        => new TermObjectType( [
								'taxonomy'   => $this->allowed_taxonomy,
								'query_name' => 'ParentTerm'
							] ),
							'description' => __( 'The parent term of the object', 'wp-graphql' ),
							'resolve'     => function( $value, array $args, ResolveInfo $info ) {
								$term_parent = ( false !== $value->parent ) ? get_term( $value->parent, $this->allowed_taxonomy ) : null;

								return ! empty( $term_parent ) ? $term_parent : null;
							}
						];

						return $fields;

					}, 10, 1 );

				}

			}

		}

	}

}
