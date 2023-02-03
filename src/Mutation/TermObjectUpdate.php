<?php
namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WP_Taxonomy;
use WPGraphQL\AppContext;
use WPGraphQL\Data\TermObjectMutation;
use WPGraphQL\Utils\Utils;

/**
 * Class TermObjectUpdate
 *
 * @package WPGraphQL\Mutation
 */
class TermObjectUpdate {
	/**
	 * Registers the TermObjectUpdate mutation.
	 *
	 * @param \WP_Taxonomy $taxonomy The Taxonomy the mutation is registered for.
	 *
	 * @return void
	 */
	public static function register_mutation( WP_Taxonomy $taxonomy ) {
		$mutation_name = 'update' . ucwords( $taxonomy->graphql_single_name );
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
	 * @param \WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return array
	 */
	public static function get_input_fields( WP_Taxonomy $taxonomy ) {
		return array_merge(
			TermObjectCreate::get_input_fields( $taxonomy ),
			[
				'name' => [
					'type'        => 'String',
					// Translators: The placeholder is the name of the taxonomy for the object being mutated
					'description' => sprintf( __( 'The name of the %1$s object to mutate', 'wp-graphql' ), $taxonomy->name ),
				],
				'id'   => [
					'type'        => [
						'non_null' => 'ID',
					],
					// Translators: The placeholder is the taxonomy of the term being updated
					'description' => sprintf( __( 'The ID of the %1$s object to update', 'wp-graphql' ), $taxonomy->graphql_single_name ),
				],
			]
		);
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return array
	 */
	public static function get_output_fields( WP_Taxonomy $taxonomy ) {
		return TermObjectCreate::get_output_fields( $taxonomy );
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 * @param string       $mutation_name  The name of the mutation.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload( WP_Taxonomy $taxonomy, $mutation_name ) {
		return function ( $input, AppContext $context, ResolveInfo $info ) use ( $taxonomy, $mutation_name ) {
			$term_id = Utils::get_database_id_from_id( $input['id'] );

			/**
			 * Ensure the type for the Global ID matches the type being mutated
			 */
			if ( empty( $term_id ) ) {
				// Translators: The placeholder is the name of the taxonomy for the term being edited
				throw new UserError( sprintf( __( 'The ID passed is not for a %1$s object', 'wp-graphql' ), $taxonomy->graphql_single_name ) );
			}

			/**
			 * Get the existing term
			 */
			$existing_term = get_term( $term_id, $taxonomy->name );

			/**
			 * If there was an error getting the existing term, return the error message
			 */
			if ( ! $existing_term instanceof \WP_Term ) {
				if ( is_wp_error( $existing_term ) ) {
					$error_message = $existing_term->get_error_message();
					if ( ! empty( $error_message ) ) {
						throw new UserError( esc_html( $error_message ) );
					} else {
						// Translators: The placeholder is the name of the taxonomy for the term being deleted
						throw new UserError( sprintf( __( 'The %1$s node failed to update', 'wp-graphql' ), $taxonomy->name ) );
					}
				}

				// Translators: The placeholder is the name of the taxonomy for the term being deleted
				throw new UserError( sprintf( __( 'The %1$s node failed to update', 'wp-graphql' ), $taxonomy->name ) );
			}

			if ( $taxonomy->name !== $existing_term->taxonomy ) {
				// translators: The first placeholder is an ID and the second placeholder is the name of the post type being edited
				throw new UserError( sprintf( __( 'The id %1$d is not of the type "%2$s"', 'wp-graphql' ), $term_id, $taxonomy->name ) );
			}

			/**
			 * Ensure the user has permission to edit terms
			 */
			if ( ! current_user_can( 'edit_term', $existing_term->term_id ) ) {
				// Translators: The placeholder is the name of the taxonomy for the term being deleted
				throw new UserError( sprintf( __( 'You do not have permission to update %1$s', 'wp-graphql' ), $taxonomy->graphql_plural_name ) );
			}

			/**
			 * Prepare the $args for mutation
			 */
			$args = TermObjectMutation::prepare_object( $input, $taxonomy, $mutation_name );

			if ( ! empty( $args ) ) {

				/**
				 * Update the term
				 */
				$update = wp_update_term( $existing_term->term_id, $taxonomy->name, wp_slash( (array) $args ) );

				/**
				 * Respond with any errors
				 */
				if ( is_wp_error( $update ) ) {
					// Translators: the placeholder is the name of the taxonomy
					throw new UserError( sprintf( __( 'The %1$s failed to update', 'wp-graphql' ), $taxonomy->name ) );
				}
			}

			/**
			 * Fires an action when a term is updated via a GraphQL Mutation
			 *
			 * @param int         $term_id       The ID of the term object that was mutated
			 * @param \WP_Taxonomy $taxonomy The taxonomy of the term being updated
			 * @param array       $args          The args used to update the term
			 * @param string      $mutation_name The name of the mutation being performed (create, update, delete, etc)
			 * @param \WPGraphQL\AppContext $context The AppContext passed down the resolve tree
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the resolve tree
			 */
			do_action( 'graphql_update_term', $existing_term->term_id, $taxonomy, $args, $mutation_name, $context, $info );

			/**
			 * Fires an action when a term is updated via a GraphQL Mutation
			 *
			 * @param int         $term_id       The ID of the term object that was mutated
			 * @param array       $args          The args used to update the term
			 * @param string      $mutation_name The name of the mutation being performed (create, update, delete, etc)
			 * @param \WPGraphQL\AppContext $context The AppContext passed down the resolve tree
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the resolve tree
			 */
			do_action( "graphql_update_{$taxonomy->name}", $existing_term->term_id, $args, $mutation_name, $context, $info );

			/**
			 * Return the payload
			 */
			return [
				'termId' => $existing_term->term_id,
			];
		};
	}
}
