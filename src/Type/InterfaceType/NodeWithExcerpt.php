<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithExcerpt {

	/**
	 * Registers the NodeWithExcerpt Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithExcerpt',
			[
				'interfaces'  => [ 'Node' ],
				'description' => static function () {
					return __( 'A node which provides an excerpt field, which is a condensed summary of the main content. Excerpts can be manually created or automatically generated and are often used in content listings and search results.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'excerpt' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The excerpt of the post.', 'wp-graphql' );
							},
							'args'        => [
								'format' => [
									'type'        => 'PostObjectFieldFormatEnum',
									'description' => static function () {
										return __( 'Format of the field output', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, $args ) {
								if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
									// @codingStandardsIgnoreLine.
									return $source->excerptRaw;
								}

								// @codingStandardsIgnoreLine.
								return $source->excerptRendered;
							},
						],
					];
				},
			]
		);
	}
}
