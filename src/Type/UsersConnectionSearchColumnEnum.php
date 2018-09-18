<?php
namespace WPGraphQL\Type;

class UsersConnectionSearchColumnEnum {
	public static function register_type() {
		register_graphql_enum_type( 'UsersConnectionSearchColumnEnum', [
			'description' => __( 'Column used for searching for users', 'wp-graphql' ),
			'values' => [
				'ID'       => [
					'value' => 'ID',
				],
				'LOGIN'    => [
					'value' => 'login',
				],
				'NICENAME' => [
					'value' => 'nicename',
				],
				'EMAIL'    => [
					'value' => 'email',
				],
				'URL'      => [
					'value' => 'url',
				],
			],
		]);
	}
}