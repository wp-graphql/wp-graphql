<?php
namespace WPGraphQL\Acf\FieldType;

class Password {

	/**
	 * Register support for the "password" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'password',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
