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
				'description' => __( 'Script enqueued by the CMS', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'EnqueuedAsset' ],
				'fields'      => [
					'id'      => [
						'type'    => [
							'non_null' => 'ID',
						],
						'resolve' => static function ( $asset ) {
							return isset( $asset->handle ) ? Relay::toGlobalId( 'enqueued_script', $asset->handle ) : null;
						},
					],
					'src'     => [
						'resolve' => static function ( \_WP_Dependency $script ) {
							return ! empty( $script->src ) && is_string( $script->src ) ? $script->src : null;
						},
					],
					'version' => [
						'resolve' => static function ( \_WP_Dependency $script ) {
							global $wp_scripts;

							return ! empty( $script->ver ) && is_string( $script->ver ) ? (string) $script->ver : $wp_scripts->default_version;
						},
					],
				],
			]
		);
	}
}
