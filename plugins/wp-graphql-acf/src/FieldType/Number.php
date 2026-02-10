<?php
namespace WPGraphQL\Acf\FieldType;

class Number {

	/**
	 * Register support for the "number" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'number',
			[
				'graphql_type' => 'Float',
			]
		);
	}
}
