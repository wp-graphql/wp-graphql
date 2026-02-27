<?php
namespace WPGraphQL\Acf\FieldType;

class Textarea {

	/**
	 * Register support for the "textarea" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type( 'textarea' );
	}
}
