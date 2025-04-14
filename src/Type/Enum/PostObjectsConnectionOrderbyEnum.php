<?php

namespace WPGraphQL\Type\Enum;

class PostObjectsConnectionOrderbyEnum {

	/**
	 * Register the PostObjectsConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'PostObjectsConnectionOrderbyEnum',
			[
				'description' => static function () {
					return __( 'Field to order the connection by', 'wp-graphql' );
				},
				'values'      => [
					'AUTHOR'        => [
						'value'       => 'post_author',
						'description' => static function () {
							return __( 'Order by author', 'wp-graphql' );
						},
					],
					'TITLE'         => [
						'value'       => 'post_title',
						'description' => static function () {
							return __( 'Order by title', 'wp-graphql' );
						},
					],
					'SLUG'          => [
						'value'       => 'post_name',
						'description' => static function () {
							return __( 'Order by slug', 'wp-graphql' );
						},
					],
					'MODIFIED'      => [
						'value'       => 'post_modified',
						'description' => static function () {
							return __( 'Order by last modified date', 'wp-graphql' );
						},
					],
					'DATE'          => [
						'value'       => 'post_date',
						'description' => static function () {
							return __( 'Order by publish date', 'wp-graphql' );
						},
					],
					'PARENT'        => [
						'value'       => 'post_parent',
						'description' => static function () {
							return __( 'Order by parent ID', 'wp-graphql' );
						},
					],
					'IN'            => [
						'value'       => 'post__in',
						'description' => static function () {
							return __( 'Preserve the ID order given in the IN array', 'wp-graphql' );
						},
					],
					'NAME_IN'       => [
						'value'       => 'post_name__in',
						'description' => static function () {
							return __( 'Preserve slug order given in the NAME_IN array', 'wp-graphql' );
						},
					],
					'MENU_ORDER'    => [
						'value'       => 'menu_order',
						'description' => static function () {
							return __( 'Order by the menu order value', 'wp-graphql' );
						},
					],
					'COMMENT_COUNT' => [
						'value'       => 'comment_count',
						'description' => static function () {
							return __( 'Order by the number of comments it has acquired', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
