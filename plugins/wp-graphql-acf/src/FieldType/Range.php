<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\FieldConfig;

class Range {

	/**
	 * Register support for the "range" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'range',
			[
				'graphql_type' => 'Float',
				'resolve'      => static function ( $root, $args, $context, $info, $field_type, FieldConfig $field_config ) {
					$value = $field_config->resolve_field( $root, $args, $context, $info );
					return (float) $value;
				},
			]
		);
	}
}
