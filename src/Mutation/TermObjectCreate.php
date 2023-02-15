<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WP_Taxonomy;
use WPGraphQL\AppContext;
use WPGraphQL\Data\TermObjectMutation;

class TermObjectCreate {
	/**
	 * Registers the TermObjectCreate mutation.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return void
	 */
	public static function register_mutation( WP_Taxonomy $taxonomy ) {
		$mutation_name = 'create' . ucwords( $taxonomy->graphql_single_name );

		register_graphql_mutation(
			$mutation_name,
			[
				'inputFields'         => array_merge(
					self::get_input_fields( $taxonomy ),
					[
						'name' => [
							'type'        => [
								'non_null' => 'String',
							],
							// Translators: The placeholder is the name of the taxonomy for the object being mutated
							'description' => sprintf( __( 'The name of the %1$s object to mutate', 'wp-graphql' ), $taxonomy->name ),
						],
					]
				),
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
		$fields = [
			'aliasOf'     => [
				'type'        => 'String',
				// Translators: The placeholder is the name of the taxonomy for the object being mutated
				'description' => sprintf( __( 'The slug that the %1$s will be an alias of', 'wp-graphql' ), $taxonomy->name ),
			],
			'description' => [
				'type'        => 'String',
				// Translators: The placeholder is the name of the taxonomy for the object being mutated
				'description' => sprintf( __( 'The description of the %1$s object', 'wp-graphql' ), $taxonomy->name ),
			],
			'slug'        => [
				'type'        => 'String',
				'description' => __( 'If this argument exists then the slug will be checked to see if it is not an existing valid term. If that check succeeds (it is not a valid term), then it is added and the term id is given. If it fails, then a check is made to whether the taxonomy is hierarchical and the parent argument is not empty. If the second check succeeds, the term will be inserted and the term id will be given. If the slug argument is empty, then it will be calculated from the term name.', 'wp-graphql' ),
			],
		];

		/**
		 * Add a parentId field to hierarchical taxonomies to allow parents to be set
		 */
		if ( true === $taxonomy->hierarchical ) {
			$fields['parentId'] = [
				'type'        => 'ID',
				// Translators: The placeholder is the name of the taxonomy for the object being mutated
				'description' => sprintf( __( 'The ID of the %1$s that should be set as the parent', 'wp-graphql' ), $taxonomy->name ),
			];
		}

		return $fields;
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 *
	 * @return array
	 */
	public static function get_output_fields( WP_Taxonomy $taxonomy ) {
		return [
			$taxonomy->graphql_single_name => [
				'type'        => $taxonomy->graphql_single_name,
				// translators: Placeholder is the name of the taxonomy
				'description' => sprintf( __( 'The created %s', 'wp-graphql' ), $taxonomy->name ),
				'resolve'     => function ( $payload, $args, AppContext $context, ResolveInfo $info ) {
					$id = isset( $payload['termId'] ) ? absint( $payload['termId'] ) : null;

					return $context->get_loader( 'term' )->load_deferred( $id );

				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy type of the mutation.
	 * @param string      $mutation_name The name of the mutation.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload( WP_Taxonomy $taxonomy, string $mutation_name ) {
		return function ( $input, AppContext $context, ResolveInfo $info ) use ( $taxonomy, $mutation_name ) {

			/**
			 * Ensure the user can edit_terms
			 */
			if ( ! isset( $taxonomy->cap->edit_terms ) || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
				// translators: the $taxonomy->graphql_plural_name placeholder is the name of the object being mutated
				throw new UserError( sprintf( __( 'Sorry, you are not allowed to create %1$s', 'wp-graphql' ), $taxonomy->graphql_plural_name ) );
			}

			/**
			 * Prepare the object for insertion
			 */
			$args = TermObjectMutation::prepare_object( $input, $taxonomy, $mutation_name );

			/**
			 * Ensure a name was provided
			 */
			if ( empty( $args['name'] ) ) {
				// Translators: The placeholder is the name of the taxonomy of the term being mutated
				throw new UserError( sprintf( __( 'A name is required to create a %1$s', 'wp-graphql' ), $taxonomy->name ) );
			}

			$term_name = wp_slash( $args['name'] );

			if ( ! is_string( $term_name ) ) {
				throw new UserError( sprintf( __( 'A valid name is required to create a %1$s', 'wp-graphql' ), $taxonomy->name ) );
			}

			/**
			 * Insert the term
			 */
			$term = wp_insert_term( $term_name, $taxonomy->name, wp_slash( (array) $args ) );

			/**
			 * If it was an error, return the message as an exception
			 */
			if ( is_wp_error( $term ) ) {
				$error_message = $term->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				} else {
					throw new UserError( __( 'The object failed to update but no error was provided', 'wp-graphql' ) );
				}
			}

			/**
			 * If the response to creating the term didn't respond with a term_id, throw an exception
			 */
			if ( empty( $term['term_id'] ) ) {
				throw new UserError( __( 'The object failed to create', 'wp-graphql' ) );
			}

			/**
			 * Fires after a single term is created or updated via a GraphQL mutation
			 *
			 * @param int         $term_id       Inserted term object
			 * @param \WP_Taxonomy $taxonomy The taxonomy of the term being updated
			 * @param array       $args          The args used to insert the term
			 * @param string      $mutation_name The name of the mutation being performed
			 * @param \WPGraphQL\AppContext $context The AppContext passed down the resolve tree
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the resolve tree
			 */
			do_action( 'graphql_insert_term', $term['term_id'], $taxonomy, $args, $mutation_name, $context, $info );

			/**
			 * Fires after a single term is created or updated via a GraphQL mutation
			 *
			 * The dynamic portion of the hook name, `$taxonomy->name` refers to the taxonomy of the term being mutated
			 *
			 * @param int         $term_id       Inserted term object
			 * @param array       $args          The args used to insert the term
			 * @param string      $mutation_name The name of the mutation being performed
			 * @param \WPGraphQL\AppContext $context The AppContext passed down the resolve tree
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the resolve tree
			 */
			do_action( "graphql_insert_{$taxonomy->name}", $term['term_id'], $args, $mutation_name, $context, $info );

			return [
				'termId' => $term['term_id'],
			];
		};
	}
}
