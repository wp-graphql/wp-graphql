<?php
namespace WPGraphQL\Acf\FieldType;

class ColorPicker {

	/**
	 * Register support for the "color_picker" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'color_picker',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
