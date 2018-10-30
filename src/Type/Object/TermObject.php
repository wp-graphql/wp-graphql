<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

function register_taxonomy_object_type( $taxonomy_object ) {

	$single_name = $taxonomy_object->graphql_single_name;
	register_graphql_object_type( $single_name, [
		'description' => __( sprintf( 'The %s type', $single_name ), 'wp-graphql' ),
		'interfaces'  => [ WPObjectType::node_interface() ],
		'fields'      => [
			'id'                => [
				'type'        => [
					'non_null' => 'ID',
				],
				# Placeholder is the name of the taxonomy
				'description' => __( 'The global ID for the ' . $taxonomy_object->name, 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, $args, $context, $info ) {
					return ( ! empty( $term->taxonomy ) && ! empty( $term->term_id ) ) ? Relay::toGlobalId( $term->taxonomy, $term->term_id ) : null;
				},
			],
			$single_name . 'Id' => [
				'type'        => 'Int',
				'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, $args, $context, $info ) {
					return ! empty( $term->term_id ) ? absint( $term->term_id ) : null;
				},
			],
			'count'             => [
				'type'        => 'Int',
				'description' => __( 'The number of objects connected to the object', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					return ! empty( $term->count ) ? absint( $term->count ) : null;
				},
			],
			'description'       => [
				'type'        => 'String',
				'description' => __( 'The description of the object', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					return ! empty( $term->description ) ? $term->description : null;
				},
			],
			'name'              => [
				'type'        => 'String',
				'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					return ! empty( $term->name ) ? $term->name : null;
				},
			],
			'slug'              => [
				'type'        => 'String',
				'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					return ! empty( $term->slug ) ? $term->slug : null;
				},
			],
			'termGroupId'       => [
				'type'        => 'Int',
				'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					return ! empty( $term->term_group ) ? absint( $term->term_group ) : null;
				},
			],
			'termTaxonomyId'    => [
				'type'        => 'Int',
				'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					return ! empty( $term->term_taxonomy_id ) ? absint( $term->term_taxonomy_id ) : null;
				},
			],
			'taxonomy'          => [
				'type'        => 'Taxonomy',
				'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					$taxonomy = get_taxonomy( $term->taxonomy );

					return ! empty( $term->taxonomy ) && false !== $taxonomy ? $taxonomy : null;
				},
			],
			'link'              => [
				'type'        => 'String',
				'description' => __( 'The link to the term', 'wp-graphql' ),
				'resolve'     => function( \WP_Term $term, $args, $context, $info ) {
					$link = get_term_link( $term->term_id );

					return ( ! is_wp_error( $link ) ) ? $link : null;
				},
			],
		],
	] );

	if ( true === $taxonomy_object->hierarchical ) {
		register_graphql_field( $taxonomy_object->graphql_single_name, 'parent', [
			'type'        => $taxonomy_object->graphql_single_name,
			'description' => __( 'The parent object', 'wp-graphql' ),
			'resolve'     => function( \WP_Term $term, $args, $context, $info ) {
				return ! empty( $term->parent ) ? DataSource::resolve_term_object( $term->parent, $term->taxonomy ) : null;
			},
		] );

		register_graphql_field( $taxonomy_object->graphql_single_name, 'ancestors', [
			'type'        => [
				'list_of' => $taxonomy_object->graphql_single_name,
			],
			'description' => esc_html__( 'The ancestors of the object', 'wp-graphql' ),
			'resolve'     => function( \WP_Term $term, $args, $context, $info ) {
				$ancestors    = [];
				$ancestor_ids = get_ancestors( $term->term_id, $term->taxonomy );
				if ( ! empty( $ancestor_ids ) ) {
					foreach ( $ancestor_ids as $ancestor_id ) {
						$ancestors[] = get_term( $ancestor_id );
					}
				}

				return ! empty( $ancestors ) ? $ancestors : null;
			},
		] );

		// @todo
		// $fields['children'] = TermObjectConnectionDefinition::connection( $taxonomy_object, 'Children' );
	}

}
