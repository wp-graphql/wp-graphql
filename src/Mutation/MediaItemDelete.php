<?php
namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Model\Post;

class MediaItemDelete {
	/**
	 * Registers the MediaItemDelete mutation.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'deleteMediaItem',
			[
				'inputFields'         => self::get_input_fields(),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array
	 */
	public static function get_input_fields() {
		return [
			'id'          => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The ID of the mediaItem to delete', 'wp-graphql' ),
			],
			'forceDelete' => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the mediaItem should be force deleted instead of being moved to the trash', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return [
			'deletedId' => [
				'type'        => 'ID',
				'description' => __( 'The ID of the deleted mediaItem', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$deleted = (object) $payload['mediaItemObject'];

					return ! empty( $deleted->ID ) ? Relay::toGlobalId( 'post', $deleted->ID ) : null;
				},
			],
			'mediaItem' => [
				'type'        => 'MediaItem',
				'description' => __( 'The mediaItem before it was deleted', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$deleted = (object) $payload['mediaItemObject'];

					return ! empty( $deleted ) ? $deleted : null;
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function ( $input ) {
			$post_type_object = get_post_type_object( 'attachment' );

			/**
			 * Get the ID from the global ID
			 */
			$id_parts            = Relay::fromGlobalId( $input['id'] );
			$existing_media_item = get_post( absint( $id_parts['id'] ) );

			/**
			 * If there's no existing mediaItem, throw an exception
			 */
			if ( empty( $existing_media_item ) ) {
				throw new UserError( __( 'No mediaItem could be found to delete', 'wp-graphql' ) );
			}

			/**
			 * Stop now if a user isn't allowed to delete a mediaItem
			 */
			if ( ! isset( $post_type_object->cap->delete_post ) || ! current_user_can( $post_type_object->cap->delete_post, absint( $id_parts['id'] ) ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to delete mediaItems', 'wp-graphql' ) );
			}

			/**
			 * Check if we should force delete or not
			 */
			$force_delete = ! empty( $input['forceDelete'] ) && true === $input['forceDelete'];

			/**
			 * Get the mediaItem object before deleting it
			 */
			$media_item_before_delete = get_post( absint( $id_parts['id'] ) );
			$media_item_before_delete = isset( $media_item_before_delete->ID ) && absint( $media_item_before_delete->ID ) ? new Post( $media_item_before_delete ) : $media_item_before_delete;

			if ( empty( $media_item_before_delete ) ) {
				throw new UserError( __( 'The Media Item could not be deleted', 'wp-graphql' ) );
			}

			/**
			 * If the mediaItem isn't of the attachment post type, throw an error
			 */
			if ( 'attachment' !== $media_item_before_delete->post_type ) {
				throw new UserError( sprintf( __( 'Sorry, the item you are trying to delete is a %1%s, not a mediaItem', 'wp-graphql' ), $media_item_before_delete->post_type ) );
			}

			/**
			 * If the mediaItem is already in the trash, and the forceDelete input was not passed,
			 * don't remove from the trash
			 */
			if ( 'trash' === $media_item_before_delete->post_status ) {
				if ( true !== $force_delete ) {
					// Translators: the first placeholder is the post_type of the object being deleted and the second placeholder is the unique ID of that object
					throw new UserError( sprintf( __( 'The mediaItem with id %1$s is already in the trash. To remove from the trash, use the forceDelete input', 'wp-graphql' ), $input['id'] ) );
				}
			}

			/**
			 * Delete the mediaItem. This will not throw false thanks to
			 * all of the above validation
			 */
			$deleted = wp_delete_attachment( $id_parts['id'], $force_delete );

			/**
			 * If the post was moved to the trash, spoof the object's status before returning it
			 */
			$media_item_before_delete->post_status = ( false !== $deleted && true !== $force_delete ) ? 'trash' : $media_item_before_delete->post_status;

			/**
			 * Return the deletedId and the mediaItem before it was deleted
			 */
			return [
				'mediaItemObject' => $media_item_before_delete,
			];

		};
	}
}
