<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class UsersConnectionSearchColumnEnum {
	public static function register_type() {

		register_graphql_enum_type(
			'UsersConnectionSearchColumnEnum',
			[
				'description' => __( 'Column name to be searched.', 'wp-graphql' ),
				'values'      => [
					'ID'        => [
						'value'       => 'ID',
						'description' => __( 'Search users by ID', 'wp-graphql' ),
					],
					'LOGIN'     => [
						'value'       => 'user_login',
						'description' => __( 'Search users by login', 'wp-graphql' ),
					],
					'NICE_NAME' => [
						'value'       => 'user_nicename',
						'description' => __( 'Search users by nicename', 'wp-graphql' ),
					],
					'EMAIL'     => [
						'value'       => 'user_email',
						'description' => __( 'Search users by email address', 'wp-graphql' ),
					],
					'URL'       => [
						'value'       => 'user_url',
						'description' => __( 'Search users by url', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
