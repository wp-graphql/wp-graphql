<?php

namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Data\MediaItemMutation;

class MediaItemCreate {
	/**
	 * Registers the MediaItemCreate mutation.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'createMediaItem',
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
			'altText'       => [
				'type'        => 'String',
				'description' => __( 'Alternative text to display when mediaItem is not displayed', 'wp-graphql' ),
			],
			'authorId'      => [
				'type'        => 'Id',
				'description' => __( 'The userId to assign as the author of the mediaItem', 'wp-graphql' ),
			],
			'caption'       => [
				'type'        => 'String',
				'description' => __( 'The caption for the mediaItem', 'wp-graphql' ),
			],
			'commentStatus' => [
				'type'        => 'String',
				'description' => __( 'The comment status for the mediaItem', 'wp-graphql' ),
			],
			'date'          => [
				'type'        => 'String',
				'description' => __( 'The date of the mediaItem', 'wp-graphql' ),
			],
			'dateGmt'       => [
				'type'        => 'String',
				'description' => __( 'The date (in GMT zone) of the mediaItem', 'wp-graphql' ),
			],
			'description'   => [
				'type'        => 'String',
				'description' => __( 'Description of the mediaItem', 'wp-graphql' ),
			],
			'filePath'      => [
				'type'        => 'String',
				'description' => __( 'The file name of the mediaItem', 'wp-graphql' ),
			],
			'fileType'      => [
				'type'        => 'MimeTypeEnum',
				'description' => __( 'The file type of the mediaItem', 'wp-graphql' ),
			],
			'slug'          => [
				'type'        => 'String',
				'description' => __( 'The slug of the mediaItem', 'wp-graphql' ),
			],
			'status'        => [
				'type'        => 'MediaItemStatusEnum',
				'description' => __( 'The status of the mediaItem', 'wp-graphql' ),
			],
			'title'         => [
				'type'        => 'String',
				'description' => __( 'The title of the mediaItem', 'wp-graphql' ),
			],
			'pingStatus'    => [
				'type'        => 'String',
				'description' => __( 'The ping status for the mediaItem', 'wp-graphql' ),
			],
			'parentId'      => [
				'type'        => 'Id',
				'description' => __( 'The WordPress post ID or the graphQL postId of the parent object', 'wp-graphql' ),
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
			'mediaItem' => [
				'type'    => 'MediaItem',
				'resolve' => function ( $payload, $args, AppContext $context ) {
					if ( empty( $payload['postObjectId'] ) || ! absint( $payload['postObjectId'] ) ) {
						return null;
					}
					return DataSource::resolve_post_object( $payload['postObjectId'], $context );
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
		return function ( $input, AppContext $context, ResolveInfo $info ) {
			/**
			 * Stop now if a user isn't allowed to upload a mediaItem
			 */
			if ( ! current_user_can( 'upload_files' ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to upload mediaItems', 'wp-graphql' ) );
			}

			/**
			 * Set the file name, whether it's a local file or from a URL.
			 * Then set the url for the uploaded file
			 */
			$file_name         = basename( $input['filePath'] );
			$uploaded_file_url = $input['filePath'];

			/**
			 * Require the file.php file from wp-admin. This file includes the
			 * download_url and wp_handle_sideload methods
			 */
			require_once ABSPATH . 'wp-admin/includes/file.php';

			$file_contents = file_get_contents( $input['filePath'] );

			/**
			 * If the mediaItem file is from a local server, use wp_upload_bits before saving it to the uploads folder
			 */
			if ( 'file' === wp_parse_url( $input['filePath'], PHP_URL_SCHEME ) && ! empty( $file_contents ) ) {
				$uploaded_file     = wp_upload_bits( $file_name, null, $file_contents );
				$uploaded_file_url = ( empty( $uploaded_file['error'] ) ? $uploaded_file['url'] : null );
			}

			/**
			 * URL data for the mediaItem, timeout value is the default, see:
			 * https://developer.wordpress.org/reference/functions/download_url/
			 */
			$timeout_seconds = 300;
			$temp_file       = download_url( $uploaded_file_url, $timeout_seconds );

			/**
			 * Handle the error from download_url if it occurs
			 */
			if ( is_wp_error( $temp_file ) ) {
				throw new UserError( __( 'Sorry, the URL for this file is invalid, it must be a valid URL', 'wp-graphql' ) );
			}

			/**
			 * Build the file data for side loading
			 */
			$file_data = [
				'name'     => $file_name,
				'type'     => ! empty( $input['fileType'] ) ? $input['fileType'] : wp_check_filetype( $temp_file ),
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
			 * Handle the error from wp_handle_sideload if it occurs
			 */
			if ( ! empty( $file['error'] ) ) {
				throw new UserError( __( 'Sorry, the URL for this file is invalid, it must be a path to the mediaItem file', 'wp-graphql' ) );
			}

			$post_type_object = get_post_type_object( 'attachment' );

			if ( empty( $post_type_object ) ) {
				return null;
			}

			/**
			 * Insert the mediaItem object and get the ID
			 */
			$media_item_args = MediaItemMutation::prepare_media_item( $input, $post_type_object, 'createMediaItem', $file );

			/**
			 * Get the post parent and if it's not set, set it to 0
			 */
			$attachment_parent_id = ! empty( $media_item_args['post_parent'] ) ? absint( $media_item_args['post_parent'] ) : 0;

			/**
			 * Stop now if a user isn't allowed to edit the parent post
			 */
			$parent = get_post( $attachment_parent_id );

			if ( null !== $parent ) {
				$post_parent_type = get_post_type_object( $parent->post_type );

				if ( empty( $post_parent_type ) ) {
					throw new UserError( __( 'The parent of the Media Item is of an invalid type', 'wp-graphql' ) );
				}

				if ( 'attachment' !== $post_parent_type->name && ( ! isset( $post_parent_type->cap->edit_post ) || ! current_user_can( $post_parent_type->cap->edit_post, $attachment_parent_id ) ) ) {
					throw new UserError( __( 'Sorry, you are not allowed to upload mediaItems assigned to this parent node', 'wp-graphql' ) );
				}
			}

			$post_type_object = get_post_type_object( 'attachment' );

			/**
			 * If the mediaItem being created is being assigned to another user that's not the current user, make sure
			 * the current user has permission to edit others mediaItems
			 */
			if ( ! empty( $input['authorId'] ) && get_current_user_id() !== $input['authorId'] && ( ! isset( $post_type_object->cap->edit_others_posts ) || ! current_user_can( $post_type_object->cap->edit_others_posts ) ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to create mediaItems as this user', 'wp-graphql' ) );
			}

			/**
			 * Insert the mediaItem
			 *
			 * Required Argument defaults are set in the main MediaItemMutation.php if they aren't set
			 * by the user during input, they are:
			 * post_title (pulled from file if not entered)
			 * post_content (empty string if not entered)
			 * post_status (inherit if not entered)
			 * post_mime_type (pulled from the file if not entered in the mutation)
			 */
			$attachment_id = wp_insert_attachment( $media_item_args, $file['file'], $attachment_parent_id );

			if ( is_wp_error( $attachment_id ) ) {
				throw new UserError( __( 'The Media Item failed to create', 'wp-graphql' ) );
			}

			/**
			 * Check if the wp_generate_attachment_metadata method exists and include it if not
			 */
			require_once ABSPATH . 'wp-admin/includes/image.php';

			/**
			 * Generate and update the mediaItem's metadata.
			 * If we make it this far the file and attachment
			 * have been validated and we will not receive any errors
			 */
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file['file'] );
			wp_update_attachment_metadata( $attachment_id, $attachment_data );

			$post_type_object = get_post_type_object( 'attachment' );

			if ( empty( $post_type_object ) ) {
				throw new UserError( __( 'The Media Item could not be created', 'wp-graphql' ) );
			}

			/**
			 * Update alt text postmeta for mediaItem
			 */
			MediaItemMutation::update_additional_media_item_data( $attachment_id, $input, $post_type_object, 'createMediaItem', $context, $info );

			return [
				'postObjectId' => $attachment_id,
			];
		};
	}
}
