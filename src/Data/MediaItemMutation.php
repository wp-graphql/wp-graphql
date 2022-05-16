<?php

namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WP_Post_Type;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

/**
 * Class MediaItemMutation
 *
 * @package WPGraphQL\Type\MediaItem
 */
class MediaItemMutation {

	/**
	 * This prepares the media item for insertion
	 *
	 * @param array        $input            The input for the mutation from the GraphQL request
	 * @param WP_Post_Type $post_type_object The post_type_object for the mediaItem (attachment)
	 * @param string       $mutation_name    The name of the mutation being performed (create,
	 *                                       update, etc.)
	 * @param mixed        $file             The mediaItem (attachment) file
	 *
	 * @return array $media_item_args
	 */
	public static function prepare_media_item( array $input, WP_Post_Type $post_type_object, string $mutation_name, $file ) {

		/**
		 * Set the post_type (attachment) for the insert
		 */
		$insert_post_args['post_type'] = $post_type_object->name;

		/**
		 * Prepare the data for inserting the mediaItem
		 * NOTE: These are organized in the same order as: http://v2.wp-api.org/reference/media/#schema-meta
		 */
		if ( ! empty( $input['date'] ) && false !== strtotime( $input['date'] ) ) {
			$insert_post_args['post_date'] = gmdate( 'Y-m-d H:i:s', strtotime( $input['date'] ) );
		}

		if ( ! empty( $input['dateGmt'] ) && false !== strtotime( $input['dateGmt'] ) ) {
			$insert_post_args['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $input['dateGmt'] ) );
		}

		if ( ! empty( $input['slug'] ) ) {
			$insert_post_args['post_name'] = $input['slug'];
		}

		if ( ! empty( $input['status'] ) ) {
			$insert_post_args['post_status'] = $input['status'];
		} else {
			$insert_post_args['post_status'] = 'inherit';
		}

		if ( ! empty( $input['title'] ) ) {
			$insert_post_args['post_title'] = $input['title'];
		} elseif ( ! empty( $file['file'] ) ) {
			$insert_post_args['post_title'] = basename( $file['file'] );
		}

		if ( ! empty( $input['authorId'] ) ) {
			$insert_post_args['post_author'] = Utils::get_database_id_from_id( $input['authorId'] );
		}

		if ( ! empty( $input['commentStatus'] ) ) {
			$insert_post_args['comment_status'] = $input['commentStatus'];
		}

		if ( ! empty( $input['pingStatus'] ) ) {
			$insert_post_args['ping_status'] = $input['pingStatus'];
		}

		if ( ! empty( $input['caption'] ) ) {
			$insert_post_args['post_excerpt'] = $input['caption'];
		}

		if ( ! empty( $input['description'] ) ) {
			$insert_post_args['post_content'] = $input['description'];
		} else {
			$insert_post_args['post_content'] = '';
		}

		if ( ! empty( $file['type'] ) ) {
			$insert_post_args['post_mime_type'] = $file['type'];
		} elseif ( ! empty( $input['fileType'] ) ) {
			$insert_post_args['post_mime_type'] = $input['fileType'];
		}

		if ( ! empty( $input['parentId'] ) ) {
			$insert_post_args['post_parent'] = Utils::get_database_id_from_id( $input['parentId'] );
		}

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array        $insert_post_args The array of $input_post_args that will be passed to wp_insert_attachment
		 * @param array        $input            The data that was entered as input for the mutation
		 * @param WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
		 * @param string       $mutation_type    The type of mutation being performed (create, update, delete)
		 */
		$insert_post_args = apply_filters( 'graphql_media_item_insert_post_args', $insert_post_args, $input, $post_type_object, $mutation_name );

		return $insert_post_args;
	}

	/**
	 * This updates additional data related to a mediaItem, such as postmeta.
	 *
	 * @param int          $media_item_id    The ID of the media item being mutated
	 * @param array        $input            The input on the mutation
	 * @param WP_Post_Type $post_type_object The Post Type Object for the item being mutated
	 * @param string       $mutation_name    The name of the mutation
	 * @param AppContext   $context          The AppContext that is passed down the resolve tree
	 * @param ResolveInfo  $info             The ResolveInfo that is passed down the resolve tree
	 *
	 * @return void
	 */
	public static function update_additional_media_item_data( int $media_item_id, array $input, WP_Post_Type $post_type_object, string $mutation_name, AppContext $context, ResolveInfo $info ) {

		/**
		 * Update alt text postmeta for the mediaItem
		 */
		if ( ! empty( $input['altText'] ) ) {
			update_post_meta( $media_item_id, '_wp_attachment_image_alt', $input['altText'] );
		}

		/**
		 * Run an action after the additional data has been updated. This is a great spot to hook into to
		 * update additional data related to mediaItems, such as updating additional postmeta,
		 * or sending emails to Kevin. . .whatever you need to do with the mediaItem.
		 *
		 * @param int          $media_item_id    The ID of the mediaItem being mutated
		 * @param array        $input            The input for the mutation
		 * @param WP_Post_Type $post_type_object The Post Type Object for the type of post being mutated
		 * @param string       $mutation_name    The name of the mutation (ex: create, update, delete)
		 * @param AppContext   $context          The AppContext that is passed down the resolve tree
		 * @param ResolveInfo  $info             The ResolveInfo that is passed down the resolve tree
		 */
		do_action( 'graphql_media_item_mutation_update_additional_data', $media_item_id, $input, $post_type_object, $mutation_name, $context, $info );

	}

}
