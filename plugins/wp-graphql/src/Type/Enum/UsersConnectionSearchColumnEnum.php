<?php

namespace WPGraphQL\Type\Enum;

class UsersConnectionSearchColumnEnum {

	/**
	 * Register the UsersConnectionSearchColumnEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'UsersConnectionSearchColumnEnum',
			[
				'description' => static function () {
					return __( 'User properties that can be targeted in search operations. Defines which user attributes can be searched when looking for specific users.', 'wp-graphql' );
				},
				'values'      => [
					'ID'       => [
						'value'       => 'ID',
						'description' => static function () {
							return __( 'The globally unique ID.', 'wp-graphql' );
						},
					],
					'LOGIN'    => [
						'value'       => 'user_login',
						'description' => static function () {
							return __( 'The username the User uses to login with.', 'wp-graphql' );
						},
					],
					'NICENAME' => [
						'value'       => 'user_nicename',
						'description' => static function () {
							return __( 'A URL-friendly name for the user. The default is the user\'s username.', 'wp-graphql' );
						},
					],
					'EMAIL'    => [
						'value'       => 'user_email',
						'description' => static function () {
							return __( 'The user\'s email address.', 'wp-graphql' );
						},
					],
					'URL'      => [
						'value'       => 'user_url',
						'description' => static function () {
							return __( 'The URL of the user\'s website.', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
