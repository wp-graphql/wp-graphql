<?php
namespace WPGraphQL\Type\InterfaceType;

class Uri {
	public static function register_type() {
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
			]
		);
	}
}
