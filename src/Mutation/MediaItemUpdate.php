<?php

namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\MediaItemMutation;

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

			$mutation_name = 'updateMediaItem';

			$id_parts            = ! empty( $input['id'] ) ? Relay::fromGlobalId( $input['id'] ) : null;
			$existing_media_item = isset( $id_parts['id'] ) && absint( $id_parts['id'] ) ? get_post( absint( $id_parts['id'] ) ) : null;

			/**
			 * If there's no existing mediaItem, throw an exception
			 */
			if ( null === $existing_media_item ) {
				throw new UserError( __( 'No mediaItem with that ID could be found to update', 'wp-graphql' ) );
			} else {
				$author_id = $existing_media_item->post_author;
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

			/**
			 * If the mutation is setting the author to be someone other than the user making the request
			 * make sure they have permission to edit others posts
			 */
			if ( ! empty( $input['authorId'] ) ) {
				$author_id_parts = Relay::fromGlobalId( $input['authorId'] );
				$author_id       = $author_id_parts['id'];
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
			$post_args                = MediaItemMutation::prepare_media_item( $input, $post_type_object, $mutation_name, false );
			$post_args['ID']          = isset( $id_parts['id'] ) ? absint( $id_parts['id'] ) : null;
			$post_args['post_author'] = $author_id;

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
				throw new UserError( __( 'The media item failed to update', 'wp-graphql' ) );
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
