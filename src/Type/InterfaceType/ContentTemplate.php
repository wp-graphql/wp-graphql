<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class ContentTemplate {

	/**
	 * Register the ContentTemplate Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'ContentTemplate',
			[
				'description' => __( 'The template assigned to a node of content', 'wp-graphql' ),
				'fields'      => [
					'templateName' => [
						'type'        => 'String',
						'description' => __( 'The name of the template', 'wp-graphql' ),
					],
				],
				'resolveType' => function( $value ) {
					return isset( $value['__typename'] ) ? $value['__typename'] : 'DefaultTemplate';
				},
			]
		);
	}
}
