<?php

namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\MediaItemMutation;
use WPGraphQL\Utils\Utils;

class MediaItemUpdate {
	/**
	 * Registers the MediaItemUpdate mutation.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'updateMediaItem',
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
		/** @var \WP_Post_Type $post_type_object */
		$post_type_object = get_post_type_object( 'attachment' );
		return array_merge(
			MediaItemCreate::get_input_fields(),
			[
				'id' => [
					'type'        => [
						'non_null' => 'ID',
					],
					// translators: the placeholder is the name of the type of post object being updated
					'description' => sprintf( __( 'The ID of the %1$s object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
				],
			]
		);
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return MediaItemCreate::get_output_fields();
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function ( $input, AppContext $context, ResolveInfo $info ) {
			$post_type_object = get_post_type_object( 'attachment' );

			if ( empty( $post_type_object ) ) {
				return null;
			}

			// Get the database ID for the comment.
			$media_item_id = Utils::get_database_id_from_id( $input['id'] );

			/**
			 * Get the mediaItem object before deleting it
			 */
			$existing_media_item = ! empty( $media_item_id ) ? get_post( $media_item_id ) : null;

			$mutation_name = 'updateMediaItem';

			/**
			 * If there's no existing mediaItem, throw an exception
			 */
			if ( null === $existing_media_item ) {
				throw new UserError( __( 'No mediaItem with that ID could be found to update', 'wp-graphql' ) );
			}

			/**
			 * Stop now if the post isn't a mediaItem
			 */
			if ( $post_type_object->name !== $existing_media_item->post_type ) {
				// translators: The placeholder is the ID of the mediaItem being edited
				throw new UserError( sprintf( __( 'The id %1$d is not of the type mediaItem', 'wp-graphql' ), $input['id'] ) );
			}

			/**
			 * Stop now if a user isn't allowed to edit mediaItems
			 */
			if ( ! isset( $post_type_object->cap->edit_posts ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to update mediaItems', 'wp-graphql' ) );
			}

			$author_id = absint( $existing_media_item->post_author );

			/**
			 * If the mutation is setting the author to be someone other than the user making the request
			 * make sure they have permission to edit others posts
			 */
			if ( ! empty( $input['authorId'] ) ) {
				// Ensure authorId is a valid databaseId.
				$input['authorId'] = Utils::get_database_id_from_id( $input['authorId'] );
				// Use the new author for checks.
				$author_id = $input['authorId'];
			}

			/**
			 * Check to see if the existing_media_item author matches the current user,
			 * if not they need to be able to edit others posts to proceed
			 */
			if ( get_current_user_id() !== $author_id && ( ! isset( $post_type_object->cap->edit_others_posts ) || ! current_user_can( $post_type_object->cap->edit_others_posts ) ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to update mediaItems as this user.', 'wp-graphql' ) );
			}

			/**
			 * Insert the post object and get the ID
			 */
			$post_args       = MediaItemMutation::prepare_media_item( $input, $post_type_object, $mutation_name, false );
			$post_args['ID'] = $media_item_id;

			$clean_args = wp_slash( (array) $post_args );

			if ( ! is_array( $clean_args ) || empty( $clean_args ) ) {
				throw new UserError( __( 'The media item failed to update', 'wp-graphql' ) );
			}

			/**
			 * Insert the post and retrieve the ID
			 *
			 * This will not fail as long as we have an ID in $post_args
			 * Thanks to the validation above we will always have the ID
			 */
			$post_id = wp_update_post( $clean_args, true );

			if ( is_wp_error( $post_id ) ) {
				$error_message = $post_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				}

				throw new UserError( __( 'The media item failed to update but no error was provided', 'wp-graphql' ) );
			}

			/**
			 * This updates additional data not part of the posts table (postmeta, terms, other relations, etc)
			 *
			 * The input for the postObjectMutation will be passed, along with the $new_post_id for the
			 * postObject that was updated so that relations can be set, meta can be updated, etc.
			 */
			MediaItemMutation::update_additional_media_item_data( $post_id, $input, $post_type_object, $mutation_name, $context, $info );

			/**
			 * Return the payload
			 */
			return [
				'postObjectId' => $post_id,
			];
		};
	}
}
