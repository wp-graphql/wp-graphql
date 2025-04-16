<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class EnqueuedAsset
 *
 * @package WPGraphQL\Type
 */
class EnqueuedAsset {

	/**
	 * Register the Enqueued Script Type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL Type Registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'EnqueuedAsset',
			[
				'description' => static function () {
					return __( 'A script or stylesheet resource that should be loaded by the client. Contains information about the resource\'s location, dependencies, and loading behavior.', 'wp-graphql' );
				},
				'resolveType' => static function ( $asset ) use ( $type_registry ) {

					/**
					 * The resolveType callback is used at runtime to determine what Type an object
					 * implementing the EnqueuedAsset Interface should be resolved as.
					 *
					 * You can filter this centrally using the "graphql_wp_interface_type_config" filter
					 * to override if you need something other than a Post object to be resolved via the
					 * $post->post_type attribute.
					 */
					$type = null;

					if ( isset( $asset['type'] ) ) {
						$type = $type_registry->get_type( $asset['type'] );
					}

					return ! empty( $type ) ? $type : null;
				},
				'fields'      => static function () {
					return [
						'args'         => [
							'type'              => 'Boolean',
							'description'       => static function () {
								return __( 'Deprecated', 'wp-graphql' );
							},
							'deprecationReason' => static function () {
								return __( 'Use `EnqueuedAsset.media` instead.', 'wp-graphql' );
							},
						],
						'after'        => [
							'type'        => [ 'list_of' => 'String' ],
							'description' => static function () {
								return __( 'The inline code to be run after the asset is loaded.', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $asset ) {
								if ( empty( $asset->extra['after'] ) ) {
									return null;
								}

								$after_scripts = array_map(
									static function ( $after ) {
										return is_string( $after ) ? $after : null;
									},
									$asset->extra['after']
								);

								return array_filter( $after_scripts );
							},
						],
						'before'       => [
							'type'        => [ 'list_of' => 'String' ],
							'description' => static function () {
								return __( 'The inline code to be run before the asset is loaded.', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $asset ) {
								if ( empty( $asset->extra['before'] ) ) {
									return null;
								}

								$before_scripts = array_map(
									static function ( $before ) {
										return is_string( $before ) ? $before : null;
									},
									$asset->extra['before']
								);

								return array_filter( $before_scripts );
							},
						],
						'conditional'  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The HTML conditional comment for the enqueued asset. E.g. IE 6, lte IE 7, etc', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $asset ) {
								if ( ! isset( $asset->extra['conditional'] ) || ! is_string( $asset->extra['conditional'] ) ) {
									return null;
								}

								return $asset->extra['conditional'];
							},
						],
						'dependencies' => [
							'type'        => [ 'list_of' => 'EnqueuedAsset' ],
							'description' => static function () {
								return __( 'Dependencies needed to use this asset', 'wp-graphql' );
							},
						],
						'id'           => [
							'type'        => [ 'non_null' => 'ID' ],
							'description' => static function () {
								return __( 'The ID of the enqueued asset', 'wp-graphql' );
							},
						],
						'handle'       => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The handle of the enqueued asset', 'wp-graphql' );
							},
						],
						'src'          => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The source of the asset', 'wp-graphql' );
							},
							'resolve'     => static function ( \_WP_Dependency $stylesheet ) {
								return ! empty( $stylesheet->src ) && is_string( $stylesheet->src ) ? $stylesheet->src : null;
							},
						],
						'version'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The version of the enqueued asset', 'wp-graphql' );
							},
						],
						'extra'        => [
							'type'              => 'String',
							'description'       => static function () {
								return __( 'Extra information needed for the script', 'wp-graphql' );
							},
							'deprecationReason' => static function () {
								return __( 'Use `EnqueuedScript.extraData` instead.', 'wp-graphql' );
							},
							'resolve'           => static function ( $asset ) {
								return isset( $asset->extra['data'] ) ? $asset->extra['data'] : null;
							},
						],
						'group'        => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The loading group to which this asset belongs.', 'wp-graphql' );
							},
							'resolve'     => static function ( $asset ) {
								return isset( $asset->extra['group'] ) ? (int) $asset->extra['group'] : null;
							},
						],
					];
				},
			]
		);
	}
}
