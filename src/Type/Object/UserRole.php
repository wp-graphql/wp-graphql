<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;

register_graphql_object_type( 'UserRole', [
	'description' => __( 'A user role object', 'wp-graphql' ),
	'fields'      => [
		'id'           => [
			'type'        => [
				'non_null' => 'ID'
			],
			'description' => __( 'The globally unique identifier for the role', 'wp-graphql' ),
			'resolve'     => function( $role, $args, $context, $info ) {
				return Relay::toGlobalId( 'role', $role['id'] );
			}
		],
		'name'         => [
			'type'        => 'String',
			'description' => __( 'The UI friendly name of the role' ),
			'resolve'     => function( $role, $args, $context, $info ) {
				return esc_html( $role['name'] );
			}
		],
		'capabilities' => [
			'type'        => [
				'list_of' => 'String'
			],
			'description' => __( 'The capabilities that belong to this role', 'wp-graphql' ),
			'resolve'     => function( $role, $args, $context, $info ) {
				return array_keys( $role['capabilities'], true, true );
			}
		]
	]
] );
