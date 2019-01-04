<?php
namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Data\PostObjectMutation;

class PostObjectUpdate {
	public static function register_mutation( \WP_Post_Type $post_type_object ) {
		$mutation_name = 'update' . ucwords( $post_type_object->graphql_single_name );

		register_graphql_mutation( $mutation_name, [
			'inputFields'         => self::get_input_fields( $post_type_object ),
			'outputFields'        => self::get_output_fields( $post_type_object ),
			'mutateAndGetPayload' => self::mutate_and_get_payload( $post_type_object, $mutation_name ),
		] );
	}

	public static function get_input_fields( $post_type_object ) {
		return array_merge(
			PostObjectCreate::get_input_fields( $post_type_object ),
			[
				'id' => [
					'type'        => [
						'non_null' => 'ID',
					],
				]
			),
			'outputFields'        => [
				$post_type_object->graphql_single_name => [
					'type'    => $post_type_object->graphql_single_name,
					'resolve' => function ( $payload ) use ( $post_type_object ) {
						return DataSource::resolve_post_object( $payload['postObjectId'], $post_type_object->name );
					},
				],
			]
			);
	}

	public static function get_output_fields( $post_type_object ) {
		return PostObjectCreate::get_output_fields( $post_type_object );
	}

	public static function mutate_and_get_payload( $post_type_object, $mutation_name ) {
		return function ( $input, AppContext $context, ResolveInfo $info ) use ( $post_type_object, $mutation_name ) {

				/**
				 * @todo: when we add support for assigning terms to posts, we should check permissions to make sure they can assign terms
				 * @see : https://github.com/WordPress/WordPress/blob/e357195ce303017d517aff944644a7a1232926f7/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php#L644-L646
				 */

				/**
				 * insert the post object and get the ID
				 */
				$post_args       = PostObjectMutation::prepare_post_object( $input, $post_type_object, $mutation_name );
				$post_args['ID'] = absint( $id_parts['id'] );

				/**
				 * Insert the post and retrieve the ID
				 */
				$post_id = wp_update_post( wp_slash( (array) $post_args ), true );

				/**
				 * Throw an exception if the post failed to update
				 */
				if ( is_wp_error( $post_id ) ) {
					throw new UserError( __( 'The object failed to update but no error was provided', 'wp-graphql' ) );
				}

				/**
				 * Fires after a single term is created or updated via a GraphQL mutation
				 *
				 * The dynamic portion of the hook name, `$taxonomy->name` refers to the taxonomy of the term being mutated
				 *
				 * @param int    $post_id       Inserted post ID
				 * @param array  $args          The args used to insert the term
				 * @param string $mutation_name The name of the mutation being performed
				 */
				do_action( "graphql_insert_{$post_type_object->name}", $post_id, $post_args, $mutation_name );

				/**
				 * This updates additional data not part of the posts table (postmeta, terms, other relations, etc)
				 *
				 * The input for the postObjectMutation will be passed, along with the $new_post_id for the
				 * postObject that was updated so that relations can be set, meta can be updated, etc.
				 */
				PostObjectMutation::update_additional_post_object_data( $post_id, $input, $post_type_object, $mutation_name, $context, $info );

				/**
				 * Return the payload
				 */
				return [
					'postObjectId' => $post_id,
				];

			/**
			 * insert the post object and get the ID
			 */
			$post_args       = PostObjectMutation::prepare_post_object( $input, $post_type_object, $mutation_name );
			$post_args['ID'] = absint( $id_parts['id'] );

			/**
			 * Insert the post and retrieve the ID
			 */
			$post_id = wp_update_post( wp_slash( (array) $post_args ), true );

			/**
			 * Throw an exception if the post failed to update
			 */
			if ( is_wp_error( $post_id ) ) {
				throw new UserError( __( 'The object failed to update but no error was provided', 'wp-graphql' ) );
			}

			/**
			 * Fires after a single term is created or updated via a GraphQL mutation
			 *
			 * The dynamic portion of the hook name, `$taxonomy->name` refers to the taxonomy of the term being mutated
			 *
			 * @param int    $post_id       Inserted post ID
			 * @param array  $args          The args used to insert the term
			 * @param string $mutation_name The name of the mutation being performed
			 */
			do_action( "graphql_insert_{$post_type_object->name}", $post_id, $post_args, $mutation_name );

			/**
			 * This updates additional data not part of the posts table (postmeta, terms, other relations, etc)
			 *
			 * The input for the postObjectMutation will be passed, along with the $new_post_id for the
			 * postObject that was updated so that relations can be set, meta can be updated, etc.
			 */
			PostObjectMutation::update_additional_post_object_data( $post_id, $input, $post_type_object, $mutation_name, $context, $info );

			/**
			 * Return the payload
			 */
			return [
				'postObjectId' => $post_id,
			];
		};
	}
}