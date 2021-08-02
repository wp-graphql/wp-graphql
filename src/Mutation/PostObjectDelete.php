<?php

namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WP_Post_Type;
use WPGraphQL\Model\Post;

class PostObjectDelete {
	/**
	 * Registers the PostObjectDelete mutation.
	 *
	 * @param WP_Post_Type $post_type_object The post type of the mutation.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_mutation( WP_Post_Type $post_type_object ) {
		$mutation_name = 'delete' . ucwords( $post_type_object->graphql_single_name );

		register_graphql_mutation(
			$mutation_name,
			[
				'inputFields'         => self::get_input_fields( $post_type_object ),
				'outputFields'        => self::get_output_fields( $post_type_object ),
				'mutateAndGetPayload' => self::mutate_and_get_payload( $post_type_object, $mutation_name ),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @param WP_Post_Type $post_type_object The post type of the mutation.
	 *
	 * @return array
	 */
	public static function get_input_fields( $post_type_object ) {
		return [
			'id'          => [
				'type'        => [
					'non_null' => 'ID',
				],
				// translators: The placeholder is the name of the post's post_type being deleted
				'description' => sprintf( __( 'The ID of the %1$s to delete', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			],
			'forceDelete' => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the object should be force deleted instead of being moved to the trash', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @param WP_Post_Type $post_type_object The post type of the mutation.
	 *
	 * @return array
	 */
	public static function get_output_fields( WP_Post_Type $post_type_object ) {
		return [
			'deletedId'                            => [
				'type'        => 'Id',
				'description' => __( 'The ID of the deleted object', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$deleted = (object) $payload['postObject'];

					return ! empty( $deleted->ID ) ? Relay::toGlobalId( 'post', $deleted->ID ) : null;
				},
			],
			$post_type_object->graphql_single_name => [
				'type'        => $post_type_object->graphql_single_name,
				'description' => __( 'The object before it was deleted', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$deleted = (object) $payload['postObject'];

					return ! empty( $deleted ) ? $deleted : null;
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @param WP_Post_Type $post_type_object The post type of the mutation.
	 * @param string       $mutation_name    The mutation name.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload( WP_Post_Type $post_type_object, string $mutation_name ) {
		return function ( $input ) use ( $post_type_object ) {

			/**
			 * Get the ID from the global ID
			 */
			$id_parts = Relay::fromGlobalId( $input['id'] );

			/**
			 * Stop now if a user isn't allowed to delete a post
			 */
			if ( ! isset( $post_type_object->cap->delete_post ) || ! current_user_can( $post_type_object->cap->delete_post, absint( $id_parts['id'] ) ) ) {
				// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
				throw new UserError( sprintf( __( 'Sorry, you are not allowed to delete %1$s', 'wp-graphql' ), $post_type_object->graphql_plural_name ) );
			}

			/**
			 * Check if we should force delete or not
			 */
			$force_delete = ! empty( $input['forceDelete'] ) && true === $input['forceDelete'];

			/**
			 * Get the post object before deleting it
			 */
			$post_before_delete = get_post( absint( $id_parts['id'] ) );

			if ( empty( $post_before_delete ) ) {
				throw new UserError( __( 'The post could not be deleted', 'wp-graphql' ) );
			}

			$post_before_delete = new Post( $post_before_delete );

			/**
			 * If the post is already in the trash, and the forceDelete input was not passed,
			 * don't remove from the trash
			 */
			if ( 'trash' === $post_before_delete->post_status ) {
				if ( true !== $force_delete ) {
					// Translators: the first placeholder is the post_type of the object being deleted and the second placeholder is the unique ID of that object
					throw new UserError( sprintf( __( 'The %1$s with id %2$s is already in the trash. To remove from the trash, use the forceDelete input', 'wp-graphql' ), $post_type_object->graphql_single_name, $input['id'] ) );
				}
			}

			/**
			 * Delete the post
			 */
			$deleted = wp_delete_post( $id_parts['id'], $force_delete );

			/**
			 * If the post was moved to the trash, spoof the object's status before returning it
			 */
			$post_before_delete->post_status = ( false !== $deleted && true !== $force_delete ) ? 'trash' : $post_before_delete->post_status;

			/**
			 * Return the deletedId and the object before it was deleted
			 */
			return [
				'postObject' => $post_before_delete,
			];
		};
	}
}
