<?php
namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Model\Post;
use WPGraphQL\Utils\Utils;

class MediaItemDelete {
	/**
	 * Registers the MediaItemDelete mutation.
	 *
	 * @return void
	 * @throws \Exception
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
					/** @var \WPGraphQL\Model\Post $deleted */
					$deleted = $payload['mediaItemObject'];

					return ! empty( $deleted->ID ) ? $deleted : null;
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
			// Get the database ID for the comment.
			$media_item_id = Utils::get_database_id_from_id( $input['id'] );

			/**
			 * Get the mediaItem object before deleting it
			 */
			$existing_media_item = ! empty( $media_item_id ) ? get_post( $media_item_id ) : null;

			// If there's no existing mediaItem, throw an exception.
			if ( null === $existing_media_item ) {
				throw new UserError( __( 'No mediaItem with that ID could be found to delete', 'wp-graphql' ) );
			}

			// Stop now if the post isn't a mediaItem.
			if ( 'attachment' !== $existing_media_item->post_type ) {
				throw new UserError( sprintf( __( 'Sorry, the item you are trying to delete is a %1%s, not a mediaItem', 'wp-graphql' ), $existing_media_item->post_type ) );
			}

			/**
			 * Stop now if a user isn't allowed to delete a mediaItem
			 */
			$post_type_object = get_post_type_object( 'attachment' );

			if ( ! isset( $post_type_object->cap->delete_post ) || ! current_user_can( $post_type_object->cap->delete_post, $media_item_id ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to delete mediaItems', 'wp-graphql' ) );
			}

			/**
			 * Check if we should force delete or not
			 */
			$force_delete = ! empty( $input['forceDelete'] ) && true === $input['forceDelete'];

			/**
			 * If the mediaItem is already in the trash, and the forceDelete input was not passed,
			 * don't remove from the trash
			 */
			if ( 'trash' === $existing_media_item->post_status && true !== $force_delete ) {
				// Translators: the first placeholder is the post_type of the object being deleted and the second placeholder is the unique ID of that object
				throw new UserError( sprintf( __( 'The mediaItem with id %1$s is already in the trash. To remove from the trash, use the forceDelete input', 'wp-graphql' ), $input['id'] ) );
			}

			/**
			 * Delete the mediaItem. This will not throw false thanks to
			 * all of the above validation
			 */
			$deleted = wp_delete_attachment( (int) $media_item_id, $force_delete );

			/**
			 * If the post was moved to the trash, spoof the object's status before returning it
			 */
			$existing_media_item->post_status = ( false !== $deleted && true !== $force_delete ) ? 'trash' : $existing_media_item->post_status;

			$media_item_before_delete = new Post( $existing_media_item );

			/**
			 * Return the deletedId and the mediaItem before it was deleted
			 */
			return [
				'mediaItemObject' => $media_item_before_delete,
			];
		};
	}
}
