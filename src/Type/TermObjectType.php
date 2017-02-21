<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Connections;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class TermObjectType extends ObjectType {

	public function __construct( $taxonomy ) {

		$node_definition    = DataSource::get_node_definition();
		$allowed_post_types = \WPGraphQL::$allowed_post_types;
		$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

		$taxonomy_object = get_taxonomy( $taxonomy );
		$single_name     = $taxonomy_object->graphql_single_name;

		$config = [
			'name'        => $single_name,
			'description' => sprintf( __( 'The % object type', 'wp-graphql' ), $single_name ),
			'fields'      => function() use ( $single_name, $taxonomy_object, $allowed_post_types, $allowed_taxonomies ) {
				$fields = [
					'id'                => [
						'type'    => Types::non_null( Types::id() ),
						'resolve' => function( \WP_Term $term, $args, $context, ResolveInfo $info ) {
							return ( ! empty( $term->taxonomy ) && ! empty( $term->term_id ) ) ? Relay::toGlobalId( $term->taxonomy, $term->term_id ) : null;
						},
					],
					$single_name . 'Id' => [
						'type'        => Types::int(),
						'description' => esc_html__( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, $args, $context, ResolveInfo $info ) {
							return ! empty( $term->term_id ) ? absint( $term->term_id ) : null;
						},
					],
					'count'             => [
						'type'        => Types::int(),
						'description' => __( 'The number of objects connected to the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->count ) ? absint( $term->count ) : null;
						},
					],
					'description'       => [
						'type'        => Types::string(),
						'description' => __( 'The description of the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->description ) ? $term->description : null;
						},
					],
					'name'              => [
						'type'        => Types::string(),
						'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->name ) ? $term->name : null;
						},
					],
					'slug'              => [
						'type'        => Types::string(),
						'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->slug ) ? $term->slug : null;
						},
					],
					'termGroupId'       => [
						'type'        => Types::int(),
						'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->term_group ) ? absint( $term->term_group ) : null;
						},
					],
					'termTaxonomyId'    => [
						'type'        => Types::int(),
						'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->term_taxonomy_id ) ? absint( $term->term_taxonomy_id ) : null;
						},
					],
					'taxonomyName'      => [
						'type'        => Types::string(),
						'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->taxonomy ) ? $term->taxonomy : null;
						},
					],
					'link'              => [
						'type'        => Types::string(),
						'description' => __( 'The link to the term', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $term, $args, $context, ResolveInfo $info ) {
							$link = get_term_link( $term->term_id );

							return ( ! is_wp_error( $link ) ) ? $link : null;
						},
					],
				];

				/**
				 * Add connections for post_types that are registered to the taxonomy
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
					foreach ( $allowed_post_types as $post_type ) {
						$post_type_object                                 = get_post_type_object( $post_type );
						$fields[ $post_type_object->graphql_plural_name ] = Connections::post_objects_connection( $post_type_object );
					}
				}

				/**
				 * Pass the fields through a filter
				 *
				 * @param array $fields
				 *
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_term_object_type_fields_' . $single_name, $fields, $taxonomy_object );

				/**
				 * Sort the fields alphabetically by key. This makes reading through docs much easier
				 * @since 0.0.2
				 */
				ksort( $fields );

				return $fields;

			},
			'interfaces'  => [ $node_definition['nodeInterface'] ],
		];
		parent::__construct( $config );
	}
}
