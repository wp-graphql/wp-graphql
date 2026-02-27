<?php
namespace WPGraphQL\Acf\FieldType;

class Link {

	/**
	 * Register support for the "link" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'link',
			[
				'graphql_type' => 'AcfLink',
			]
		);
	}
}
