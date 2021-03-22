<?php
namespace WPGraphQL\Type\Enum;

class TermObjectsConnectionOrderbyEnum {

	/**
	 * Register the TermObjectsConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'TermObjectsConnectionOrderbyEnum',
			[
				'description' => __( 'Options for ordering the connection by', 'wp-graphql' ),
				'values'      => [
					'NAME'        => [
						'value'       => 'name',
						'description' => 'Order the connection by name.',
					],
					'SLUG'        => [
						'value'       => 'slug',
						'description' => 'Order the connection by slug.',
					],
					'TERM_GROUP'  => [
						'value'       => 'term_group',
						'description' => 'Order the connection by term group.',
					],
					'TERM_ID'     => [
						'value'       => 'term_id',
						'description' => 'Order the connection by term id.',
					],
					'TERM_ORDER'  => [
						'value'       => 'term_order',
						'description' => 'Order the connection by term order.',
					],
					'DESCRIPTION' => [
						'value'       => 'description',
						'description' => 'Order the connection by description.',
					],
					'COUNT'       => [
						'value'       => 'count',
						'description' => 'Order the connection by item count.',
					],
				],
			]
		);
	}
}
