<?php
namespace WPGraphQL\Type\Enum;

class GuestCommenterIdTypeEnum {

	/**
	 * Register the Enum used for setting the field to identify User nodes by
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'GuestCommenterIdTypeEnum',
			[
				'description' => __( 'The Type of Identifier used to fetch a single GuestCommenter node. To be used along with the "id" field. Default is "ID".', 'wp-graphql' ),
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
		];
	}
}
