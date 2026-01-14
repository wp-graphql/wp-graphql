<?php

namespace WPGraphQL\Type\Enum;

class UsersConnectionOrderbyEnum {

	/**
	 * Register the UsersConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'UsersConnectionOrderbyEnum',
			[
				'description' => static function () {
					return __( 'User attribute sorting options. Determines which property of user accounts is used for ordering user listings.', 'wp-graphql' );
				},
				'values'      => [
					'DISPLAY_NAME' => [
						'value'       => 'display_name',
						'description' => static function () {
							return __( 'Order by display name', 'wp-graphql' );
						},
					],
					'EMAIL'        => [
						'value'       => 'user_email',
						'description' => static function () {
							return __( 'Order by email address', 'wp-graphql' );
						},
					],
					'LOGIN'        => [
						'value'       => 'user_login',
						'description' => static function () {
							return __( 'Order by login', 'wp-graphql' );
						},
					],
					'LOGIN_IN'     => [
						'value'       => 'login__in',
						'description' => static function () {
							return __( 'Preserve the login order given in the LOGIN_IN array', 'wp-graphql' );
						},
					],
					'NICE_NAME'    => [
						'value'       => 'user_nicename',
						'description' => static function () {
							return __( 'Order by nice name', 'wp-graphql' );
						},
					],
					'NICE_NAME_IN' => [
						'value'       => 'nicename__in',
						'description' => static function () {
							return __( 'Preserve the nice name order given in the NICE_NAME_IN array', 'wp-graphql' );
						},
					],
					'REGISTERED'   => [
						'value'       => 'user_registered',
						'description' => static function () {
							return __( 'Order by registration date', 'wp-graphql' );
						},
					],
					'URL'          => [
						'value'       => 'user_url',
						'description' => static function () {
							return __( 'Order by URL', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
