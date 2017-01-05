<?php
namespace DFM\WPGraphQL\Setup;

class TermEntities {

	public $allowed_taxonomies = [];

	public function __construct() {
		// placeholder
	}

	public function init() {

		add_action( 'wpgraphql_root_queries', [ $this, 'setup_root_queries' ], 10, 1 );

	}

	public function show_taxonomies_in_graphql() {

		global $wp_taxonomies;

		if ( isset( $wp_taxonomies['category'] ) ) {
			$wp_taxonomies['category']->show_in_graphql = true;
			$wp_taxonomies['category']->graphql_query_class = '\DFM\WPGraphQL\Types\TermObject\TermObjectQueryType';
		}

		if ( isset( $wp_taxonomies['post_tag'] ) ) {
			$wp_taxonomies['post_tag']->show_in_graphql = true;
			$wp_taxonomies['post_tag']->graphql_query_class = '\DFM\WPGraphQL\Types\TermObject\TermObjectQueryType';
		}

	}

	public function setup_root_queries( $fields ) {

		$this->show_taxonomies_in_graphql();

		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );

		$this->allowed_taxonomies = apply_filters( 'wpgraphql_term_queries_allowed_taxonomies', $taxonomies );

		if ( ! empty( $this->allowed_taxonomies ) && is_array( $this->allowed_taxonomies ) ) {

			foreach ( $this->allowed_taxonomies as $allowed_taxonomy ) {

				$taxonomy_query_class = get_taxonomy( $allowed_taxonomy )->graphql_query_class;

				$class = ( ! empty( $taxonomy_query_class ) && class_exists( $taxonomy_query_class ) ) ? $taxonomy_query_class : '\DFM\WPGraphQL\Types\TermObject\TermObjectQueryType';

				$fields[] = new $class( [ 'taxonomy' => $allowed_taxonomy ] );
			}

		}

		return $fields;

	}

}
