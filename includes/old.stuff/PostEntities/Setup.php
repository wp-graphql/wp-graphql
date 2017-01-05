<?php
namespace DFM\WPGraphQL\Queries\PostEntities;

use DFM\WPGraphQL\Queries\PostEntities\PostObjectQuery;

/**
 * Class Setup
 * @package DFM\WPGraphQL\Queries\PostEntities
 * @since 0.0.2
 */
class Setup {

	/**
	 * allowed_post_types
	 *
	 * Holds an array of the post_types allowed to be exposed in the GraphQL Queries
	 *
	 * @var array
	 * @since 0.0.2
	 */
	public $allowed_post_types = [];

	/**
	 * PostsQueries constructor.
	 *
	 * @since 0.0.2
	 */
	public function __construct() {

		/**
		 * Add core post_types to show in GraohQL
		 */
		$this->show_post_types_in_graphql();

		/**
		 * Get all post_types that have been registered to "show_in_graphql"
		 */
		$post_types = get_post_types( [ 'show_in_graphql' => true ] );

		/**
		 * Define the $allowed_post_types to be exposed by GraphQL Queries
		 * Pass through a filter to allow the post_types to be modified (for example if
		 * a certain post_type should not be exposed to the GraphQL API)
		 */
		$this->allowed_post_types = apply_filters( 'wpgraphql_post_queries_allowed_post_types', $post_types );

	}

	/**
	 * Filter the core post types to "show_in_graphql"
	 *
	 * Additional post_types can be given GraphQL support in the same way, by adding the
	 * "show_in_graphql" and optionally a "graphql_query_class". If no "graphql_query_class" is provided
	 * the default "PostObjectQuery" class will be used which provides the standard fields for all
	 * post objects. 
	 *
	 * @since 0.0.2
	 */
	public function show_post_types_in_graphql(){

		global $wp_post_types;

		if ( isset( $wp_post_types['attachment'] ) ) {
			$wp_post_types['attachment']->show_in_graphql = true;
			$wp_post_types['attachment']->graphql_query_class = '\DFM\WPGraphQL\Entities\Attachments\Query';
			$wp_post_types['attachment']->graphql_mutation_class = '\DFM\WPGraphQL\Entities\Attachments\Mutation';
			$wp_post_types['attachment']->graphql_type_class = '\DFM\WPGraphQL\Entities\Attachments\Type';
		}

		if ( isset( $wp_post_types['page'] ) ) {
			$wp_post_types['page']->show_in_graphql = true;
			$wp_post_types['page']->graphql_query_class = '\DFM\WPGraphQL\Entities\Pages\Query';
			$wp_post_types['page']->graphql_mutation_class = '\DFM\WPGraphQL\Entities\Pages\Mutation';
			$wp_post_types['page']->graphql_type_class = '\DFM\WPGraphQL\Entities\Pages\Type';
		}

		if ( isset( $wp_post_types['post'] ) ) {
			$wp_post_types['post']->show_in_graphql = true;
			$wp_post_types['post']->graphql_query_class = '\DFM\WPGraphQL\Entities\Posts\Query';
			$wp_post_types['post']->graphql_mutation_class = '\DFM\WPGraphQL\Entities\Posts\Mutation';
			$wp_post_types['post']->graphql_type_class = '\DFM\WPGraphQL\Entities\Posts\Type';
		}

	}

	/**
	 * init
	 *
	 * Setup the root queries for each allowed_post_type
	 *
	 * @param $fields
	 * @return array
	 * @since 0.0.2
	 *
	 */
	public function init( $fields ) {

		if ( ! empty( $this->allowed_post_types ) && is_array( $this->allowed_post_types ) ) {

			/**
			 * Loop through each of the allowed_post_types
			 */
			foreach( $this->allowed_post_types as $allowed_post_type ) {

				/**
				 * Get the query class from the post_type_object
				 */
				$post_type_query_class = get_post_type_object( $allowed_post_type )->graphql_query_class;

				/**
				 * If the post_type has a "graphql_query_class" defined, use it
				 * Otherwise fall back to the standard PostObjectQuery class
				 */
				$class = ( ! empty( $post_type_query_class ) && class_exists( $post_type_query_class ) ) ? $post_type_query_class  : '\DFM\WPGraphQL\Entities\PostObject\Query';

				/**
				 * Adds the class to the RootQueryType
				 */
				$fields[] = new $class( $allowed_post_type );

			}

		}

		/**
		 * Returns the fields
		 */
		return $fields;

	}

}