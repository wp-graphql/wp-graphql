<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

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
				'description' => __( 'Script enqueued by the CMS', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'EnqueuedAsset' ],
				'fields'      => [
					'id'           => [
						'type'        => [ 'non_null' => 'ID' ],
						'description' => __( 'The global ID of the enqueued script', 'wp-graphql' ),
						'resolve'     => static function ( $asset ) {
							return isset( $asset->handle ) ? Relay::toGlobalId( 'enqueued_script', $asset->handle ) : null;
						},
					],
					'dependencies' => [
						'type'        => [ 'list_of' => 'EnqueuedScript' ],
						'description' => __( 'Handles of dependencies needed to use this asset', 'wp-graphql' ),
						'resolve'     => static function ( $asset ) {
							return ! empty( $asset->deps ) ? DataSource::resolve_enqueued_assets( 'script', $asset->deps ) : [];
						},
					],
					'extraData'    => [
						'type'        => 'String',
						'description' => __( 'Extra data supplied to the enqueued script', 'wp-graphql' ),
						'resolve'     => static function ( \_WP_Dependency $script ) {
							if ( ! isset( $script->extra['data'] ) || ! is_string( $script->extra['data'] ) ) {
								return null;
							}

							return $script->extra['data'];
						},
					],
					'strategy'     => [
						'type'        => 'ScriptLoadingStrategyEnum',
						'description' => __( 'The loading strategy to use on the script tag', 'wp-graphql' ),
						'resolve'     => static function ( \_WP_Dependency $script ) {
							if ( ! isset( $script->extra['strategy'] ) || ! is_string( $script->extra['strategy'] ) ) {
								return null;
							}

							return $script->extra['strategy'];
						},
					],
					'location'     => [
						'type'        => 'ScriptLoadingGroupEnum',
						'description' => __( 'The location where this script should be loaded', 'wp-graphql' ),
						'resolve'     => static function ( \_WP_Dependency $script ) {
							if ( isset( $script->args ) && 1 === $script->args ) {
                                return 1;
                            } elseif ( ! isset( $script->extra['group'] ) ) {
                                return 0;
                            }
                            // graphql_debug([$script->handle => $script]);
                            return absint( $script->extra['group'] );
						},
					],
					'version'      => [
						'description' => __( 'The version of the enqueued script', 'wp-graphql' ),
						'resolve'     => static function ( \_WP_Dependency $script ) {
							/** @var \WP_Scripts $wp_scripts */
							global $wp_scripts;

							return ! empty( $script->ver ) && is_string( $script->ver ) ? $script->ver : $wp_scripts->default_version;
						},
					],
				],
			]
		);
	}
}
