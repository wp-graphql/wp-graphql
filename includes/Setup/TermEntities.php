<?php
namespace DFM\WPGraphQL\Setup;

class TermEntities {

	public $allowed_taxonomies;

	public function __construct() {

	}

	public function show_taxonomies_in_graphql() {

		global $wp_taxonomies;

		if ( isset( $wp_taxonomies['category'] ) ) {
			$wp_taxonomies['category']->show_in_graphql = true;
			$wp_taxonomies['category']->graphql_query_class = '\DFM\WPGraphQL\Entities\TermObject\TermObjectQuery';
		}

		if ( isset( $wp_taxonomies['post_tag'] ) ) {
			$wp_taxonomies['post_tag']->show_in_graphql = true;
			$wp_taxonomies['post_tag']->graphql_query_class = '\DFM\WPGraphQL\Entities\TermObject\TermObjectQuery';
		}

	}

	public function init( $fields ) {

		$this->show_taxonomies_in_graphql();

		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );

		$this->allowed_taxonomies = apply_filters( 'wpgraphql_term_queries_allowed_taxonomies', $taxonomies );

		if ( ! empty( $this->allowed_taxonomies ) && is_array( $this->allowed_taxonomies ) ) {

			foreach ( $this->allowed_taxonomies as $allowed_taxonomy ) {

				$class = ! empty( $allowed_taxonomy->graphql_query_class ) ? $allowed_taxonomy->graphql_query_class : '\DFM\WPGraphQL\Entities\TermObject\TermObjectQuery';

				$fields[] = new $class( $allowed_taxonomy );
			}

		}

		return $fields;

	}

}
