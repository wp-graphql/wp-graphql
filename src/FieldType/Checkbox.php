<?php
namespace WPGraphQL\Acf\FieldType;

class Checkbox {

	/**
	 * Register support for the "checkbox" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'checkbox',
			[
				'graphql_type' => [ 'list_of' => 'String' ],
			]
		);
	}
}
