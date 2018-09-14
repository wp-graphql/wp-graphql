<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Connection\PostObjects;
use WPGraphQL\Connection\TermObjects;
use WPGraphQL\Data\DataSource;

class TermObject {
	public static function register_type( $taxonomy_object ) {
		if ( ! empty( $taxonomy_object->graphql_single_name ) ) {
			register_graphql_object_type( ucfirst( $taxonomy_object->graphql_single_name ), [
				'description' => sprintf( __( 'The % object type', 'wp-graphql' ), $taxonomy_object->graphql_single_name ),
				'interfaces'  => [ WPObjectType::node_interface() ],
				'fields'      => [
					'id'                => [
						'type'        => [
							'non_null' => 'ID',
						],
						# Placeholder is the name of the taxonomy
						'description' => __( 'The global ID for the ' . $taxonomy_object->name, 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, $args, AppContext $context, ResolveInfo $info ) {
							return ( ! empty( $term->taxonomy ) && ! empty( $term->term_id ) ) ? Relay::toGlobalId( $term->taxonomy, $term->term_id ) : null;
						},
					],
					$taxonomy_object->graphql_single_name . 'Id' => [
						'type'        => 'Int',
						'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->term_id ) ? absint( $term->term_id ) : null;
						},
					],
					'count'             => [
						'type'        => 'Int',
						'description' => __( 'The number of objects connected to the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->count ) ? absint( $term->count ) : null;
						},
					],
					'description'       => [
						'type'        => 'String',
						'description' => __( 'The description of the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->description ) ? $term->description : null;
						},
					],
					'name'              => [
						'type'        => 'String',
						'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->name ) ? $term->name : null;
						},
					],
					'slug'              => [
						'type'        => 'String',
						'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->slug ) ? $term->slug : null;
						},
					],
					'termGroupId'       => [
						'type'        => 'Int',
						'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->term_group ) ? absint( $term->term_group ) : null;
						},
					],
					'termTaxonomyId'    => [
						'type'        => 'Int',
						'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $term->term_taxonomy_id ) ? absint( $term->term_taxonomy_id ) : null;
						},
					],
					'taxonomy'          => [
						'type'        => 'Taxonomy',
						'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, AppContext $context, ResolveInfo $info ) {
							$taxonomy = get_taxonomy( $term->taxonomy );

							return ! empty( $term->taxonomy ) && false !== $taxonomy ? $taxonomy : null;
						},
					],
					'link'              => [
						'type'        => 'String',
						'description' => __( 'The link to the term', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, $args, AppContext $context, ResolveInfo $info ) {
							$link = get_term_link( $term->term_id );

							return ( ! is_wp_error( $link ) ) ? $link : null;
						},
					],
				],
			]);

		}
	}
}