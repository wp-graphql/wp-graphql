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
					 * insert the post object and get the ID
					 */
					$post_args = MediaItemMutation::prepare_media_item( $input, $post_type_object, $mutation_name );

					/**
					 * Insert the post and retrieve the ID
					 */
					$post_id = wp_insert_attachment( wp_slash( (array) $post_args ), true );

					/**
					 * Throw an exception if the post failed to create
					 */
					if ( is_wp_error( $post_id ) ) {
						$error_message = $post_id->get_error_message();
						if ( ! empty( $error_message ) ) {
							throw new \Exception( esc_html( $error_message ) );
						} else {
							throw new \Exception( __( 'The object failed to create but no error was provided', 'wp-graphql' ) );
						}
					}

					/**
					 * If the $post_id is empty, we should throw an exception
					 */
					if ( empty( $post_id ) ) {
						throw new \Exception( __( 'The object failed to create', 'wp-graphql' ) );
					}

					// @TODO: Add updates for attachment post meta

				},

			] );

		endif; // End if().

		return self::$mutation;
	}
}
