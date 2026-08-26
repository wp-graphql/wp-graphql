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
	 * Derives the featured image database ID for a previewed node from the request's
	 * preview context.
	 *
	 * Shared by the `previewResolve` callbacks below, which only run for an authorized
	 * preview targeting this node (see Preview::resolve_preview_field()). Mirrors how
	 * WordPress core resolves the previewed featured image from the `_thumbnail_id`
	 * request parameter, which it never persists to the revision:
	 *
	 * - An absent `featuredImageDatabaseId` means no override: the stored featured
	 *   image is used.
	 * - `0` means the featured image was removed in the preview.
	 * - A value that is not an existing attachment resolves as no image, so a junk id
	 *   is never echoed back as if it were a real featured image.
	 *
	 * @param \WPGraphQL\Model\Post $post    The post the featured image is resolved for.
	 * @param array<string,mixed>   $preview The request's preview context.
	 */
	public static function get_previewed_featured_image_database_id( Post $post, array $preview ): ?int {
		if ( ! isset( $preview['featuredImageDatabaseId'] ) ) {
			return ! empty( $post->featuredImageDatabaseId ) ? absint( $post->featuredImageDatabaseId ) : null;
		}

		$featured_image_id = absint( $preview['featuredImageDatabaseId'] );

		// 0 means the featured image was removed in the preview.
		if ( empty( $featured_image_id ) ) {
			return null;
		}

		$attachment = get_post( $featured_image_id );

		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		return $featured_image_id;
	}

	/**
	 * Resolves the one-to-one featured image connection for the given attachment ID.
	 *
	 * @param \WPGraphQL\Model\Post                $post              The post the featured image is resolved for.
	 * @param array<string,mixed>                  $args              The field args.
	 * @param \WPGraphQL\AppContext                $context           The AppContext for the request.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info              The ResolveInfo for the field.
	 * @param ?int                                 $featured_image_id The attachment ID to resolve, or null for no image.
	 *
	 * @return \GraphQL\Deferred|null
	 */
	private static function resolve_featured_image_connection( Post $post, array $args, AppContext $context, ResolveInfo $info, ?int $featured_image_id ) {
		if ( empty( $featured_image_id ) ) {
			return null;
		}

		$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'attachment' );
		$resolver->set_query_arg( 'p', $featured_image_id );

		return $resolver->one_to_one()->get_connection();
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
						'toType'         => 'MediaItem',
						'oneToOne'       => true,
						'resolve'        => static function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							$featured_image_id = ! empty( $post->featuredImageDatabaseId ) ? absint( $post->featuredImageDatabaseId ) : null;

							return self::resolve_featured_image_connection( $post, $args, $context, $info, $featured_image_id );
						},
						// Core's own consumer of the previewResolve field-config API: WordPress
						// passes the previewed featured image as a request param and never stores
						// it on the revision, so the previewed value must be derived from the
						// request's preview context rather than the revision.
						'previewResolve' => static function ( Post $post, $args, AppContext $context, ResolveInfo $info, array $preview ) {
							return self::resolve_featured_image_connection( $post, $args, $context, $info, self::get_previewed_featured_image_database_id( $post, $preview ) );
						},
					],
				],
				'fields'      => static function () {
					return [
						'featuredImageId'         => [
							'type'           => 'ID',
							'description'    => static function () {
								return __( 'Globally unique ID of the featured image assigned to the node', 'wp-graphql' );
							},
							'previewResolve' => static function ( Post $post, $args, AppContext $context, ResolveInfo $info, array $preview ) {
								$database_id = self::get_previewed_featured_image_database_id( $post, $preview );

								return ! empty( $database_id ) ? Relay::toGlobalId( 'post', (string) $database_id ) : null;
							},
						],
						'featuredImageDatabaseId' => [
							'type'           => 'Int',
							'description'    => static function () {
								return __( 'The database identifier for the featured image node assigned to the content node', 'wp-graphql' );
							},
							'previewResolve' => static function ( Post $post, $args, AppContext $context, ResolveInfo $info, array $preview ) {
								return self::get_previewed_featured_image_database_id( $post, $preview );
							},
						],
					];
				},
			]
		);
	}
}
