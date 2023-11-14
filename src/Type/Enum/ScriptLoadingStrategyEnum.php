<?php
/**
 * Register the ScriptLoadingStrategy Enum Type to the Schema
 *
 * @package WPGraphQL\Type\Enum
 * @since 1.19.0
 */

namespace WPGraphQL\Type\Enum;

/**
 * Class ScriptLoadingStrategyEnum
 */
class ScriptLoadingStrategyEnum {

	/**
	 * Register the ScriptLoadingStrategy Enum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'ScriptLoadingStrategyEnum',
			[
				'description' => __( 'The strategy to use when loading the script', 'wp-graphql' ),
				'values'      => [
					'ASYNC' => [
						'value'       => 'async',
						'description' => __( 'Use the script `async` attribute', 'wp-graphql' ),
					],
					'DEFER' => [
						'value'       => 'defer',
						'description' => __( 'Use the script `defer` attribute', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
