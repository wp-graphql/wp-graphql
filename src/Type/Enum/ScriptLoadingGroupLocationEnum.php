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
				'description' => __( 'Location in the document where the script to be loaded', 'wp-graphql' ),
				'values'      => [
					'HEADER' => [
						'value'       => 0,
						'description' => __( 'A script to be loaded in document `<head>` tag', 'wp-graphql' ),
					],
					'FOOTER' => [
						'value'       => 1,
						'description' => __( 'A script to be loaded in document at right before the closing `<body>` tag', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
