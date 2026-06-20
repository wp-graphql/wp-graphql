<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithFeaturedImage {

	/**
	 * Resolves the database ID of the node's featured image, honoring a previewed
	 * featured image when the request carries one.
	 *
	 * When the request's preview context targets this node (`preview.id` matches the
	 * node) and the viewer can edit that post, the client-supplied
	 * `featuredImageDatabaseId` is used instead of the stored featured image. This
	 * mirrors how WordPress core resolves the previewed featured image from the
	 * `_thumbnail_id` request parameter, which it never persists to the revision.
	 *
	 * A `featuredImageDatabaseId` of 0 means the featured image was removed in the preview.
	 *
	 * @param \WPGraphQL\Model\Post $post    The post the featured image is resolved for.
	 * @param \WPGraphQL\AppContext $context The AppContext for the request.
	 */
	public static function resolve_featured_image_database_id( Post $post, AppContext $context ): ?int {
		$preview = $context->preview;

		// When the request's preview context targets this node, overlay the previewed
		// featured image while preserving the node's identity (the node keeps its published
		// databaseId; only the featured image is overlaid), mirroring how WordPress core
		// reads the previewed featured image from the `_thumbnail_id` request parameter.
		// The viewer must be authenticated and able to edit the post being previewed.
		if (
			is_array( $preview )
			&& isset( $preview['featuredImageDatabaseId'] )
			&& (int) $post->databaseId === (int) $preview['id']
			&& is_user_logged_in()
			&& current_user_can( 'edit_post', (int) $preview['id'] )
		) {
			return ! empty( $preview['featuredImageDatabaseId'] ) ? absint( $preview['featuredImageDatabaseId'] ) : null;
		}

		return ! empty( $post->featuredImageDatabaseId ) ? absint( $post->featuredImageDatabaseId ) : null;
	}

	/**
	 * Registers the NodeWithFeaturedImage Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithFeaturedImage',
			[
				'description' => static function () {
					return __( 'Content that can have a primary image attached. This image is typically used for thumbnails, social sharing, and prominent display in the presentation layer.', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node' ],
				'connections' => [
					'featuredImage' => [
						'toType'   => 'MediaItem',
						'oneToOne' => true,
						'resolve'  => static function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							$featured_image_id = self::resolve_featured_image_database_id( $post, $context );

							if ( empty( $featured_image_id ) ) {
								return null;
							}

							$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'attachment' );
							$resolver->set_query_arg( 'p', $featured_image_id );

							return $resolver->one_to_one()->get_connection();
						},
					],
				],
				'fields'      => static function () {
					return [
						'featuredImageId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'Globally unique ID of the featured image assigned to the node', 'wp-graphql' );
							},
							'resolve'     => static function ( Post $post, $args, AppContext $context ) {
								$database_id = self::resolve_featured_image_database_id( $post, $context );

								return ! empty( $database_id ) ? Relay::toGlobalId( 'post', (string) $database_id ) : null;
							},
						],
						'featuredImageDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The database identifier for the featured image node assigned to the content node', 'wp-graphql' );
							},
							'resolve'     => static function ( Post $post, $args, AppContext $context ) {
								return self::resolve_featured_image_database_id( $post, $context );
							},
						],
					];
				},
			]
		);
	}
}
