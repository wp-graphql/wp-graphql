<?php
namespace WPGraphQL\Type\Enum;

/**
 * Class - UserNodeIdTypeEnum
 *
 * @package WPGraphQL\Type\Enum
 *
 * @phpstan-import-type PartialWPEnumValueConfig from \WPGraphQL\Type\WPEnumType
 */
class UserNodeIdTypeEnum {

	/**
	 * Register the Enum used for setting the field to identify User nodes by
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'UserNodeIdTypeEnum',
			[
				'description' => static function () {
					return __( 'Identifier types for retrieving a specific user. Determines whether to look up users by ID, email, username, or other unique properties.', 'wp-graphql' );
				},
				'values'      => self::get_values(),
			]
		);
	}

	/**
	 * Returns the values for the Enum.
	 *
	 * @return array<string,PartialWPEnumValueConfig>
	 */
	public static function get_values() {
		return [
			'ID'          => [
				'name'        => 'ID',
				'value'       => 'global_id',
				'description' => static function () {
					return __( 'The hashed Global ID', 'wp-graphql' );
				},
			],
			'DATABASE_ID' => [
				'name'        => 'DATABASE_ID',
				'value'       => 'database_id',
				'description' => static function () {
					return __( 'The Database ID for the node', 'wp-graphql' );
				},
			],
			'URI'         => [
				'name'        => 'URI',
				'value'       => 'uri',
				'description' => static function () {
					return __( 'The URI for the node', 'wp-graphql' );
				},
			],
			'SLUG'        => [
				'name'        => 'SLUG',
				'value'       => 'slug',
				'description' => static function () {
					return __( 'The slug of the User', 'wp-graphql' );
				},
			],
			'EMAIL'       => [
				'name'        => 'EMAIL',
				'value'       => 'email',
				'description' => static function () {
					return __( 'The Email of the User', 'wp-graphql' );
				},
			],
			'USERNAME'    => [
				'name'        => 'USERNAME',
				'value'       => 'login',
				'description' => static function () {
					return __( 'The username the User uses to login with', 'wp-graphql' );
				},
			],
		];
	}
}
