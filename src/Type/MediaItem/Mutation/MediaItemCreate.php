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
				'inputFields' => self::input_fields( $post_type_object ),
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

					/**
					 * URL data for the media item
					 */
					$timeout_seconds = 60;
					$temp_file = download_url( $uploaded_file_url, $timeout_seconds );

					update_option( 'hd_temp_file', $temp_file );

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
					 * Insert the media item and retrieve it's data
					 */
					$file = wp_handle_sideload( $file_data, $overrides );

					/**
					 * Build out the attachment (new media item)
					 */
					$attachment = self::prepare_media_item_args( $file, $media_item_args );

					/**
					 * Get the post parent and if it's not set, leave it empty
					 */
					$attachment_parent_id = ( ! empty( $media_item_args['post_parent'] ) ? $media_item_args['post_parent'] : false );

					/**
					 * Insert the attachment (new media item)
					 */
					$attachment_id = wp_insert_attachment( $attachment, $file['file'], $attachment_parent_id );

					/**
					 * Generate the metadata for the attachment, and update the database record
					 */
					if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/image.php' );
					}

					$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file['file'] );

					wp_update_attachment_metadata( $attachment_id, $attachment_data );

					// Update alt text post meta for media item
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $media_item_args['alt_text'] );

					return [
						'id' => $attachment_id,
					];

				},

			] );

		endif; // End if().

		return ! empty( self::$mutation[ $post_type_object->graphql_single_name ] ) ? self::$mutation[ $post_type_object->graphql_single_name ] : null;
	}

	/**
	 * Prepares all of the arguments for the media item insertion (wp_insert_attachment method)
	 *
	 * @param array $file
	 * @param array $media_item_args
	 *
	 * @return array $insert_attachment_args
	 */
	private static function prepare_media_item_args( $file, $media_item_args ) {
		if ( ! empty( $media_item_args['post_date'] ) ) {
			$insert_attachment_args['post_date'] = date("Y-m-d H:i:s", $media_item_args['post_date'] );
		}

		if ( ! empty( $media_item_args['post_date_gmt'] ) ) {
			$insert_attachment_args['post_date_gmt'] = date("Y-m-d H:i:s", $media_item_args['post_date_gmt'] );
		}

		if ( ! empty( $media_item_args['post_name'] ) ) {
			$insert_attachment_args['post_name'] = $media_item_args['post_name'];
		}

		$insert_attachment_args['post_status'] = ( ! empty( $media_item_args['post_status'] ) ? $media_item_args['post_status'] : 'inherit' );

		$insert_attachment_args['post_title'] = ( ! empty( $media_item_args['post_title']  ) ? $media_item_args['post_title'] : basename( $file['file'] ) );

		$insert_attachment_args['post_author'] = $media_item_args['post_author'];

		if ( ! empty( $media_item_args['comment_status'] ) ) {
			$insert_attachment_args['comment_status'] = $media_item_args['comment_status'];
		}

		if ( ! empty( $media_item_args['ping_status'] ) ) {
			$insert_attachment_args['ping_status'] = $media_item_args['ping_status'];
		}

		$insert_attachment_args['post_excerpt'] = ( ! empty( $media_item_args['post_excerpt'] ) ? $media_item_args['post_excerpt'] : '' );

		$insert_attachment_args['post_content'] = ( ! empty( $media_item_args['post_content'] ) ? $media_item_args['post_content'] : '' );

		$insert_attachment_args['post_mime_type'] = ( ! empty( $media_item_args['post_mime_type'] ) ? $media_item_args['post_mime_type'] : $file['type'] );

		return $insert_attachment_args;
	}

	/**
	 * Add the filePath as a nonNull field for create mutations
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
					'description' => __( 'The file name of the media item', 'wp-graphql' ),
				],
			],
			MediaItemMutation::input_fields( $post_type_object )
		);

	}
}
