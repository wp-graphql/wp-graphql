<?php

namespace WPGraphQL\Type\PostObject\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class PostObjectCreate
 *
 * @package WPGraphQL\Type\PostObject\Mutation
 */
class PostObjectCreate {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];

	/**
	 * Defines the create mutation for PostTypeObjects
	 *
	 * @param \WP_Post_Type $post_type_object
	 *
	 * @return array|mixed
	 */
	public static function mutate( \WP_Post_Type $post_type_object ) {

		if ( ! empty( $post_type_object->graphql_single_name ) && empty( self::$mutation[ $post_type_object->graphql_single_name ] ) ) :

			/**
			 * Set the name of the mutation being performed
			 */
			$mutation_name = 'Create' . ucwords( $post_type_object->graphql_single_name );

			self::$mutation[ $post_type_object->graphql_single_name ] = Relay::mutationWithClientMutationId( [
				'name'                => esc_html( $mutation_name ),
				// translators: The placeholder is the name of the object type
				'description'         => sprintf( __( 'Create %1$s objects', 'wp-graphql' ), $post_type_object->graphql_single_name ),
				'inputFields'         => PostObjectMutation::input_fields( $post_type_object ),
				'outputFields'        => [
					$post_type_object->graphql_single_name => [
						'type'    => Types::post_object( $post_type_object->name ),
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
						throw new UserError( __( 'Mutation not processed. There was no input for the mutation or the post_type_object was invalid', 'wp-graphql' ) );
					}

					/**
					 * Stop now if a user isn't allowed to create a post
					 */
					if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
						// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
						throw new UserError( sprintf( __( 'Sorry, you are not allowed to create %1$s', 'wp-graphql' ), $post_type_object->graphql_plural_name ) );
					}

					/**
					 * If the post being created is being assigned to another user that's not the current user, make sure
					 * the current user has permission to edit others posts for this post_type
					 */
					if ( ! empty( $input['authorId'] ) && get_current_user_id() !== $input['authorId'] && ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
						// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
						throw new UserError( sprintf( __( 'Sorry, you are not allowed to create %1$s as this user', 'wp-graphql' ), $post_type_object->graphql_plural_name ) );
					}

					/**
					 * @todo: When we support assigning terms and setting posts as "sticky" we need to check permissions
					 * @see:https://github.com/WordPress/WordPress/blob/e357195ce303017d517aff944644a7a1232926f7/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php#L504-L506
					 * @see: https://github.com/WordPress/WordPress/blob/e357195ce303017d517aff944644a7a1232926f7/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php#L496-L498
					 */

					/**
					 * insert the post object and get the ID
					 */
					$post_args = PostObjectMutation::prepare_post_object( $input, $post_type_object, $mutation_name );

					/**
					 * Insert the post and retrieve the ID
					 */
					$post_id = wp_insert_post( wp_slash( (array) $post_args ), true );

					/**
					 * Throw an exception if the post failed to create
					 */
					if ( is_wp_error( $post_id ) ) {
						$error_message = $post_id->get_error_message();
						if ( ! empty( $error_message ) ) {
							throw new UserError( esc_html( $error_message ) );
						} else {
							throw new UserError( __( 'The object failed to create but no error was provided', 'wp-graphql' ) );
						}
					}

					/**
					 * If the $post_id is empty, we should throw an exception
					 */
					if ( empty( $post_id ) ) {
						throw new UserError( __( 'The object failed to create', 'wp-graphql' ) );
					}

					/**
					 * This updates additional data not part of the posts table (postmeta, terms, other relations, etc)
					 *
					 * The input for the postObjectMutation will be passed, along with the $new_post_id for the
					 * postObject that was created so that relations can be set, meta can be updated, etc.
					 */
					PostObjectMutation::update_additional_post_object_data( $post_id, $input, $post_type_object, $mutation_name );

					/**
					 * Return the post object
					 */
					return [
						'id' => $post_id,
					];
				},

			] );

		endif; // End if().

		return ! empty( self::$mutation[ $post_type_object->graphql_single_name ] ) ? self::$mutation[ $post_type_object->graphql_single_name ] : null;

	}

}
