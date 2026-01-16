<?php

namespace WPGraphQL\Type\Enum;

/**
 * Class - PluginStatusEnum
 *
 * @package WPGraphQL\Type\Enum
 *
 * @phpstan-import-type PartialWPEnumValueConfig from \WPGraphQL\Type\WPEnumType
 */
class PluginStatusEnum {

	/**
	 * Register the PluginStatusEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'PluginStatusEnum',
			[
				'description'  => static function () {
					return __( 'Operational status of a plugin. Indicates whether a plugin is active, inactive, or in another state that affects its functionality.', 'wp-graphql' );
				},
				'values'       => self::get_enum_values(),
				'defaultValue' => 'ACTIVE',
			]
		);
	}

	/**
	 * Returns the values for the Enum.
	 *
	 * @return array<string,PartialWPEnumValueConfig>
	 */
	protected static function get_enum_values() {
		$values = [
			'ACTIVE'          => [
				'value'       => 'active',
				'description' => static function () {
					return __( 'The plugin is currently active.', 'wp-graphql' );
				},
			],
			'DROP_IN'         => [
				'value'       => 'dropins',
				'description' => static function () {
					return __( 'The plugin is a drop-in plugin.', 'wp-graphql' );
				},
			],
			'INACTIVE'        => [
				'value'       => 'inactive',
				'description' => static function () {
					return __( 'The plugin is currently inactive.', 'wp-graphql' );
				},
			],
			'MUST_USE'        => [
				'value'       => 'mustuse',
				'description' => static function () {
					return __( 'The plugin is a must-use plugin.', 'wp-graphql' );
				},
			],
			'PAUSED'          => [
				'value'       => 'paused',
				'description' => static function () {
					return __( 'The plugin is technically active but was paused while loading.', 'wp-graphql' );
				},
			],
			'RECENTLY_ACTIVE' => [
				'value'       => 'recently_activated',
				'description' => static function () {
					return __( 'The plugin was active recently.', 'wp-graphql' );
				},
			],
			'UPGRADE'         => [
				'value'       => 'upgrade',
				'description' => static function () {
					return __( 'The plugin has an upgrade available.', 'wp-graphql' );
				},
			],
		];

		// Multisite enums
		if ( is_multisite() ) {
			$values['NETWORK_ACTIVATED'] = [
				'value'       => 'network_activated',
				'description' => static function () {
					return __( 'The plugin is activated on the multisite network.', 'wp-graphql' );
				},
			];
			$values['NETWORK_INACTIVE']  = [
				'value'       => 'network_inactive',
				'description' => static function () {
					return __( 'The plugin is installed on the multisite network, but is currently inactive.', 'wp-graphql' );
				},
			];
		}

		return $values;
	}
}
