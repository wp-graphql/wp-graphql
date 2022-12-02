<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithExcerpt {

	/**
	 * Registers the NodeWithExcerpt Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithExcerpt',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'A node that can have an excerpt', 'wp-graphql' ),
				'fields'      => [
					'excerpt' => [
						'type'        => 'String',
						'description' => __( 'The excerpt of the post.', 'wp-graphql' ),
						'args'        => [
							'format' => [
								'type'        => 'PostObjectFieldFormatEnum',
								'description' => __( 'Format of the field output', 'wp-graphql' ),
							],
						],
						'resolve'     => function ( $source, $args ) {
							if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
								// @codingStandardsIgnoreLine.
								return $source->excerptRaw;
							}

							// @codingStandardsIgnoreLine.
							return $source->excerptRendered;
						},
					],
				],
			]
		);
	}
}
