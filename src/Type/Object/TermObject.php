<?php

namespace WPGraphQL\Type\Object;

use WPGraphQL\AppContext;
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

		$interfaces = [ 'Node', 'TermNode', 'UniformResourceIdentifiable' ];

		if ( $taxonomy_object->hierarchical ) {
			$interfaces[] = 'HierarchicalTermNode';
		}

		$single_name = $taxonomy_object->graphql_single_name;
		register_graphql_object_type(
			$single_name,
			[
				'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
				'interfaces'  => $interfaces,
				'fields'      => [
					$single_name . 'Id' => [
						'type'              => 'Int',
						'deprecationReason' => __( 'Deprecated in favor of databaseId', 'wp-graphql' ),
						'description'       => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve'           => function( Term $term, $args, $context, $info ) {
							return absint( $term->term_id );
						},
					],
					'uri'               => [
						'resolve' => function( $term, $args, $context, $info ) {
							return ! empty( $term->link ) ? str_ireplace( home_url(), '', $term->link ) : '';
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
					'resolve'     => function( Term $term, $args, AppContext $context, $info ) {
						return isset( $term->parentDatabaseId ) ? $context->get_loader( 'term' )->load_deferred( $term->parentDatabaseId ) : null;
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
