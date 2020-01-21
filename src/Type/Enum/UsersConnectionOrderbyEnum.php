<?php

namespace WPGraphQL\Type\Enum;

class UsersConnectionOrderbyEnum {
	public static function register_type() {

		register_graphql_enum_type(
			'UsersConnectionOrderbyEnum',
			[
				'description' => __( 'Field to order the connection by', 'wp-graphql' ),
				'values'      => [
					'DISPLAY_NAME' => [
						'value'       => 'display_name',
						'description' => __( 'Order by display name', 'wp-graphql' ),
					],
					'EMAIL'        => [
						'value'       => 'user_email',
						'description' => __( 'Order by email address', 'wp-graphql' ),
					],
					'LOGIN'        => [
						'value'       => 'user_login',
						'description' => __( 'Order by login', 'wp-graphql' ),
					],
					'LOGIN_IN'     => [
						'value'       => 'login__in',
						'description' => __( 'Preserve the login order given in the LOGIN_IN array', 'wp-graphql' ),
					],
					'NICE_NAME'    => [
						'value'       => 'user_nicename',
						'description' => __( 'Order by nice name', 'wp-graphql' ),
					],
					'NICE_NAME_IN' => [
						'value'       => 'nicename__in',
						'description' => __( 'Preserve the nice name order given in the NICE_NAME_IN array', 'wp-graphql' ),
					],
					'REGISTERED'   => [
						'value'       => 'user_registered',
						'description' => __( 'Order by registration date', 'wp-graphql' ),
					],
					'URL'          => [
						'value'       => 'user_url',
						'description' => __( 'Order by URL', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
