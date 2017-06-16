<?php

namespace WPGraphQL\Type\PostObject\Mutation;

use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class PostObjectMutation
 *
 * @package WPGraphQL\Type\PostObject
 */
class PostObjectMutation {

	/**
	 * Holds the input_fields configuration
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
				'authorId'      => [
					'type'        => Types::id(),
					'description' => __( 'The userId to assign as the author of the post', 'wp-graphql' ),
				],
				'commentCount'  => [
					'type'        => Types::int(),
					'description' => __( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatability.', 'wp-graphql' ),
				],
				'commentStatus' => [
					'type'        => Types::string(),
					'description' => __( 'The comment status for the object', 'wp-graphql' ),
				],
				'content'       => [
					'type'        => Types::string(),
					'description' => __( 'The content of the object', 'wp-graphql' ),
				],
				'date'          => [
					'type'        => Types::string(),
					'description' => __( 'The date of the object', 'wp-graphql' ),
				],
				'dateGmt'       => [
					'type'        => Types::string(),
					'description' => __( 'The date (in GMT zone) of the object', 'wp-graphql' ),
				],
				'excerpt'       => [
					'type'        => Types::string(),
					'description' => __( 'The excerpt of the object', 'wp-graphql' ),
				],
				'menuOrder'     => [
					'type'        => Types::int(),
					'description' => __( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
				],
				'mimeType'      => [
					'type'        => Types::mime_type_enum(),
					'description' => __( 'If the post is an attachment or a media file, this field will carry the corresponding MIME type. This field is equivalent to the value of WP_Post->post_mime_type and the post_mime_type column in the `post_objects` database table.', 'wp-graphql' ),
				],
				'modified'      => [
					'type'        => Types::string(),
					'description' => __( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
				],
				'modifiedGmt'   => [
					'type'        => Types::string(),
					'description' => __( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
				],
				'parentId'      => [
					'type'        => Types::id(),
					'description' => __( 'The ID of the parent object', 'wp-graphql' ),
				],
				'password'      => [
					'type'        => Types::string(),
					'description' => __( 'The password used to protect the content of the object', 'wp-graphql' ),
				],
				'pinged'        => [
					'type'        => Types::list_of( Types::string() ),
					'description' => __( 'URLs that have been pinged.', 'wp-graphql' ),
				],
				'pingStatus'    => [
					'type'        => Types::string(),
					'description' => __( 'The ping status for the object', 'wp-graphql' ),
				],
				'slug'          => [
					'type'        => Types::string(),
					'description' => __( 'The slug of the object', 'wp-graphql' ),
				],
				'status'        => [
					'type'        => Types::post_status_enum(),
					'description' => __( 'The status of the object', 'wp-graphql' ),
				],
				'title'         => [
					'type'        => Types::string(),
					'description' => __( 'The title of the post', 'wp-graphql' ),
				],
				'toPing'        => [
					'type'        => Types::list_of( Types::string() ),
					'description' => __( 'URLs queued to be pinged.', 'wp-graphql' ),
				],
			];

			/**
			 * Filters the mutation input fields for the object type
			 *
			 * @param array         $input_fields     The array of input fields
			 * @param \WP_Post_Type $post_type_object The post_type object for the type of Post being mutated
			 */
			self::$input_fields[ $post_type_object->graphql_single_name ] = apply_filters( 'graphql_post_object_mutation_input_fields', $input_fields, $post_type_object );


		} // End if().

		return ! empty( self::$input_fields[ $post_type_object->graphql_single_name ] ) ? self::$input_fields[ $post_type_object->graphql_single_name ] : null;

	}

	/**
	 * This handles inserting the post object
	 *
	 * @param array         $input            The input for the mutation
	 * @param \WP_Post_Type $post_type_object The post_type_object for the type of post being mutated
	 * @param string        $mutation_name    The name of the mutation being performed
	 *
	 * @return array $insert_post_args
	 * @throws \Exception
	 */
	public static function prepare_post_object( $input, $post_type_object, $mutation_name ) {

		/**
		 * Set the post_type for the insert
		 */
		$insert_post_args['post_type'] = $post_type_object->name;

		/**
		 * Prepare the data for inserting the post
		 * NOTE: These are organized in the same order as: https://developer.wordpress.org/reference/functions/wp_insert_post/
		 */
		$author_id_parts = ! empty( $input['authorId'] ) ? Relay::fromGlobalId( $input['authorId'] ) : null;
		if ( is_array( $author_id_parts ) && ! empty( $author_id_parts['id'] ) && is_int( $author_id_parts['id'] ) ) {
			$insert_post_args['post_author'] = absint( $author_id_parts['id'] );
		}

		if ( ! empty( $input['date'] ) && false !== strtotime( $input['date'] ) ) {
			$insert_post_args['post_date'] = strtotime( $input['date'] );
		}

		if ( ! empty( $input['dateGmt'] ) && false !== strtotime( $input['dateGmt'] ) ) {
			$insert_post_args['post_date_gmt'] = strtotime( $input['dateGmt'] );
		}

		if ( ! empty( $input['content'] ) ) {
			$insert_post_args['post_content'] = $input['content'];
		}

		if ( ! empty( $input['title'] ) ) {
			$insert_post_args['post_title'] = $input['title'];
		}

		if ( ! empty( $input['excerpt'] ) ) {
			$insert_post_args['post_excerpt'] = $input['excerpt'];
		}

		if ( ! empty( $input['status'] ) ) {
			$insert_post_args['post_status'] = $input['status'];
		}

		if ( ! empty( $input['commentStatus'] ) ) {
			$insert_post_args['comment_status'] = $input['commentStatus'];
		}

		if ( ! empty( $input['pingStatus'] ) ) {
			$insert_post_args['ping_status'] = $input['pingStatus'];
		}

		if ( ! empty( $input['password'] ) ) {
			$insert_post_args['post_password'] = $input['password'];
		}

		if ( ! empty( $input['slug'] ) ) {
			$insert_post_args['post_name'] = $input['slug'];
		}

		if ( ! empty( $input['toPing'] ) ) {
			$insert_post_args['to_ping'] = $input['toPing'];
		}

		if ( ! empty( $input['pinged'] ) ) {
			$insert_post_args['pinged'] = $input['pinged'];
		}

		if ( ! empty( $input['postModified'] ) && false !== strtotime( $input['postModified'] ) ) {
			$insert_post_args['modified'] = strtotime( $input['postModified'] );
		}

		if ( ! empty( $input['postModifiedGmt'] ) && false !== strtotime( $input['postModifiedGmt'] ) ) {
			$insert_post_args['post_modified_gmt'] = strtotime( $input['postModifiedGmt'] );
		}

		$parent_id_parts = ! empty( $input['parentId'] ) ? Relay::fromGlobalId( $input['parentId'] ) : null;
		if ( is_array( $parent_id_parts ) && ! empty( $parent_id_parts['id'] ) && is_int( $parent_id_parts['id'] ) ) {
			$insert_post_args['post_parent'] = absint( $parent_id_parts['id'] );
		}

		if ( ! empty( $input['menuOrder'] ) ) {
			$insert_post_args['menu_order'] = $input['menuOrder'];
		}

		if ( ! empty( $input['mimeType'] ) ) {
			$insert_post_args['post_mime_type'] = $input['mimeType'];
		}

		if ( ! empty( $input['commentCount'] ) ) {
			$insert_post_args['comment_count'] = $input['commentCount'];
		}

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array         $insert_post_args The array of $input_post_args that will be passed to wp_insert_post
		 * @param array         $input            The data that was entered as input for the mutation
		 * @param \WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
		 * @param string        $mutation_type    The type of mutation being performed (create, edit, etc)
		 */
		$insert_post_args = apply_filters( 'graphql_post_object_insert_post_args', $insert_post_args, $input, $post_type_object, $mutation_name );

		/**
		 * Return the $args
		 */
		return $insert_post_args;

	}

	/**
	 * This updates additional data related to a post object, such as postmeta, term relationships, etc.
	 *
	 * @param $new_post_id
	 * @param $input
	 * @param $post_type_object
	 * @param $mutation_name
	 */
	public static function update_additional_post_object_data( $new_post_id, $input, $post_type_object, $mutation_name ) {

		/**
		 * Set the post_lock for the $new_post_id
		 */
		self::set_edit_lock( $new_post_id );

		/**
		 * Update the _edit_last field
		 */
		update_post_meta( $new_post_id, '_edit_last', get_current_user_id() );

		/**
		 * Update the postmeta fields
		 */
		if ( ! empty( $input['desiredSlug'] ) ) {
			update_post_meta( $new_post_id, '_wp_desired_post_slug', $input['desiredSlug'] );
		}

		/**
		 * Run an action after the additional data has been updated. This is a great spot to hook into to
		 * update additional data related to postObjects, such as setting relationships, updating additional postmeta,
		 * or sending emails to Kevin. . .whatever you need to do with the postObject.
		 *
		 * @param int           $new_post_id      The ID of the postObject being mutated
		 * @param array         $input            The input for the mutation
		 * @param \WP_Post_Type $post_type_object The Post Type Object for the type of post being mutated
		 * @param string        $mutation_name    The name of the mutation (ex: create, update, delete)
		 */
		do_action( 'graphql_post_object_mutation_update_additional_data', $new_post_id, $input, $post_type_object, $mutation_name );

	}

	/**
	 * This is a copy of the wp_set_post_lock function that exists in WordPress core, but is not
	 * accessible because that part of WordPress is never loaded for WPGraphQL executions
	 *
	 * Mark the post as currently being edited by the current user
	 *
	 * @param int $post_id ID of the post being edited.
	 *
	 * @return array|false Array of the lock time and user ID. False if the post does not exist, or
	 *                     there is no current user.
	 */
	public static function set_edit_lock( $post_id ) {

		$post    = get_post( $post_id );
		$user_id = get_current_user_id();

		if ( empty( $post ) ) {
			return false;
		}

		if ( 0 === $user_id ) {
			return false;
		}

		$now  = time();
		$lock = "$now:$user_id";
		update_post_meta( $post->ID, '_edit_lock', $lock );

		return [ $now, $user_id ];

	}

}
