<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

class AcfeCodeEditor {

	/**
	 * Register support for the ACF Extended acfe_code_editor field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type( 'acfe_code_editor' );
	}
}
