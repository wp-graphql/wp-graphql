<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

class AcfeCountries {

	/**
	 * @param array<mixed>|string $countries
	 *
	 * @return array<mixed>|null
	 */
	public static function resolve_countries( $countries ): ?array {
		if ( empty( $countries ) ) {
			return null;
		}

		if ( ! function_exists( 'acfe_get_country' ) ) {
			return null;
		}

		if ( ! is_array( $countries ) ) {
			$countries = [ $countries ];
		}

		return array_filter(
			array_map(
				static function ( $country ) {
					return acfe_get_country( $country );
				},
				$countries
			)
		);
	}

	/**
	 * Register support for the ACF Extended acfe_countries field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'acfe_countries',
			[
				'graphql_type' => static function () {
					return [ 'list_of' => 'ACFE_Country' ];
				},
				'resolve'      => static function ( $root, $args, $context, $info, $field_type, $field_config ) {
					$value = $field_config->resolve_field( $root, $args, $context, $info );

					return self::resolve_countries( $value );
				},
			]
		);
	}
}
