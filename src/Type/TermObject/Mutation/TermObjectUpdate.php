<?php

namespace WPGraphQL\Type\TermObject\Mutation;

use GraphQLRelay\Relay;
use WPGraphQL\Types;

class TermObjectUpdate {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];

	/**
	 * Defines the update mutation for TermObjects
	 *
	 * @param \WP_Taxonomy $taxonomy
	 *
	 * @return array|mixed
	 */
	public static function mutate( \WP_Taxonomy $taxonomy ) {

		if ( ! empty( $taxonomy->graphql_single_name ) && empty( self::$mutation[ $taxonomy->graphql_single_name ] ) ) :

			$mutation_name = 'update' . ucwords( $taxonomy->graphql_single_name );

			self::$mutation[ $taxonomy->graphql_single_name ] = Relay::mutationWithClientMutationId( [
				'name'                => esc_html( $mutation_name ),
				// translators: The placeholder is the name of the post type being updated
				'description'         => sprintf( esc_html__( 'Updates %1$s objects', 'wp-graphql' ), $taxonomy->graphql_single_name ),
				'inputFields'         => self::input_fields( $taxonomy ),
				'outputFields'        => [
					$taxonomy->graphql_single_name => [
						'type'    => Types::term_object( $taxonomy->name ),
						'resolve' => function( $payload ) use ( $taxonomy ) {
							return get_term( $payload['postObjectId'], $taxonomy->name );
						},
					],
				],
				'mutateAndGetPayload' => function( $input ) use ( $taxonomy, $mutation_name ) {

				},
			] );

			return self::$mutation[ $taxonomy->graphql_single_name ];

		endif; // End if().

		return self::$mutation;

	}

	/**
	 * Add the id as an optional field for update mutations
	 *
	 * @param \WP_Taxonomy $taxonomy
	 *
	 * @return array
	 */
	private static function input_fields( $taxonomy ) {

		/**
		 * Add name as a non_null field for term creation
		 */
		return array_merge(
			[
				'name' => [
					'type'        => Types::string(),
					// Translators: The placeholder is the name of the taxonomy for the object being mutated
					'description' => sprintf( __( 'The name of the %1$s object to mutate', 'wp-graphql' ), $taxonomy->name ),
				],
			],
			TermObjectMutation::input_fields( $taxonomy )
		);

	}

}
