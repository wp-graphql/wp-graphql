<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithTitle {

	/**
	 * Registers the NodeWithTitle Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type(
			'NodeWithTitle',
			[
				'description' => __( 'A node that NodeWith a title', 'wp-graphql' ),
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
