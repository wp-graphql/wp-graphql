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
				'description' => __( 'The format of post field data.', 'wp-graphql' ),
				'values'      => [
					'RAW'      => [
						'name'        => 'RAW',
						'description' => __( 'Provide the field value directly from database. Null on unauthenticated requests.', 'wp-graphql' ),
						'value'       => 'raw',
					],
					'RENDERED' => [
						'name'        => 'RENDERED',
						'description' => __( 'Provide the field value as rendered by WordPress. Default.', 'wp-graphql' ),
						'value'       => 'rendered',
					],
				],
			]
		);
	}
}
