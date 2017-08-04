<?php

namespace WPGraphQL\Type\MediaItem\Mutation;

use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class MediaItemCreate
 *
 * @package WPGraphQL\Type\MediaItem\Mutation
 */
class MediaItemCreate {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array mutation
	 */
	private static $mutation = [];

	/**
	 * Defines the create mutation for MediaItems
	 *
	 * @var \WP_Post_Type $post_type_object
	 *
	 * @return array|mixed
	 */
	public static function mutate( \WP_Post_Type $post_type_object ) {

		/**
		 * Set the name of the mutation being performed
		 */
		$mutation_name = 'createMediaItem';

		self::$mutation['mediaItem'] = Relay::mutationWithClientMutationId( [
			'name' => esc_html( $mutation_name ),
			'description' => __( 'Create mediaItems', 'wp-graphql' ),
			'inputFields' => self::input_fields( $post_type_object ),
			'outputFields' => [
				'mediaItem' => [
					'type' => Types::post_object( $post_type_object->name ),
					'resolve' => function( $payload ) {
						return get_post( $payload['id'] );
					},
				],
			],
			'mutateAndGetPayload' => function( $input ) use ( $post_type_object, $mutation_name ) {

				/**
				 * Throw an exception if there's no input
				 */
				if ( ( empty( $post_type_object->name ) ) || ( empty( $input ) || ! is_array( $input ) ) ) {
					throw new \Exception( __( 'Mutation not processed. There was no input for the mutation or the post_type_object was invalid', 'wp-graphql' ) );
				}

				/**
				 * Stop now if a user isn't allowed to create a mediaItem
				 */
				if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
					throw new \Exception( __( 'Sorry, you are not allowed to create mediaItems', 'wp-graphql' ) );
				}

				/**
				 * If the mediaItem being created is being assigned to another user that's not the current user, make sure
				 * the current user has permission to edit others mediaItems
				 */
				if ( ! empty( $input['authorId'] ) && get_current_user_id() !== $input['authorId'] && ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
					throw new \Exception( __( 'Sorry, you are not allowed to create mediaItems as this user', 'wp-graphql' ) );
				}

				/**
				 * Set the file name, whether it's a local file or from a URL
				 */
				$file_name = basename( $input['filePath'] );

				/**
				 * Check if the download_url method exists and include it if not
				 * This file also includes the wp_handle_sideload method
				 */
				if ( ! function_exists( 'download_url' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				}

				/**
				 * If the mediaItem file is from a local server, use wp_upload_bits before saving it to the uploads folder
				 */
				if ( 'false' === filter_var( $input['filePath'], FILTER_VALIDATE_URL ) ) {
					$uploaded_file = wp_upload_bits( $file_name, null, file_get_contents( $input['filePath'] ) );
					$uploaded_file_url = $uploaded_file['url'];
				} else {
					$uploaded_file_url = $input['filePath'];
				}

				/**
				 * URL data for the mediaItem, timeout value is the default, see:
				 * https://developer.wordpress.org/reference/functions/download_url/
				 */
				$timeout_seconds = 300;
				$temp_file = download_url( $uploaded_file_url, $timeout_seconds );

				/**
				 * Build the file data for side loading
				 */
				$file_data = [
					'name'     => $file_name,
					'type'     => $input['fileType'],
					'tmp_name' => $temp_file,
					'error'    => 0,
					'size'     => filesize( $temp_file ),
				];

				/**
				 * Tells WordPress to not look for the POST form fields that would normally be present as
				 * we downloaded the file from a remote server, so there will be no form fields
				 * The default is true
				 */
				$overrides = [
					'test_form' => false,
				];

				/**
				 * Insert the mediaItem and retrieve it's data
				 */
				$file = wp_handle_sideload( $file_data, $overrides );

				/**
				 * Insert the mediIitem object and get the ID
				 */
				$media_item_args = MediaItemMutation::prepare_media_item( $input, $post_type_object, $mutation_name, $file );

				/**
				 * Get the post parent and if it's not set, set it to false
				 */
				$attachment_parent_id = ( ! empty( $media_item_args['post_parent'] ) ? $media_item_args['post_parent'] : false );

				/**
				 * Insert the mediaItem
				 */
				$attachment_id = wp_insert_attachment( $media_item_args, $file['file'], $attachment_parent_id );

				/**
				 * Check if the wp_generate_attachment_metadata method exists and include it if not
				 */
				if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
				}

				/**
				 * Generate and update the mediaItem's metadata
				 */
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file['file'] );
				wp_update_attachment_metadata( $attachment_id, $attachment_data );

				/**
				 * Update alt text postmeta for mediaItem
				 */
				MediaItemMutation::update_additional_media_item_data( $attachment_id, $input, $post_type_object, $mutation_name );

				return [
					'id' => $attachment_id,
				];

			},

		] );

		return ! empty( self::$mutation['mediaItem'] ) ? self::$mutation['mediaItem'] : null;
	}

	/**
	 * Add the filePath as a nonNull field for create mutations as its required
	 * to create a media item
	 *
	 * @param \WP_Post_Type $post_type_object
	 *
	 * @return array
	 */
	private static function input_fields( $post_type_object ) {

		/**
		 * Update mutations require an filePath to be passed
		 */
		return array_merge(
			[
				'filePath'      => [
					'type'        => Types::non_null( Types::string() ),
					'description' => __( 'The URL or file path to the mediaItem', 'wp-graphql' ),
				],
			],
			MediaItemMutation::input_fields( $post_type_object )
		);

	}
}
