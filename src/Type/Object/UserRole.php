<?php

namespace WPGraphQL\Type\Object;

class UserRole {
	public static function register_type() {
		register_graphql_object_type(
			'UserRole',
			[
				'description' => __( 'A user role object', 'wp-graphql' ),
				'interfaces'  => [ 'Node' ],
				'fields'      => [
					'id'           => [
						'description' => __( 'The globally unique identifier for the user role object.', 'wp-graphql' ),
					],
					'name'         => [
						'type'        => 'String',
						'description' => __( 'The UI friendly name of the role' ),
					],
					'capabilities' => [
						'type'        => [
							'list_of' => 'String',
						],
						'description' => __( 'The capabilities that belong to this role', 'wp-graphql' ),
					],
					'isRestricted' => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
