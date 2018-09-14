<?php
namespace WPGraphQL\Type;

class UserSearchColumnEnum {
	public static function register_type() {
		register_graphql_enum_type( 'UserSearchColumnEnum', [
			'description' => __( 'Columns to search in', 'wp-graphql' ),
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