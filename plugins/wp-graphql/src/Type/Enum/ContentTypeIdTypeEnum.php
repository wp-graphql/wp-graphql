<?php

namespace WPGraphQL\Type\Enum;

class ContentTypeIdTypeEnum {

	/**
	 * Register the ContentTypeIdTypeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'ContentTypeIdTypeEnum',
			[
				'description' => static function () {
					return __( 'Identifier types for retrieving a specific content type definition. Determines whether to look up content types by ID or name.', 'wp-graphql' );
				},
				'values'      => [
					'ID'   => [
						'name'        => 'ID',
						'value'       => 'id',
						'description' => static function () {
							return __( 'The globally unique ID', 'wp-graphql' );
						},
					],
					'NAME' => [
						'name'        => 'NAME',
						'value'       => 'name',
						'description' => static function () {
							return __( 'The name of the content type.', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
