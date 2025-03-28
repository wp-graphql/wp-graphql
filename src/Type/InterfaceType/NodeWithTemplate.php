<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithTemplate {

	/**
	 * Registers the NodeWithTemplate Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithTemplate',
			[
				'description' => __( 'Content that provides template metadata. The template can help inform how the content is might be structured, styled, and presented to the user.', 'wp-graphql' ),
				'interfaces'  => [ 'Node' ],
				'fields'      => [
					'template' => [
						'description' => __( 'The template assigned to the node', 'wp-graphql' ),
						'type'        => 'ContentTemplate',
					],
				],
			]
		);
	}
}
