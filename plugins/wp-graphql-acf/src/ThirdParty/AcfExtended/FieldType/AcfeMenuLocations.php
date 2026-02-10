<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

class AcfeMenuLocations {

	/**
	 * Register support for the ACF Extended acfe_menu_locations field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'acfe_menu_locations',
			[
				'graphql_type' => [ 'list_of' => 'MenuLocationEnum' ],
				'resolve'      => static function ( $root, $args, $context, $info, $field_type, $field_config ) {
					$value = $field_config->resolve_field( $root, $args, $context, $info );

					if ( empty( $value ) ) {
						return null;
					}

					if ( ! is_array( $value ) ) {
						$value = [ $value ];
					}

					return $value;
				},
			]
		);
	}
}
