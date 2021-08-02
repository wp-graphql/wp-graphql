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
						'description' => __( 'Provide the field value directly from database', 'wp-graphql' ),
						'value'       => 'raw',
					],
					'RENDERED' => [
						'name'        => 'RENDERED',
						'description' => __( 'Apply the default WordPress rendering', 'wp-graphql' ),
						'value'       => 'rendered',
					],
				],
			]
		);
	}
}


