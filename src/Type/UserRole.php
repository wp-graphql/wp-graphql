<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

class UserRole {
	public static function register_type() {
		register_graphql_object_type( 'UserRole', [
			'description' => __( 'A user role object', 'wp-graphql' ),
			'fields' => [
				'id'           => [
					'type'        => [
						'non_null' => 'ID'
					],
					'description' => __( 'The globally unique identifier for the role', 'wp-graphql' ),
					'resolve'     => function ( $role, $args, AppContext $context, ResolveInfo $info ) {
						return Relay::toGlobalId( 'role', $role['id'] );
					}
				],
				'name'         => [
					'type'        => 'String',
					'description' => __( 'The UI friendly name of the role' ),
					'resolve'     => function ( $role, $args, AppContext $context, ResolveInfo $info ) {
						return esc_html( $role['name'] );
					}
				],
				'capabilities' => [
					'type'        => [
						'list_of' => 'String'
					],
					'description' => __( 'The capabilities that belong to this role', 'wp-graphql' ),
					'resolve'     => function ( $role, $args, AppContext $context, ResolveInfo $info ) {
						return array_keys( $role['capabilities'], true, true );
					}
				]
			]
		]);
	}
}