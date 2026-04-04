<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

class AcfeImageSelector {

	/**
	 * Register support for the ACF Extended acfe_image_selector field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'acfe_image_selector',
			[
				'graphql_type' => [ 'list_of' => 'String' ],
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
