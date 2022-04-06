<?php

namespace WPGraphQL\Type\Enum;

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
				'description'  => __( 'The status of the WordPress plugin.', 'wp-graphql' ),
				'values'       => self::get_enum_values(),
				'defaultValue' => 'ACTIVE',
			]
		);
	}

	/**
	 * Returns the array configuration for the GraphQL enum values.
	 *
	 * @return array
	 */
	protected static function get_enum_values() {
		$values = [
			'ACTIVE'          => [
				'value'       => 'active',
				'description' => __( 'The plugin is currently active.', 'wp-graphql' ),
			],
			'DROP_IN'         => [
				'value'       => 'dropins',
				'description' => __( 'The plugin is a drop-in plugin.', 'wp-graphql' ),

			],
			'INACTIVE'        => [
				'value'       => 'inactive',
				'description' => __( 'The plugin is currently inactive.', 'wp-graphql' ),
			],
			'MUST_USE'        => [
				'value'       => 'mustuse',
				'description' => __( 'The plugin is a must-use plugin.', 'wp-graphql' ),
			],
			'PAUSED'          => [
				'value'       => 'paused',
				'description' => __( 'The plugin is technically active but was paused while loading.' ),
			],
			'RECENTLY_ACTIVE' => [
				'value'       => 'recently_activated',
				'description' => __( 'The plugin was active recently.', 'wp-graphql' ),
			],
			'UPGRADE'         => [
				'value'       => 'upgrade',
				'description' => __( 'The plugin has an upgrade available.' ),
			],
		];

		// Multisite enums
		if ( is_multisite() ) {
			$values['NETWORK_ACTIVATED'] = [
				'value'       => 'network_activated',
				'description' => __( 'The plugin is activated on the multisite network.', 'wp-graphql' ),
			];
			$values['NETWORK_INACTIVE']  = [
				'value'       => 'network_inactive',
				'description' => __( 'The plugin is installed on the multisite network, but is currently inactive.', 'wp-graphql' ),
			];
		}

		return $values;
	}
}
