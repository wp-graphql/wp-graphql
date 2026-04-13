<?php
namespace WPGraphQL\Acf\FieldType;

class TrueFalse {

	/**
	 * Register support for the "true_false" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'true_false',
			[
				'graphql_type' => 'Boolean',
			]
		);
	}
}
