<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class Uri {
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'Uri',
			[
				'description' => __( 'Any node that has a URI', 'wp-graphql' ),
				'fields'      => [
					'uri' => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
					],
				],
				'resolveType' => function() {
				
				}
			]
		);
	}
}
