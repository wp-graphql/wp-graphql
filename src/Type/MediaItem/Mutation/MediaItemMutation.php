<?php
namespace WPGraphQL\Type\MediaItem\Mutation;

use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class MediaItemMutation
 *
 * @package WPGraphQL\Type\MediaItem
 */
class MediaItemMutation {

	/**
	 * Holds the input fields configuration
	 *
	 * @var array
	 */
	private static $input_fields = [];

	/**
	 * @param $post_type_object
	 *
	 * @return mixed|array|null $input_fields
	 */
	public static function input_fields( $post_type_object ) {

		if ( ! empty( $post_type_object->graphql_single_name ) && empty( self::$input_fields[ $post_type_object->graphql_single_name ] ) ) {

			$input_fields = [
				'altText'       => [
					'type'        => Types::string(),
					'description' => __( 'Alternative text to display when media item is not displayed', 'wp-graphql' ),
				],
				'authorId'      => [
					'type'        => Types::id(),
					'description' => __( 'The userId to assign as the author of the media item', 'wp-graphql' ),
				],
				'caption'       => [
					'type'        => Types::string(),
					'description' => __( 'The caption for the resource', 'wp-graphql' ),
				],
				'commentStatus' => [
					'type'        => Types::string(),
					'description' => __( 'The comment status for the media item', 'wp-graphql' ),
				],
				'date'          => [
					'type'        => Types::string(),
					'description' => __( 'The date of the media item', 'wp-graphql' ),
				],
				'dateGmt'       => [
					'type'        => Types::string(),
					'description' => __( 'The date (in GMT zone) of the media item', 'wp-graphql' ),
				],
				'description'   => [
					'type'        => Types::string(),
					'description' => __( 'Description of the media item', 'wp-graphql' ),
				],
				'filePath'      => [
					'type'        => Types::string(),
					'description' => __( 'The file name of the media item', 'wp-graphql' ),
				],
				'fileType'      => [
					'type'        => Types::mime_type_enum(),
					'description' => __( 'The file type of the media item', 'wp-graphql' ),
				],
				'slug'          => [
					'type'        => Types::string(),
					'description' => __( 'The slug of the object', 'wp-graphql' ),
				],
				'status'        => [
					'type'        => Types::media_item_status_enum(),
					'description' => __( 'The status of the media item', 'wp-graphql' ),
				],
				'title'         => [
					'type'        => Types::string(),
					'description' => __( 'The title of the media item', 'wp-graphql' ),
				],
				'pingStatus'    => [
					'type'        => Types::string(),
					'description' => __( 'The ping status for the media item', 'wp-graphql' ),
				],
				'parentId'      => [
					'type'        => Types::id(),
					'description' => __( 'The ID of the parent object', 'wp-graphql' ),
				],
			];

			/**
			 * Filters the mutation input fields for the media item
			 *
			 * @param array         $input_fields     The array of input fields
			 * @param \WP_Post_Type $post_type_object The post_type object for the media item
			 */
			self::$input_fields[ $post_type_object->graphql_single_name ] = apply_filters( 'graphql_media_item_mutation_input_fields', $input_fields, $post_type_object );

		} // End if().

		return ! empty( self::$input_fields[ $post_type_object->graphql_single_name ] ) ? self::$input_fields[ $post_type_object->graphql_single_name ] : null;

	}

	/**
	 * This prepares the media item for insertion
	 *
	 * @param array         $input            The input for the mutation from the GraphQL request
	 * @param \WP_Post_Type $post_type_object The post_type_object for the attachment/media item
	 * @param string        $mutation_name    The name of the mutation being performed (create, update, etc.)
	 *
	 * @return array $media_item_args
	 */
	public static function prepare_media_item( $input, $post_type_object, $mutation_name ) {

		/**
		 * Set the post_type for the insert
		 */
		$insert_post_args['post_type'] = $post_type_object->name;

		/**
		 * Prepare the data for inserting the media item (attachment)
		 * NOTE: These are organized in the same order as: http://v2.wp-api.org/reference/media/#schema-meta
		 */
		if ( ! empty( $input['date'] ) && false !== strtotime( $input['date'] ) ) {
			$insert_post_args['post_date'] = strtotime( $input['date'] );
		}

		if ( ! empty( $input['dateGmt'] ) && false !== strtotime( $input['dateGmt'] ) ) {
			$insert_post_args['post_date_gmt'] = strtotime( $input['dateGmt'] );
		}

		if ( ! empty( $input['slug'] ) ) {
			$insert_post_args['post_name'] = $input['slug'];
		}

		if ( ! empty( $input['status'] ) ) {
			$insert_post_args['post_status'] = $input['status'];
		}

		if ( ! empty( $input['title'] ) ) {
			$insert_post_args['post_title'] = $input['title'];
		}

		$author_id_parts = ! empty( $input['authorId'] ) ? Relay::fromGlobalId( $input['authorId'] ) : null;
		if ( is_array( $author_id_parts ) && ! empty( $author_id_parts['id'] ) && is_int( $author_id_parts['id'] ) ) {
			$insert_post_args['post_author'] = absint( $author_id_parts['id'] );
		}

		if ( ! empty( $input['commentStatus'] ) ) {
			$insert_post_args['comment_status'] = $input['commentStatus'];
		}

		if ( ! empty( $input['pingStatus'] ) ) {
			$insert_post_args['ping_status'] = $input['pingStatus'];
		}

		if ( ! empty( $input['altText'] ) ) {
			$insert_post_args['alt_text'] = $input['altText'];
		}

		if ( ! empty( $input['caption'] ) ) {
			$insert_post_args['post_excerpt'] = $input['caption'];
		}

		if ( ! empty( $input['description'] ) ) {
			$insert_post_args['post_content'] = $input['description'];
		}

		if ( ! empty( $input['filePath'] ) ) {
			$insert_post_args['path'] = $input['filePath'];
		}

		if ( ! empty( $input['fileType'] ) ) {
			$insert_post_args['post_mime_type'] = $input['fileType'];
		}

		$parent_id_parts = ! empty( $input['parentID'] ) ? Relay::fromGlobalId( $input['parentId'] ) : null;
		if ( is_array( $parent_id_parts ) && ! empty( $parent_id_parts['id'] ) && is_int( $parent_id_parts['id'] ) ) {
			$insert_post_args['post_parent'] = absint( $parent_id_parts['id'] );
		}

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array         $insert_post_args The array of $input_post_args that will be passed to wp_insert_post
		 * @param array         $input            The data that was entered as input for the mutation
		 * @param \WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
		 * @param string        $mutation_type    The type of mutation being performed (create, edit, etc)
		 */
		$insert_post_args = apply_filters( 'graphql_media_item_object_insert_post_args', $insert_post_args, $input, $post_type_object, $mutation_name );

		return $insert_post_args;
	}

	// @TODO: Add Meta fields for updating meta fields and set them down here (Media_sizes, meta, details, etc

}
