<?php
namespace WPGraphQL\Acf\FieldType;

class Email {

	/**
	 * Register support for the "email" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'email',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
