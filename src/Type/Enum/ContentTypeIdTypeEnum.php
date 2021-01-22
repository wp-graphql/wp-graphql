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
				'description' => __( 'The Type of Identifier used to fetch a single Content Type node. To be used along with the "id" field. Default is "ID".', 'wp-graphql' ),
				'values'      => [
					'ID'   => [
						'name'        => 'ID',
						'value'       => 'id',
						'description' => __( 'The globally unique ID', 'wp-graphql' ),
					],
					'NAME' => [
						'name'        => 'NAME',
						'value'       => 'name',
						'description' => __( 'The name of the content type.', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
