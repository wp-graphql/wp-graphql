<?php
/**
 * Register the ScriptLoadingGroupEnum Type to the Schema
 *
 * @package WPGraphQL\Type\Enum
 * @since TBD
 */

namespace WPGraphQL\Type\Enum;

/**
 * Class ScriptLoadingGroupEnum
 */
class ScriptLoadingGroupEnum {

	/**
	 * Register the ScriptLoadingStrategy Enum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'ScriptLoadingGroupEnum',
			[
				'description' => __( 'Locations for script to be loaded', 'wp-graphql' ),
				'values'      => [
					'HEADER' => [
						'value'       => 0,
						'description' => __( 'Script to be loaded in document `<head>` tags', 'wp-graphql' ),
					],
					'FOOTER' => [
						'value'       => 1,
						'description' => __( 'Script to be loaded in document at right before the closing `<body>` tag', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
