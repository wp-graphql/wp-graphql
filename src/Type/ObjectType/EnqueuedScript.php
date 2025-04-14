<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQLRelay\Relay;

/**
 * Class EnqueuedScript
 *
 * @package WPGraphQL\Type\Object
 */
class EnqueuedScript {

	/**
	 * Register the EnqueuedScript Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'EnqueuedScript',
			[
				'description' => static function () {
					return __( 'Script enqueued by the CMS', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node', 'EnqueuedAsset' ],
				'fields'      => static function () {
					return [
						'id'            => [
							'type'        => [ 'non_null' => 'ID' ],
							'description' => static function () {
								return __( 'The global ID of the enqueued script', 'wp-graphql' );
							},
							'resolve'     => static function ( $asset ) {
								return isset( $asset->handle ) ? Relay::toGlobalId( 'enqueued_script', $asset->handle ) : null;
							},
						],
						'dependencies'  => [
							'type'        => [ 'list_of' => 'EnqueuedScript' ],
							'description' => static function () {
								return __( 'Dependencies needed to use this asset', 'wp-graphql' );
							},
						],
						'extraData'     => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Extra data supplied to the enqueued script', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $script ) {
								if ( ! isset( $script->extra['data'] ) || ! is_string( $script->extra['data'] ) ) {
									return null;
								}

								return $script->extra['data'];
							},
						],
						'strategy'      => [
							'type'        => 'ScriptLoadingStrategyEnum',
							'description' => static function () {
								return __( 'The loading strategy to use on the script tag', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $script ) {
								if ( ! isset( $script->extra['strategy'] ) || ! is_string( $script->extra['strategy'] ) ) {
									return null;
								}

								return $script->extra['strategy'];
							},
						],
						'groupLocation' => [
							'type'        => 'ScriptLoadingGroupLocationEnum',
							'description' => static function () {
								return __( 'The location where this script should be loaded', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $script ) {
								return isset( $script->extra['group'] ) ? (int) $script->extra['group'] : 0;
							},
						],
						'version'       => [
							'description' => static function () {
								return __( 'The version of the enqueued script', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $script ) {
								/** @var \WP_Scripts $wp_scripts */
								global $wp_scripts;

								return ! empty( $script->ver ) && is_string( $script->ver ) ? $script->ver : $wp_scripts->default_version;
							},
						],
					];
				},
			]
		);
	}
}
