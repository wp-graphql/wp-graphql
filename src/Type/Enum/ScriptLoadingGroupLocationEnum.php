<?php
/**
 * Register the ScriptLoadingGroupLocationEnum Type to the Schema
 *
 * @package WPGraphQL\Type\Enum
 * @since 1.30.0
 */

namespace WPGraphQL\Type\Enum;

/**
 * Class ScriptLoadingGroupLocationEnum
 */
class ScriptLoadingGroupLocationEnum {

	/**
	 * Register the ScriptLoadingStrategy Enum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'ScriptLoadingGroupLocationEnum',
			[
				'description' => static function () {
					return __( 'Script insertion positions in the document structure. Determines whether scripts are placed in the document head or before the closing body tag.', 'wp-graphql' );
				},
				'values'      => [
					'HEADER' => [
						'value'       => 0,
						'description' => static function () {
							return __( 'Early loading in document `<head>` tag. (executes before page content renders)', 'wp-graphql' );
						},
					],
					'FOOTER' => [
						'value'       => 1,
						'description' => static function () {
							return __( 'Delayed loading at end of document, right before the closing `<body>` tag. (allows content to render first)', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
