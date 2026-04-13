<?php
namespace WPGraphQL\Acf\FieldType;

class Url {

	/**
	 * Register support for the "url" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'url',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
