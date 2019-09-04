<?php

namespace WPGraphQL\Type;

register_graphql_object_type(
	'UserRole',
	[
		'description' => __( 'A user role object', 'wp-graphql' ),
		'fields'      => [
			'id'           => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The globally unique identifier for the role', 'wp-graphql' ),
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
