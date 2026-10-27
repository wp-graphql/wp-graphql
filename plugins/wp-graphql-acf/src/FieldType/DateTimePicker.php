<?php
namespace WPGraphQL\Acf\FieldType;

use DateTimeInterface;
use WPGraphQL\Acf\FieldConfig;

class DateTimePicker {

	/**
	 * Register support for the "date_time_picker" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'date_time_picker',
			[
				'graphql_type'              => 'String',
				// Apply a description to be appended to the field description.
				// @todo: consider removing when CustomScalar types are supported along with the @specifiedBy directive
				'graphql_description_after' => static function ( FieldConfig $field_config ) {
					$field_type = $field_config->get_acf_field()['type'] ?? null;

					// translators: The $s is the name of the acf field type that is returning a date string according to the RFC3339 spec.
					return '(' . sprintf( __( 'ACF Fields of the "%s" type return a date string according to the RFC3339 spec: https://datatracker.ietf.org/doc/html/rfc3339.', 'wpgraphql-acf' ), $field_type ) . ')';
				},
				'resolve'                   => static function ( $root, $args, $context, $info, $field_type, FieldConfig $field_config ) {
					$value = $field_config->resolve_field( $root, $args, $context, $info );

					if ( empty( $value ) ) {
						return null;
					}

					// Stored values are typically 'Y-m-d H:i:s'. Parse directly without relying on return_format.
					$date_time = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );

					if ( false === $date_time ) {
						$date_time = \DateTime::createFromFormat( 'Y-m-d H:i:s|', $value );
					}

					if ( false === $date_time ) {
						// Stored without seconds: reset seconds to 00.
						$date_time = \DateTime::createFromFormat( 'Y-m-d H:i|', $value );
					}

					if ( false === $date_time ) {
						// Stored date only: reset time to 00:00:00.
						$date_time = \DateTime::createFromFormat( 'Y-m-d|', $value );
					}

					if ( false === $date_time ) {
						$date_time = \DateTime::createFromFormat( 'Ymd|', $value );
					}

					if ( false === $date_time ) {
						// Fallback: in case value was modified or formatted by a custom filter or return format.
						$acf_field     = $field_config->get_acf_field();
						$return_format = $acf_field['return_format'] ?? null;

						if ( ! empty( $return_format ) ) {
							$date_time = \DateTime::createFromFormat( $return_format . '|', $value );
							if ( false === $date_time ) {
								$date_time = \DateTime::createFromFormat( $return_format, $value );
							}
						}
					}

					if ( false === $date_time && is_string( $value ) ) {
						$timestamp = strtotime( $value );
						if ( false !== $timestamp ) {
							$date_time = ( new \DateTime() )->setTimestamp( $timestamp );
						}
					}

					if ( empty( $date_time ) ) {
						return null;
					}

					return $date_time->format( DateTimeInterface::RFC3339 );
				},
			]
		);
	}
}
