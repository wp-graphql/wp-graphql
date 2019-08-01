<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Term;

function register_taxonomy_object_type( $taxonomy_object ) {

	$single_name = $taxonomy_object->graphql_single_name;
	register_graphql_object_type(
		$single_name,
		[
			'description' => __( sprintf( 'The %s type', $single_name ), 'wp-graphql' ),
			'interfaces'  => [ WPObjectType::node_interface() ],
			'fields'      => [
				'id'                => [
					'type'        => [
						'non_null' => 'ID',
					],
					// Placeholder is the name of the taxonomy
					'description' => __( 'The global ID for the ' . $taxonomy_object->name, 'wp-graphql' ),
				],
				$single_name . 'Id' => [
					'type'        => 'Int',
					'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
					'resolve'     => function( Term $term, $args, $context, $node ) {
						return absint( $term->term_id );
					},
				],
				'count'             => [
					'type'        => 'Int',
					'description' => __( 'The number of objects connected to the object', 'wp-graphql' ),
				],
				'description'       => [
					'type'        => 'String',
					'description' => __( 'The description of the object', 'wp-graphql' ),
				],
				'name'              => [
					'type'        => 'String',
					'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
				],
				'slug'              => [
					'type'        => 'String',
					'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
				],
				'termGroupId'       => [
					'type'        => 'Int',
					'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
				],
				'termTaxonomyId'    => [
					'type'        => 'Int',
					'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
				],
				'taxonomy'          => [
					'type'        => 'Taxonomy',
					'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
					'resolve'     => function( $source, $args, $context, $info ) {
						return DataSource::resolve_taxonomy( $source->taxonomyName );
					},
				],
				'isRestricted'      => [
					'type'        => 'Boolean',
					'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
				],
				'link'              => [
					'type'        => 'String',
					'description' => __( 'The link to the term', 'wp-graphql' ),
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

		// @todo
		// $fields['children'] = TermObjectConnectionDefinition::connection( $taxonomy_object, 'Children' );
	}

}
