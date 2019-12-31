<?php

namespace WPGraphQL\Type\Object;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Term;

/**
 * Class TermObject
 *
 * @package WPGraphQL\Type\Object
 */
class TermObject {

	/**
	 * Register the Type for each kind of Taxonomy
	 *
	 * @param $taxonomy_object
	 */
	public static function register_taxonomy_object_type( $taxonomy_object ) {

		$single_name = $taxonomy_object->graphql_single_name;
		register_graphql_object_type(
			$single_name,
			[
				'description' => __( sprintf( 'The %s type', $single_name ), 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'TermNode', 'UniformResourceIdentifiable' ],
				'fields'      => [
					$single_name . 'Id' => [
						'type'              => 'Int',
						'isDeprecated'      => true,
						'deprecationReason' => __( 'Deprecated in favor of databaseId', 'wp-graphql' ),
						'description'       => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve'           => function( Term $term, $args, $context, $info ) {
							return absint( $term->term_id );
						},
					],
					'uri'               => [
						'resolve' => function( $term, $args, $context, $info ) {
							return ! empty( $term->link ) ? ltrim( str_ireplace( home_url(), '', $term->link ), '/' ) : '';
						},
					],
				],
			]
		);

		if ( true === $taxonomy_object->hierarchical ) {
			register_graphql_field(
				$taxonomy_object->graphql_single_name,
				'parent',
				[
					'type'        => $taxonomy_object->graphql_single_name,
					'description' => __( 'The parent object', 'wp-graphql' ),
					'resolve'     => function( Term $term, $args, $context, $info ) {
						return isset( $term->parentId ) ? DataSource::resolve_term_object( $term->parentId, $context ) : null;
					},
				]
			);

			register_graphql_field(
				$taxonomy_object->graphql_single_name,
				'ancestors',
				[
					'type'        => [
						'list_of' => $taxonomy_object->graphql_single_name,
					],
					'description' => esc_html__( 'The ancestors of the object', 'wp-graphql' ),
					'resolve'     => function( Term $term, $args, $context, $info ) {
						$ancestors = [];

						$ancestor_ids = get_ancestors( absint( $term->term_id ), $term->taxonomyName, 'taxonomy' );
						if ( ! empty( $ancestor_ids ) ) {
							foreach ( $ancestor_ids as $ancestor_id ) {
								$ancestors[] = DataSource::resolve_term_object( $ancestor_id, $context );
							}
						}

						return ! empty( $ancestors ) ? $ancestors : null;
					},
				]
			);

		}

	}

}
