<?php
namespace WPGraphQL\Type\Enum;

class TermObjectsConnectionOrderbyEnum {
	public static function register_type() {
		register_graphql_enum_type(
			'TermObjectsConnectionOrderbyEnum',
			[
				'description' => __( 'Options for ordering the connection by', 'wp-graphql' ),
				'values'      => [
					'NAME'        => [
						'value' => 'name',
					],
					'SLUG'        => [
						'value' => 'slug',
					],
					'TERM_GROUP'  => [
						'value' => 'term_group',
					],
					'TERM_ID'     => [
						'value' => 'term_id',
					],
					'TERM_ORDER'  => [
						'value' => 'term_order',
					],
					'DESCRIPTION' => [
						'value' => 'description',
					],
					'COUNT'       => [
						'value' => 'count',
					],
				],
			]
		);

	}
}
