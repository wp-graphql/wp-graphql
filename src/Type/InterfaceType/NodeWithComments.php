<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithComments {
	/**
	 * Registers the NodeWithComments Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithComments',
			[
				'interfaces'  => [ 'Node' ],
				'description' => static function () {
					return __( 'A node that can have comments associated with it', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'commentCount'  => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatibility.', 'wp-graphql' );
							},
						],
						'commentStatus' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Whether the comments are open or closed for this particular post.', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
