<?php
namespace WPGraphQL\Acf\FieldType;

class Select {

	/**
	 * Register support for the "select" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'select',
			[
				'graphql_type' => [ 'list_of' => 'String' ],
			]
		);
	}
}
