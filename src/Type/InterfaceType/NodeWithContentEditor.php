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
				'description' => static function () {
					return __( 'A node that supports the content editor', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'content' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The content of the post.', 'wp-graphql' );
							},
							'args'        => [
								'format' => [
									'type'        => 'PostObjectFieldFormatEnum',
									'description' => static function () {
										return __( 'Format of the field output', 'wp-graphql' );
									},
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
					];
				},
			]
		);
	}
}
