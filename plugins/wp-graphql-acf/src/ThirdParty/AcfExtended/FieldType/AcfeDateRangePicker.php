<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

use WPGraphQL\Acf\FieldConfig;

class AcfeDateRangePicker {

	/**
	 * Register support for the ACF Extended acfe_date_range_picker field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'acfe_date_range_picker',
			[
				'graphql_type' => 'ACFE_Date_Range',
				'resolve'      => static function ( $root, $args, $context, $info, $field_type, FieldConfig $field_config ) {
					$value      = $field_config->resolve_field( $root, $args, $context, $info );
					$start_date = $value['start'] ?? null;
					$end_date   = $value['end'] ?? null;
					$acf_field  = $field_config->get_acf_field();

					// handle resolving from a block
					if ( empty( $start_date ) ) {
						$start_date = $field_config->resolve_field( $root, $args, $context, $info, [ 'name' => $acf_field['name'] . '_start' ] );
					}

					// handle resolving from a block
					if ( empty( $end_date ) ) {
						$end_date = $field_config->resolve_field( $root, $args, $context, $info, [ 'name' => $acf_field['name'] . '_end' ] );
					}

					if ( ! empty( $start_date ) ) {
						$_start_date = \DateTime::createFromFormat( 'Ymd|', $start_date );
						if ( ! empty( $_start_date ) ) {
							$start_date = $_start_date->format( \DateTimeInterface::RFC3339 );
						}
					}

					if ( ! empty( $end_date ) ) {
						$_end_date = \DateTime::createFromFormat( 'Ymd|', $end_date );
						if ( ! empty( $_end_date ) ) {
							$end_date = $_end_date->format( \DateTimeInterface::RFC3339 );
						}
					}

					// @see: https://www.acf-extended.com/features/fields/date-range-picker#field-value
					// ACFE Date Range Picker returns unformatted value with Ymd format
					// NOTE: appending '|' to the format prevents the minutes and seconds from being determined from the current time
					return [
						'startDate' => ! empty( $start_date ) ? $start_date : null,
						'endDate'   => ! empty( $end_date ) ? $end_date : null,
					];
				},
			]
		);
	}
}
