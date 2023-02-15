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
				'description' => __( 'Column used for searching for users.', 'wp-graphql' ),
				'values'      => [
					'ID'       => [
						'value'       => 'ID',
						'description' => __( 'The globally unique ID.', 'wp-graphql' ),
					],
					'LOGIN'    => [
						'value'       => 'login',
						'description' => __( 'The username the User uses to login with.', 'wp-graphql' ),
					],
					'NICENAME' => [
						'value'       => 'nicename',
						'description' => __( 'A URL-friendly name for the user. The default is the user\'s username.', 'wp-graphql' ),
					],
					'EMAIL'    => [
						'value'       => 'email',
						'description' => __( 'The user\'s email address.', 'wp-graphql' ),
					],
					'URL'      => [
						'value'       => 'url',
						'description' => __( 'The URL of the user\'s website.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
