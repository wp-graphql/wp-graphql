<?php

namespace WPGraphQL\Type\UserRoles;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class UserRoleType
 *
 * @package WPGraphQL\Type\UserRoles
 * @since 0.0.30
 */
class UserRoleType extends WPObjectType {

	/**
	 * Stores the name of the type for use throughout the class
	 *
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * Stores the fields that are registered for this type
	 *
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * UserRoleType constructor.
	 */
	public function __construct() {

		self::$type_name = 'UserRole';

		$config = [
			'name' => self::$type_name,
			'description' => __( 'A user role object', 'wp-graphql' ),
			'fields' => self::fields(),
		];

		parent::__construct( $config );

	}

	/**
	 * Builds out the fields for the UserRoleType
	 *
	 * @return array|\Closure
	 * @access private
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function () {

				$fields = [
					'id'           => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'The globally unique identifier for the role', 'wp-graphql' ),
						'resolve'     => function ( $role, $args, AppContext $context, ResolveInfo $info ) {
							return Relay::toGlobalId( 'role', $role['id'] );
						}
					],
					'name'         => [
						'type'        => Types::string(),
						'description' => __( 'The UI friendly name of the role' ),
						'resolve'     => function ( $role, $args, AppContext $context, ResolveInfo $info ) {
							return esc_html( $role['name'] );
						}
					],
					'capabilities' => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'The capabilities that belong to this role', 'wp-graphql' ),
						'resolve'     => function ( $role, $args, AppContext $context, ResolveInfo $info ) {
							return array_keys( $role['capabilities'], true, true );
						}
					]
				];

				return self::prepare_fields( $fields, self::$type_name );

			};
		}

		return self::$fields;

	}

}
