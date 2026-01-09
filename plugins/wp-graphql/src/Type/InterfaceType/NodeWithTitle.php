<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithTitle {

	/**
	 * Registers the NodeWithTitle Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithTitle',
			[
				'interfaces'  => [ 'Node' ],
				'description' => static function () {
					return __( 'Content with a dedicated title field. The title typically serves as the main heading and identifier for the content.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'title' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The title of the post. This is currently just the raw title. An amendment to support rendered title needs to be made.', 'wp-graphql' );
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
									return $source->titleRaw;
								}

								// @codingStandardsIgnoreLine.
								return $source->titleRendered;
							},
						],
					];
				},
			]
		);
	}
}
