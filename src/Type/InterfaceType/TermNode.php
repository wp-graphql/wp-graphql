<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

class TermNode {

	/**
	 * Register the TermNode Interface
	 *
	 * @param TypeRegistry $type_registry
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type(
			'TermNode',
			[
				'description' => __( 'Terms are nodes within a Taxonomy, used to group and relate other nodes.', 'wp-graphql' ),
				'resolveType' => function( $term ) use ( $type_registry ) {

					/**
					 * The resolveType callback is used at runtime to determine what Type an object
					 * implementing the ContentNode Interface should be resolved as.
					 *
					 * You can filter this centrally using the "graphql_wp_interface_type_config" filter
					 * to override if you need something other than a Post object to be resolved via the
					 * $post->post_type attribute.
					 */
					$type = null;

					if ( isset( $term->taxonomyName ) ) {
						$tax_object = get_taxonomy( $term->taxonomyName );
						if ( isset( $tax_object->graphql_single_name ) ) {
							$type = $type_registry->get_type( $tax_object->graphql_single_name );
						}
					}

					return ! empty( $type ) ? $type : null;

				},
				'fields'      => [
					'id'             => [
						'type'        => [ 'non_null' => 'ID' ],
						'description' => __( 'Unique identifier for the term', 'wp-graphql' ),
					],
					'databaseId'     => [
						'type'        => [ 'non_null' => 'Int' ],
						'description' => __( 'Identifies the primary key from the database.', 'wp-graphql' ),
						'resolve'     => function( Term $term, $args, $context, $info ) {
							return absint( $term->term_id );
						},
					],
					'count'          => [
						'type'        => 'Int',
						'description' => __( 'The number of objects connected to the object', 'wp-graphql' ),
					],
					'description'    => [
						'type'        => 'String',
						'description' => __( 'The description of the object', 'wp-graphql' ),
					],
					'name'           => [
						'type'        => 'String',
						'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
					],
					'slug'           => [
						'type'        => 'String',
						'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
					],
					'taxonomy'       => [
						'type'        => 'Taxonomy',
						'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
						'resolve'     => function( $source, $args, $context, $info ) {
							return DataSource::resolve_taxonomy( $source->taxonomyName );
						},
					],
					'termGroupId'    => [
						'type'        => 'Int',
						'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
					],
					'termTaxonomyId' => [
						'type'        => 'Int',
						'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
					],
					'isRestricted'   => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'link'           => [
						'type'        => 'String',
						'description' => __( 'The link to the term', 'wp-graphql' ),
					],
					'uri'            => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
