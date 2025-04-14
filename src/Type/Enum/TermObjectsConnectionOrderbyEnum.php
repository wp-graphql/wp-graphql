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
				'description' => static function () {
					return __( 'Options for ordering the connection by', 'wp-graphql' );
				},
				'values'      => [
					'NAME'        => [
						'value'       => 'name',
						'description' => static function () {
							return __( 'Order the connection by name.', 'wp-graphql' );
						},
					],
					'SLUG'        => [
						'value'       => 'slug',
						'description' => static function () {
							return __( 'Order the connection by slug.', 'wp-graphql' );
						},
					],
					'TERM_GROUP'  => [
						'value'       => 'term_group',
						'description' => static function () {
							return __( 'Order the connection by term group.', 'wp-graphql' );
						},
					],
					'TERM_ID'     => [
						'value'       => 'term_id',
						'description' => static function () {
							return __( 'Order the connection by term id.', 'wp-graphql' );
						},
					],
					'TERM_ORDER'  => [
						'value'       => 'term_order',
						'description' => static function () {
							return __( 'Order the connection by term order.', 'wp-graphql' );
						},
					],
					'DESCRIPTION' => [
						'value'       => 'description',
						'description' => static function () {
							return __( 'Order the connection by description.', 'wp-graphql' );
						},
					],
					'COUNT'       => [
						'value'       => 'count',
						'description' => static function () {
							return __( 'Order the connection by item count.', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
