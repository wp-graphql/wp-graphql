<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithTrackbacks {

	/**
	 * Registers the NodeWithTrackbacks Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithTrackbacks',
			[
				'interfaces'  => [ 'Node' ],
				'description' => static function () {
					return __( 'Content that supports cross-site notifications when linked to by other sites. Includes fields for pingback status and linked URLs.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'toPing'     => [
							'type'        => [ 'list_of' => 'String' ],
							'description' => static function () {
								return __( 'URLs queued to be pinged.', 'wp-graphql' );
							},
						],
						'pinged'     => [
							'type'        => [ 'list_of' => 'String' ],
							'description' => static function () {
								return __( 'URLs that have been pinged.', 'wp-graphql' );
							},
						],
						'pingStatus' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Whether the pings are open or closed for this particular post.', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
