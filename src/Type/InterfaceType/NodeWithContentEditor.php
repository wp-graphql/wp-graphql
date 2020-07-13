<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithContentEditor {
	/**
	 * @param TypeRegistry $type_registry Instance of the Type Registry
	 */
	public static function register_type( $type_registry ) {
		register_graphql_interface_type(
			'NodeWithContentEditor',
			[
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
						'resolve'     => function( $source, $args ) {
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
