<?php
namespace WPGraphQL\Acf\FieldType;

class ButtonGroup {

	/**
	 * Register support for the "button_group" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'button_group',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
