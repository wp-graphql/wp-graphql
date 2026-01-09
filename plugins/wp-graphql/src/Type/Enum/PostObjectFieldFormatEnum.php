<?php

namespace WPGraphQL\Type\Enum;

class PostObjectFieldFormatEnum {

	/**
	 * Register the PostObjectFieldFormatEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'PostObjectFieldFormatEnum',
			[
				'description' => static function () {
					return __( 'Content field rendering options. Determines whether content fields are returned as raw data or with applied formatting and transformations. Default is RENDERED.', 'wp-graphql' );
				},
				'values'      => [
					'RAW'      => [
						'name'        => 'RAW',
						'description' => static function () {
							return __( 'Unprocessed content exactly as stored in the database, requires appropriate permissions.', 'wp-graphql' );
						},
						'value'       => 'raw',
					],
					'RENDERED' => [
						'name'        => 'RENDERED',
						'description' => static function () {
							return __( 'Content with all formatting and transformations applied, ready for display.', 'wp-graphql' );
						},
						'value'       => 'rendered',
					],
				],
			]
		);
	}
}
