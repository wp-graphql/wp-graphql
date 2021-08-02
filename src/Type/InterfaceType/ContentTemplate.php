<?php

namespace WPGraphQL\Type\InterfaceType;

class ContentTemplate {

	/**
	 * Register the ContentTemplate Interface
	 *
	 * @return void
	 */
	public static function register_type() {
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
				'resolveType' => function ( $value ) {
					return isset( $value['__typename'] ) ? $value['__typename'] : 'DefaultTemplate';
				},
			]
		);
	}
}
