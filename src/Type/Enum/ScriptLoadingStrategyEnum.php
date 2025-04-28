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
				'description' => static function () {
					return __( 'Script loading optimization attributes. Controls browser behavior for script loading to improve page performance (async or defer).', 'wp-graphql' );
				},
				'values'      => [
					'ASYNC' => [
						'value'       => 'async',
						'description' => static function () {
							return __( 'Load script in parallel with page rendering, executing as soon as downloaded', 'wp-graphql' );
						},
					],
					'DEFER' => [
						'value'       => 'defer',
						'description' => static function () {
							return __( 'Download script in parallel but defer execution until page is fully parsed', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
