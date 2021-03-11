<?php
namespace WPGraphQL\Type\InterfaceType;

use Exception;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithTitle {

	/**
	 * Registers the NodeWithTitle Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type(
			'NodeWithTitle',
			[
				'description' => __( 'A node that NodeWith a title', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'ContentNode', 'DatabaseIdentifier' ],
				'fields'      => [
					'title' => [
						'type'        => 'String',
						'description' => __( 'The title of the post. This is currently just the raw title. An amendment to support rendered title needs to be made.', 'wp-graphql' ),
						'args'        => [
							'format' => [
								'type'        => 'PostObjectFieldFormatEnum',
								'description' => __( 'Format of the field output', 'wp-graphql' ),
							],
						],
						'resolve'     => function( $source, $args ) {
							if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
								// @codingStandardsIgnoreLine.
								return $source->titleRaw;
							}

							// @codingStandardsIgnoreLine.
							return $source->titleRendered;
						},
					],
				],
			]
		);

	}
}
