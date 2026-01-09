<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Model\UserRole as UserRoleModel;

class UserRole {

	/**
	 * Register the UserRole Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'UserRole',
			[
				'description' => static function () {
					return __( 'A user role object', 'wp-graphql' );
				},
				'model'       => UserRoleModel::class,
				'interfaces'  => [ 'Node' ],
				'fields'      => static function () {
					return [
						'id'           => [
							'description' => static function () {
								return __( 'The globally unique identifier for the user role object.', 'wp-graphql' );
							},
						],
						'name'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The registered name of the role', 'wp-graphql' );
							},
						],
						'capabilities' => [
							'type'        => [
								'list_of' => 'String',
							],
							'description' => static function () {
								return __( 'The capabilities that belong to this role', 'wp-graphql' );
							},
						],
						'displayName'  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The display name of the role', 'wp-graphql' );
							},
						],
						'isRestricted' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
