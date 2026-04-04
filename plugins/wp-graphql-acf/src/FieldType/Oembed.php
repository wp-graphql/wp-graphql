<?php
namespace WPGraphQL\Acf\FieldType;

class Oembed {

	/**
	 * Register support for the "oembed" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'oembed',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
