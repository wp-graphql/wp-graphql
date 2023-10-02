<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

class TermNode {

	/**
	 * Register the TermNode Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'TermNode',
			[
				'interfaces'  => [ 'Node', 'UniformResourceIdentifiable' ],
				'connections' => [
					'enqueuedScripts'     => [
						'toType'  => 'EnqueuedScript',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'enqueuedStylesheets' => [
						'toType'  => 'EnqueuedStylesheet',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );
							return $resolver->get_connection();
						},
					],
				],
				'description' => __( 'Terms are nodes within a Taxonomy, used to group and relate other nodes.', 'wp-graphql' ),
				'resolveType' => static function ( $term ) use ( $type_registry ) {

					/**
					 * The resolveType callback is used at runtime to determine what Type an object
					 * implementing the TermNode Interface should be resolved as.
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
					'databaseId'     => [
						'type'        => [ 'non_null' => 'Int' ],
						'description' => __( 'Identifies the primary key from the database.', 'wp-graphql' ),
						'resolve'     => static function ( Term $term ) {
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
					'termGroupId'    => [
						'type'        => 'Int',
						'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
					],
					'termTaxonomyId' => [
						'type'        => 'Int',
						'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
					],
					'taxonomyName'   => [
						'type'        => 'String',
						'description' => __( 'The name of the taxonomy that the object is associated with', 'wp-graphql' ),
					],
					'isRestricted'   => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'link'           => [
						'type'        => 'String',
						'description' => __( 'The link to the term', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
