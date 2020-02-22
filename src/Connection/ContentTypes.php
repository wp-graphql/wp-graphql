<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;

class ContentTypes {

	public static function register_connections() {

		/**
		 * Registers a connection from the RootQuery to the PostType Type
		 */
		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'ContentType',
				'fromFieldName' => 'contentTypes',
				'resolve'       => function( $source, $args, $context, $info ) {
					return ContentTypeConnectionResolver::resolve( $source, $args, $context, $info );
				},
			]
		);

		$post_types = get_post_types( [ 'show_in_graphql' => true ], 'OBJECT' );

		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				register_graphql_connection(
					[
						'fromType'      => $post_type->graphql_single_name,
						'toType'        => 'ContentType',
						'fromFieldName' => 'contentType',
						'resolve'       => function( $source, $args, $context, $info ) {
							return ContentTypeConnectionResolver::resolve( $source, $args, $context, $info );
						},
						'oneToOne'      => true,
					]
				);
			}
		}

	}

}
