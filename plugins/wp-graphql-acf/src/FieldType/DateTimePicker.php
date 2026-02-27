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

					$acf_field = $field_config->get_acf_field();

					// Get the return format from the ACF Field
					$return_format = $acf_field['return_format'] ?? null;

					if ( empty( $return_format ) ) {
						return $value;
					}

					$date_time = \DateTime::createFromFormat( $return_format, $value );

					if ( empty( $date_time ) ) {
						return null;
					}

					return $date_time->format( DateTimeInterface::RFC3339 );
				},
			]
		);
	}
}
