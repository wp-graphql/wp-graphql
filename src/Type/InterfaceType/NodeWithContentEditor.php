<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithContentEditor {
	/**
	 * Registers the NodeWithContentEditor Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithContentEditor',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'A node that supports the content editor', 'wp-graphql' ),
				'fields'      => [
					'content' => [
						'type'        => 'String',
						'description' => __( 'The content of the post.', 'wp-graphql' ),
						'args'        => [
							'format' => [
								'type'        => 'PostObjectFieldFormatEnum',
								'description' => __( 'Format of the field output', 'wp-graphql' ),
							],
						],
						'resolve'     => function ( $source, $args ) {
							if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
								// @codingStandardsIgnoreLine.
								return $source->contentRaw;
							}

							// @codingStandardsIgnoreLine.
							return $source->contentRendered;
						},
					],
				],
			]
		);
	}
}
