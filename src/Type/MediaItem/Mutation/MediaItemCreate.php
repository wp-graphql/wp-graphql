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

		if ( ! empty( $post_type_object->graphql_single_name ) && empty( self::$mutation[ $post_type_object->graphql_single_name] ) ) :

			/**
			 * Set the name of the mutation being performed
			 */
			$mutation_name = 'create' . ucwords( $post_type_object->graphql_single_name );

			self::$mutation[ $post_type_object->graphql_single_name ] = Relay::mutationWithClientMutationId( [

				'name' => esc_html( $mutation_name ),
				'description' => sprintf( __( 'Create %1$s objects', 'wp-graphql' ), $post_type_object->graphql_single_name ),
				'inputFields' => MediaItemMutation::input_fields( $post_type_object ),
				'outputFields' => [
					$post_type_object->graphql_single_name => [
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
					 * Stop now if a user isn't allowed to create a post
					 */
					if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
						// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
						throw new \Exception( sprintf( __( 'Sorry, you are not allowed to create %1$s', 'wp-graphql' ), $post_type_object->graphql_plural_name ) );
					}

					/**
					 * If the post being created is being assigned to another user that's not the current user, make sure
					 * the current user has permission to edit others posts for this post_type
					 */
					if ( ! empty( $input['authorId'] ) && get_current_user_id() !== $input['authorId'] && ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
						// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
						throw new \Exception( sprintf( __( 'Sorry, you are not allowed to create %1$s as this user', 'wp-graphql' ), $post_type_object->graphql_plural_name ) );
					}

					/**
					 * insert the media item object and get the ID
					 */
					$media_item_args = MediaItemMutation::prepare_media_item( $input, $post_type_object, $mutation_name );

//					var_dump( $media_item_args );

					/**
					 * Set the file name, whether it's a local file or from a URL
					 */
					$file_name = basename( $media_item_args['path'] );


					/**
					 * Check if the download_url method exists and include it if not
					 * This file also includes the wp_handle_sideload method
					 */
					if ( ! function_exists( 'download_url' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/file.php' );
					}

					/**
					 * If the file is from a local server, use wp_upload_bits before saving it to the uploads folder
					 */
					if ( 'false' === filter_var( $media_item_args['path'], FILTER_VALIDATE_URL ) ) {
						$uploaded_file = wp_upload_bits( $file_name, null, file_get_contents( $media_item_args['path'] ) );
						$uploaded_file_url = $uploaded_file['url'];
					} else {
						$uploaded_file_url = $media_item_args['path'];
					}

//					var_dump( $uploaded_file_url );

					/**
					 * URL data for the media item
					 */
					$timeout_seconds = 60;
					$temp_file = download_url( $uploaded_file_url, $timeout_seconds );

//					var_dump( $temp_file );

					/**
					 * Build the file args for side loading
					 */
					$file_data = [
						'name'     => $file_name,
						'type'     => $media_item_args['mime_type'],
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
					 * Insert the media item and retrieve the it's data
					 */
					$file = wp_handle_sideload( $file_data, $overrides );

					$attachment = [
						'post_mime_type' => $file['type'],
						'post_title'     => basename( $file['file'] ),
						'post_content'   => '',
						'post_status'    => 'inherit',
					];

					/**
					 * Insert the new media item
					 * IF there was a post parent we could associate it with the third param in wp_insert_attachment
					 */
					$attachment_id = wp_insert_attachment( $attachment, $file['file'] );

					/**
					 * Generate the metadata for the attachment, and update the database record
					 */
					if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/image.php' );
					}

					$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file['file'] );

//					$post_data = get_post( $attachment_id );
//
//					var_dump( $attachment_id );
//					var_dump( $attachment_data );
//					var_dump( $post_data );

					wp_update_attachment_metadata( $attachment_id, $attachment_data );

					// @TODO: Update post meta for media item with all other input fields
					// Alt Text post meta field _wp_attachment_image_alt

					return [
						'id' => $attachment_id,
					];

				},

			] );

		endif; // End if().

		return ! empty( self::$mutation[ $post_type_object->graphql_single_name ] ) ? self::$mutation[ $post_type_object->graphql_single_name ] : null;
	}
}
