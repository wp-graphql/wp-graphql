<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithContentEditor {
	/**
	 * Registers the NodeWithContentEditor Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithContentEditor',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'Content that has a main body field which can contain formatted text and media. Provides access to both raw (with appropriate permissions) and rendered versions of the content.', 'wp-graphql' ),
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
						'resolve'     => static function ( $source, $args ) {
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
