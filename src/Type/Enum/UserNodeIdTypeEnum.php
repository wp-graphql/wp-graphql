<?php
namespace WPGraphQL\Type\Enum;

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
				'description' => __( 'The Type of Identifier used to fetch a single User node. To be used along with the "id" field. Default is "ID".', 'wp-graphql' ),
				'values'      => self::get_values(),
			]
		);
	}

	/**
	 * Returns the values for the Enum.
	 *
	 * @return array
	 */
	public static function get_values() {
		return [
			'ID'          => [
				'name'        => 'ID',
				'value'       => 'global_id',
				'description' => __( 'The hashed Global ID', 'wp-graphql' ),
			],
			'DATABASE_ID' => [
				'name'        => 'DATABASE_ID',
				'value'       => 'database_id',
				'description' => __( 'The Database ID for the node', 'wp-graphql' ),
			],
			'URI'         => [
				'name'        => 'URI',
				'value'       => 'uri',
				'description' => __( 'The URI for the node', 'wp-graphql' ),
			],
			'SLUG'        => [
				'name'        => 'SLUG',
				'value'       => 'slug',
				'description' => __( 'The slug of the User', 'wp-graphql' ),
			],
			'EMAIL'       => [
				'name'        => 'EMAIL',
				'value'       => 'email',
				'description' => __( 'The Email of the User', 'wp-graphql' ),
			],
			'USERNAME'    => [
				'name'        => 'USERNAME',
				'value'       => 'login',
				'description' => __( 'The username the User uses to login with', 'wp-graphql' ),
			],
		];
	}
}
