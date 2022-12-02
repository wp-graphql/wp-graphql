<?php

namespace WPGraphQL\Data;

use GraphQL\Error\UserError;
use WP_Taxonomy;
use WPGraphQL\Utils\Utils;

class TermObjectMutation {

	/**
	 * This prepares the object to be mutated - ensures data is safe to be saved,
	 * and mapped from input args to WordPress $args
	 *
	 * @throws UserError User error for invalid term.
	 * 
	 * @param array        $input         The input from the GraphQL Request
	 * @param WP_Taxonomy  $taxonomy      The Taxonomy object for the type of term being mutated
	 * @param string       $mutation_name The name of the mutation (create, update, etc)
	 *
	 * @return mixed
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
		 * If the parentId argument was entered, we need to validate that it's actually a legit term that can
		 * be set as a parent
		 */
		if ( ! empty( $input['parentId'] ) ) {

			/**
			 * Convert parent ID to WordPress ID
			 */
			$parent_id = Utils::get_database_id_from_id( $input['parentId'] );

			if ( empty( $parent_id ) ) {
				throw new UserError( __( 'The parent ID is not a valid ID', 'wp-graphql' ) );
			}

			/**
			 * Ensure there's actually a parent term to be associated with
			 */
			$parent_term = get_term( absint( $parent_id ), $taxonomy->name );

			if ( ! $parent_term instanceof \WP_Term ) {
				throw new UserError( __( 'The parent does not exist', 'wp-graphql' ) );
			}

			$insert_args['parent'] = $parent_term->term_id;
		}

		/**
		 * Filter the $insert_args
		 *
		 * @param array $insert_args The array of input args that will be passed to the functions that insert terms
		 * @param array $input The data that was entered as input for the mutation
		 * @param WP_Taxonomy $taxonomy The taxonomy object of the term being mutated
		 * @param string $mutation_name The name of the mutation being performed (create, edit, etc)
		 */
		return apply_filters( 'graphql_term_object_insert_term_args', $insert_args, $input, $taxonomy, $mutation_name );
	}
}
