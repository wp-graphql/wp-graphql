<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class PageInfo {
	/**
	 * Register the PageInfo Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'WPPageInfo',
			[
				'description' => static function () {
					return __( 'Metadata for cursor-based pagination. Provides cursors for continuing pagination and boolean flags indicating if more items exist in either direction.', 'wp-graphql' );
				},
				'interfaces'  => [ 'PageInfo' ],
				'fields'      => static function () {
					return self::get_fields();
				},
			]
		);

		register_graphql_interface_type(
			'PageInfo',
			[
				'description' => static function () {
					return __( 'Metadata for cursor-based pagination. Provides cursors for continuing pagination and boolean flags indicating if more items exist in either direction.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return self::get_fields();
				},
			]
		);
	}

	/**
	 * Get the fields for the PageInfo Type
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields(): array {
		return [
			'hasNextPage'     => [
				'type'        => [
					'non_null' => 'Boolean',
				],
				'description' => static function () {
					return __( 'When paginating forwards, are there more items?', 'wp-graphql' );
				},
			],
			'hasPreviousPage' => [
				'type'        => [
					'non_null' => 'Boolean',
				],
				'description' => static function () {
					return __( 'When paginating backwards, are there more items?', 'wp-graphql' );
				},
			],
			'startCursor'     => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'When paginating backwards, the cursor to continue.', 'wp-graphql' );
				},
			],
			'endCursor'       => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'When paginating forwards, the cursor to continue.', 'wp-graphql' );
				},
			],
		];
	}
}
