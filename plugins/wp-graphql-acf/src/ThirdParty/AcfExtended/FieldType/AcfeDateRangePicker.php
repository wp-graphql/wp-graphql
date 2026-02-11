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
					$start_date = ( is_array( $value ) && isset( $value['start'] ) ) ? $value['start'] : null;
					$end_date   = ( is_array( $value ) && isset( $value['end'] ) ) ? $value['end'] : null;
					$acf_field  = $field_config->get_acf_field();

					// Get the node ID for resolving fields - use the same approach as resolve_field
					$node    = $root['node'] ?? null;
					$node_id = $node ? \WPGraphQL\Acf\Utils::get_node_acf_id( $node ) : null;

					// If node_id is still empty or 0, try to get it from the root directly
					if ( empty( $node_id ) || 0 === $node_id ) {
						$node_id_from_root = \WPGraphQL\Acf\Utils::get_node_acf_id( $root );
						if ( ! empty( $node_id_from_root ) && 0 !== $node_id_from_root ) {
							$node_id = $node_id_from_root;
						}
					}

					// ACFE Date Range Picker stores values as separate _start and _end fields
					// Always try to get them if the main value doesn't have start/end keys
					if ( empty( $start_date ) && ! empty( $node_id ) && 0 !== $node_id && function_exists( 'get_field' ) ) {
						$field_start = get_field( $acf_field['name'] . '_start', $node_id, false );
						if ( ! empty( $field_start ) ) {
							$start_date = $field_start;
						}
					}

					if ( empty( $end_date ) && ! empty( $node_id ) && 0 !== $node_id && function_exists( 'get_field' ) ) {
						$field_end = get_field( $acf_field['name'] . '_end', $node_id, false );
						if ( ! empty( $field_end ) ) {
							$end_date = $field_end;
						}
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
