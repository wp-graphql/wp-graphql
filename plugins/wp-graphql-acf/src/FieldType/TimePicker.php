<?php
namespace WPGraphQL\Acf\FieldType;

class TimePicker {

	/**
	 * Register support for the "time_picker" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'time_picker',
			[
				'graphql_type' => 'String',
			]
		);
	}
}
