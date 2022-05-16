<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WP_Taxonomy;
use WPGraphQL\Model\Term;
use WPGraphQL\Utils\Utils;

/**
 * Class TermObjectDelete
 *
 * @package WPGraphQL\Mutation
 */
class TermObjectDelete {
	/**
	 * Registers the TermObjectDelete mutation.
	 *
	 * @param WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return void
	 */
	public static function register_mutation( WP_Taxonomy $taxonomy ) {
		$mutation_name = 'delete' . ucfirst( $taxonomy->graphql_single_name );

		register_graphql_mutation(
			$mutation_name,
			[
				'inputFields'         => self::get_input_fields( $taxonomy ),
				'outputFields'        => self::get_output_fields( $taxonomy ),
				'mutateAndGetPayload' => self::mutate_and_get_payload( $taxonomy, $mutation_name ),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @param WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return array
	 */
	public static function get_input_fields( WP_Taxonomy $taxonomy ) {
		return [
			'id' => [
				'type'        => [
					'non_null' => 'ID',
				],
				// translators: The placeholder is the name of the taxonomy for the term being deleted
				'description' => sprintf( __( 'The ID of the %1$s to delete', 'wp-graphql' ), $taxonomy->graphql_single_name ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @param WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return array
	 */
	public static function get_output_fields( WP_Taxonomy $taxonomy ) {
		return [
			'deletedId'                    => [
				'type'        => 'ID',
				'description' => __( 'The ID of the deleted object', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$deleted = (object) $payload['termObject'];

					return ! empty( $deleted->term_id ) ? Relay::toGlobalId( 'term', $deleted->term_id ) : null;
				},
			],
			$taxonomy->graphql_single_name => [
				'type'        => $taxonomy->graphql_single_name,
				'description' => __( 'The deteted term object', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					return new Term( $payload['termObject'] );
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @param WP_Taxonomy $taxonomy      The taxonomy type of the mutation.
	 * @param string      $mutation_name The name of the mutation.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload( WP_Taxonomy $taxonomy, string $mutation_name ) {
		return function ( $input ) use ( $taxonomy ) {
			// Get the database ID for the comment.
			$term_id = Utils::get_database_id_from_id( $input['id'] );

			if ( empty( $term_id ) ) {
				// Translators: The placeholder is the name of the taxonomy for the term being deleted
				throw new UserError( sprintf( __( 'The ID for the %1$s was not valid', 'wp-graphql' ), $taxonomy->graphql_single_name ) );
			}

			/**
			 * Get the term before deleting it
			 */
			$term_object = get_term( $term_id, $taxonomy->name );

			if ( ! $term_object instanceof \WP_Term ) {
				throw new UserError( __( 'The ID passed is invalid', 'wp-graphql' ) );
			}

			/**
			 * Ensure the type for the Global ID matches the type being mutated
			 */
			if ( $taxonomy->name !== $term_object->taxonomy ) {
				// Translators: The placeholder is the name of the taxonomy for the term being edited
				throw new UserError( sprintf( __( 'The ID passed is not for a %1$s object', 'wp-graphql' ), $taxonomy->graphql_single_name ) );
			}

			/**
			 * Ensure the user can delete terms of this taxonomy
			 */
			if ( ! current_user_can( 'delete_term', $term_object->term_id ) ) {
				// Translators: The placeholder is the name of the taxonomy for the term being deleted
				throw new UserError( sprintf( __( 'You do not have permission to delete %1$s', 'wp-graphql' ), $taxonomy->graphql_plural_name ) );
			}

			/**
			 * Delete the term and get the response
			 */
			$deleted = wp_delete_term( $term_id, $taxonomy->name );

			/**
			 * If there was an error deleting the term, get the error message and return it
			 */
			if ( is_wp_error( $deleted ) ) {
				$error_message = $deleted->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				} else {
					// Translators: The placeholder is the name of the taxonomy for the term being deleted
					throw new UserError( sprintf( __( 'The %1$s failed to delete but no error was provided', 'wp-graphql' ), $taxonomy->name ) );
				}
			}

			/**
			 * Return the term object that was retrieved prior to deletion
			 */
			return [
				'termObject' => $term_object,
			];
		};
	}
}
