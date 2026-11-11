<?php

namespace WPGraphQL\Data;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;
use WP_Taxonomy;

class TermObjectMutation {

	/**
	 * This prepares the object to be mutated - ensures data is safe to be saved,
	 * and mapped from input args to WordPress $args
	 *
	 * @throws \GraphQL\Error\UserError User error for invalid term.
	 *
	 * @param array<string,mixed> $input         The input from the GraphQL Request
	 * @param \WP_Taxonomy        $taxonomy The Taxonomy object for the type of term being mutated
	 * @param string              $mutation_name The name of the mutation (create, update, etc)
	 *
	 * @return array<string,mixed>
	 */
	public static function prepare_object( array $input, WP_Taxonomy $taxonomy, string $mutation_name ) {
		$insert_args = [];

		/**
		 * Set the taxonomy for insert
		 */
		$insert_args['taxonomy'] = $taxonomy->name;

		/**
		 * Prepare the data for inserting the term
		 */
		if ( ! empty( $input['aliasOf'] ) ) {
			$insert_args['alias_of'] = $input['aliasOf'];
		}

		if ( ! empty( $input['name'] ) ) {
			$insert_args['name'] = $input['name'];
		}

		if ( ! empty( $input['description'] ) ) {
			$insert_args['description'] = $input['description'];
		}

		if ( ! empty( $input['slug'] ) ) {
			$insert_args['slug'] = $input['slug'];
		}

		/**
		 * If both parentId and parentDatabaseId are provided, throw an error
		 */
		if ( isset( $input['parentId'] ) && isset( $input['parentDatabaseId'] ) ) {
			throw new UserError( esc_html__( 'Please provide only one of parentId or parentDatabaseId', 'wp-graphql' ) );
		}

		/**
		 * Handle parentId input
		 */
		if ( isset( $input['parentId'] ) ) {
			/**
			 * Convert parent ID to WordPress ID
			 */
			$parent_id = Utils::get_database_id_from_id( $input['parentId'] );

			if ( false === $parent_id ) {
				throw new UserError( esc_html__( 'The parent ID is not a valid ID', 'wp-graphql' ) );
			}

			/**
			 * Ensure there's actually a parent term to be associated with
			 */
			$parent_term = get_term( absint( $parent_id ), $taxonomy->name );

			if ( 0 !== $parent_id && ! $parent_term instanceof \WP_Term ) {
				throw new UserError( esc_html__( 'The parent does not exist', 'wp-graphql' ) );
			}

			$insert_args['parent'] = 0 !== $parent_id ? $parent_term->term_id : 0;
		}

		/**
		 * Handle parentDatabaseId input
		 */
		if ( isset( $input['parentDatabaseId'] ) ) {
			$parent_id = absint( $input['parentDatabaseId'] );

			// If parent_id is not 0, verify the term exists
			if ( 0 !== $parent_id ) {
				$parent_term = get_term( $parent_id, $taxonomy->name );

				if ( ! $parent_term instanceof \WP_Term ) {
					throw new UserError( esc_html__( 'The parent does not exist', 'wp-graphql' ) );
				}
			}

			$insert_args['parent'] = $parent_id;
		}

		/**
		 * Filter the $insert_args
		 *
		 * @param array<string,mixed> $insert_args   The array of input args that will be passed to the functions that insert terms
		 * @param array<string,mixed> $input         The data that was entered as input for the mutation
		 * @param \WP_Taxonomy        $taxonomy      The taxonomy object of the term being mutated
		 * @param string              $mutation_name The name of the mutation being performed (create, edit, etc)
		 *
		 * @hookGroup models
		 * @since 0.0.5
		 */
		return apply_filters( 'graphql_term_object_insert_term_args', $insert_args, $input, $taxonomy, $mutation_name );
	}

	/**
	 * Fires the action that lets extensions update additional data related to a term object,
	 * such as termmeta, after the term has been created or updated.
	 *
	 * @param int                                  $term_id       The ID of the term being mutated
	 * @param array<string,mixed>                  $input         The input for the mutation
	 * @param \WP_Taxonomy                         $taxonomy      The taxonomy of the term being mutated
	 * @param string                               $mutation_name The name of the mutation (ex: createCategory, updateTag)
	 * @param \WPGraphQL\AppContext                $context       The AppContext passed down to all resolvers
	 * @param \GraphQL\Type\Definition\ResolveInfo $info          The ResolveInfo passed down to all resolvers
	 *
	 * @return void
	 *
	 * @since 2.27.0
	 */
	public static function update_additional_term_object_data( int $term_id, array $input, WP_Taxonomy $taxonomy, string $mutation_name, AppContext $context, ResolveInfo $info ) {

		/**
		 * Fires after a term has been created or updated via a GraphQL mutation. This is a great spot to
		 * hook into to update additional data related to terms, such as termmeta or relationships, using
		 * the mutation input.
		 *
		 * @param int                                  $term_id       The ID of the term being mutated
		 * @param array<string,mixed>                  $input         The input for the mutation
		 * @param \WP_Taxonomy                         $taxonomy      The taxonomy of the term being mutated
		 * @param string                               $mutation_name The name of the mutation (ex: createCategory, updateTag)
		 * @param \WPGraphQL\AppContext                $context       The AppContext passed down to all resolvers
		 * @param \GraphQL\Type\Definition\ResolveInfo $info          The ResolveInfo passed down to all resolvers
		 *
		 * @hookGroup models
		 * @since 2.27.0
		 */
		do_action( 'graphql_term_object_mutation_update_additional_data', $term_id, $input, $taxonomy, $mutation_name, $context, $info );
	}
}
