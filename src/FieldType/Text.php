<?php
namespace WPGraphQL\Acf\FieldType;

class Text {

	/**
	 * Register support for the "text" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type( 'text' );
	}
}
