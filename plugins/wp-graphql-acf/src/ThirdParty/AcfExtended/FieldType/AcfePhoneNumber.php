<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

class AcfePhoneNumber {

	/**
	 * Register support for the ACF Extended acfe_phone_number field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type( 'acfe_phone_number' );
	}
}
