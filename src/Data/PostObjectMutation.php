<?php

namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;
use WP_Post_Type;

/**
 * Class PostObjectMutation
 *
 * @package WPGraphQL\Type\PostObject
 */
class PostObjectMutation {

	/**
	 * This handles inserting the post object
	 *
	 * @param array<string,mixed> $input            The input for the mutation
	 * @param \WP_Post_Type       $post_type_object The post_type_object for the type of post being mutated
	 * @param string              $mutation_name    The name of the mutation being performed
	 *
	 * @return array<string,mixed>
	 * @throws \Exception
	 */
	public static function prepare_post_object( $input, $post_type_object, $mutation_name ) {
		$insert_post_args = [];

		/**
		 * Set the post_type for the insert
		 */
		$insert_post_args['post_type'] = $post_type_object->name;

		/**
		 * Prepare the data for inserting the post
		 * NOTE: These are organized in the same order as: https://developer.wordpress.org/reference/functions/wp_insert_post/
		 */
		if ( ! empty( $input['authorId'] ) ) {
			$insert_post_args['post_author'] = Utils::get_database_id_from_id( $input['authorId'] );
		}

		if ( ! empty( $input['date'] ) && false !== strtotime( $input['date'] ) ) {
			$insert_post_args['post_date'] = gmdate( 'Y-m-d H:i:s', strtotime( $input['date'] ) );
		}

		if ( ! empty( $input['content'] ) ) {
			$insert_post_args['post_content'] = $input['content'];
		}

		if ( ! empty( $input['title'] ) ) {
			$insert_post_args['post_title'] = $input['title'];
		}

		if ( ! empty( $input['excerpt'] ) ) {
			$insert_post_args['post_excerpt'] = $input['excerpt'];
		}

		if ( ! empty( $input['status'] ) ) {
			$insert_post_args['post_status'] = $input['status'];
		}

		if ( ! empty( $input['commentStatus'] ) ) {
			$insert_post_args['comment_status'] = $input['commentStatus'];
		}

		if ( ! empty( $input['pingStatus'] ) ) {
			$insert_post_args['ping_status'] = $input['pingStatus'];
		}

		if ( ! empty( $input['password'] ) ) {
			$insert_post_args['post_password'] = $input['password'];
		}

		if ( ! empty( $input['slug'] ) ) {
			$insert_post_args['post_name'] = $input['slug'];
		}

		if ( ! empty( $input['toPing'] ) ) {
			$insert_post_args['to_ping'] = $input['toPing'];
		}

		if ( ! empty( $input['pinged'] ) ) {
			$insert_post_args['pinged'] = $input['pinged'];
		}

		if ( ! empty( $input['parentId'] ) ) {
			$insert_post_args['post_parent'] = Utils::get_database_id_from_id( $input['parentId'] );
		}

		if ( ! empty( $input['menuOrder'] ) ) {
			$insert_post_args['menu_order'] = $input['menuOrder'];
		}

		if ( ! empty( $input['mimeType'] ) ) {
			$insert_post_args['post_mime_type'] = $input['mimeType'];
		}

		if ( ! empty( $input['commentCount'] ) ) {
			$insert_post_args['comment_count'] = $input['commentCount'];
		}

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array<string,mixed> $insert_post_args The array of $input_post_args that will be passed to wp_insert_post
		 * @param array<string,mixed> $input            The data that was entered as input for the mutation
		 * @param \WP_Post_Type       $post_type_object The post_type_object that the mutation is affecting
		 * @param string              $mutation_type    The type of mutation being performed (create, edit, etc)
		 */
		$insert_post_args = apply_filters( 'graphql_post_object_insert_post_args', $insert_post_args, $input, $post_type_object, $mutation_name );

		/**
		 * Return the $args
		 */
		return $insert_post_args;
	}

	/**
	 * This updates additional data related to a post object, such as postmeta, term relationships,
	 * etc.
	 *
	 * @param int                                  $post_id              The ID of the postObject being mutated
	 * @param array<string,mixed>                  $input                The input for the mutation
	 * @param \WP_Post_Type                        $post_type_object     The Post Type Object for the type of post being mutated
	 * @param string                               $mutation_name        The name of the mutation (ex: create, update, delete)
	 * @param \WPGraphQL\AppContext                $context              The AppContext passed down to all resolvers
	 * @param \GraphQL\Type\Definition\ResolveInfo $info                 The ResolveInfo passed down to all resolvers
	 * @param string                               $default_post_status  The default status posts should use if an intended status wasn't set
	 * @param string                               $intended_post_status The intended post_status the post should have according to the mutation input
	 *
	 * @return void
	 */
	public static function update_additional_post_object_data( $post_id, $input, $post_type_object, $mutation_name, AppContext $context, ResolveInfo $info, $default_post_status = null, $intended_post_status = null ) {

		/**
		 * Sets the post lock
		 *
		 * @param bool                                 $is_locked            Whether the post is locked
		 * @param int                                  $post_id              The ID of the postObject being mutated
		 * @param array<string,mixed>                  $input                The input for the mutation
		 * @param \WP_Post_Type                        $post_type_object The Post Type Object for the type of post being mutated
		 * @param string                               $mutation_name        The name of the mutation (ex: create, update, delete)
		 * @param \WPGraphQL\AppContext                $context The AppContext passed down to all resolvers
		 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down to all resolvers
		 * @param ?string                              $intended_post_status The intended post_status the post should have according to the mutation input
		 * @param ?string                              $default_post_status  The default status posts should use if an intended status wasn't set
		 */
		if ( true === apply_filters( 'graphql_post_object_mutation_set_edit_lock', true, $post_id, $input, $post_type_object, $mutation_name, $context, $info, $default_post_status, $intended_post_status ) ) {
			/**
			 * Set the post_lock for the $new_post_id
			 */
			self::set_edit_lock( $post_id );
		}

		/**
		 * Update the _edit_last field
		 */
		update_post_meta( $post_id, '_edit_last', get_current_user_id() );

		/**
		 * Update the postmeta fields
		 */
		if ( ! empty( $input['desiredSlug'] ) ) {
			update_post_meta( $post_id, '_wp_desired_post_slug', $input['desiredSlug'] );
		}

		/**
		 * Set the object terms
		 *
		 * @param int                 $post_id          The ID of the postObject being mutated
		 * @param array<string,mixed> $input            The input for the mutation
		 * @param \WP_Post_Type       $post_type_object The Post Type Object for the type of post being mutated
		 * @param string              $mutation_name    The name of the mutation (ex: create, update, delete)
		 */
		self::set_object_terms( $post_id, $input, $post_type_object, $mutation_name );

		/**
		 * Run an action after the additional data has been updated. This is a great spot to hook into to
		 * update additional data related to postObjects, such as setting relationships, updating additional postmeta,
		 * or sending emails to Kevin. . .whatever you need to do with the postObject.
		 *
		 * @param int                                  $post_id              The ID of the postObject being mutated
		 * @param array<string,mixed>                  $input                The input for the mutation
		 * @param \WP_Post_Type                        $post_type_object     The Post Type Object for the type of post being mutated
		 * @param string                               $mutation_name        The name of the mutation (ex: create, update, delete)
		 * @param \WPGraphQL\AppContext                $context              The AppContext passed down to all resolvers
		 * @param \GraphQL\Type\Definition\ResolveInfo $info                 The ResolveInfo passed down to all resolvers
		 * @param ?string                              $intended_post_status The intended post_status the post should have according to the mutation input
		 * @param ?string                              $default_post_status  The default status posts should use if an intended status wasn't set
		 */
		do_action( 'graphql_post_object_mutation_update_additional_data', $post_id, $input, $post_type_object, $mutation_name, $context, $info, $default_post_status, $intended_post_status );

		/**
		 * Sets the post lock
		 *
		 * @param bool                                 $is_locked            Whether the post is locked.
		 * @param int                                  $post_id              The ID of the postObject being mutated
		 * @param array<string,mixed>                  $input                The input for the mutation
		 * @param \WP_Post_Type                        $post_type_object     The Post Type Object for the type of post being mutated
		 * @param string                               $mutation_name        The name of the mutation (ex: create, update, delete)
		 * @param \WPGraphQL\AppContext                $context              The AppContext passed down to all resolvers
		 * @param \GraphQL\Type\Definition\ResolveInfo $info                 The ResolveInfo passed down to all resolvers
		 * @param ?string                              $intended_post_status The intended post_status the post should have according to the mutation input
		 * @param ?string                              $default_post_status  The default status posts should use if an intended status wasn't set
		 *
		 * @return bool
		 */
		if ( true === apply_filters( 'graphql_post_object_mutation_set_edit_lock', true, $post_id, $input, $post_type_object, $mutation_name, $context, $info, $default_post_status, $intended_post_status ) ) {
			/**
			 * Set the post_lock for the $new_post_id
			 */
			self::remove_edit_lock( $post_id );
		}
	}

	/**
	 * Given a $post_id and $input from the mutation, check to see if any term associations are
	 * being made, and properly set the relationships
	 *
	 * @param int                 $post_id           The ID of the postObject being mutated
	 * @param array<string,mixed> $input             The input for the mutation
	 * @param \WP_Post_Type       $post_type_object The Post Type Object for the type of post being mutated
	 * @param string              $mutation_name     The name of the mutation (ex: create, update, delete)
	 *
	 * @return void
	 */
	protected static function set_object_terms( int $post_id, array $input, WP_Post_Type $post_type_object, string $mutation_name ) {

		/**
		 * Fire an action before setting object terms during a GraphQL Post Object Mutation.
		 *
		 * One example use for this hook would be to create terms from the input that may not exist yet, so that they can be set as a relation below.
		 *
		 * @param int                 $post_id          The ID of the postObject being mutated
		 * @param array<string,mixed> $input            The input for the mutation
		 * @param \WP_Post_Type       $post_type_object The Post Type Object for the type of post being mutated
		 * @param string              $mutation_name    The name of the mutation (ex: create, update, delete)
		 */
		do_action( 'graphql_post_object_mutation_set_object_terms', $post_id, $input, $post_type_object, $mutation_name );

		/**
		 * Get the allowed taxonomies and iterate through them to find the term inputs to use for setting relationships.
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		foreach ( $allowed_taxonomies as $tax_object ) {

			/**
			 * If the taxonomy is in the array of taxonomies registered to the post_type
			 */
			if ( in_array( $tax_object->name, get_object_taxonomies( $post_type_object->name ), true ) ) {

				/**
				 * If there is input for the taxonomy, process it
				 */
				if ( isset( $input[ lcfirst( $tax_object->graphql_plural_name ) ] ) ) {
					$term_input = $input[ lcfirst( $tax_object->graphql_plural_name ) ];

					/**
					 * Default append to true, but allow input to set it to false.
					 */
					$append = ! isset( $term_input['append'] ) || false !== $term_input['append'];

					/**
					 * Start an array of terms to connect
					 */
					$terms_to_connect = [];

					/**
					 * Filter whether to allow terms to be created during a post mutation.
					 *
					 * If a post mutation includes term input for a term that does not already exist,
					 * this will allow terms to be created in order to connect the term to the post object,
					 * but if filtered to false, this will prevent the term that doesn't already exist
					 * from being created during the mutation of the post.
					 *
					 * @param bool         $allow_term_creation Whether new terms should be created during the post object mutation
					 * @param \WP_Taxonomy $tax_object          The Taxonomy object for the term being added to the Post Object
					 */
					$allow_term_creation = apply_filters( 'graphql_post_object_mutations_allow_term_creation', true, $tax_object );

					/**
					 * If there are nodes in the term_input
					 */
					if ( ! empty( $term_input['nodes'] ) && is_array( $term_input['nodes'] ) ) {
						foreach ( $term_input['nodes'] as $node ) {
							$term_exists = false;

							/**
							 * Handle the input for ID first.
							 */
							if ( ! empty( $node['id'] ) ) {
								if ( ! absint( $node['id'] ) ) {
									$id_parts = Relay::fromGlobalId( $node['id'] );

									if ( ! empty( $id_parts['id'] ) ) {
										$term_exists = get_term_by( 'id', absint( $id_parts['id'] ), $tax_object->name );
										if ( isset( $term_exists->term_id ) ) {
											$terms_to_connect[] = $term_exists->term_id;
										}
									}
								} else {
									$term_exists = get_term_by( 'id', absint( $node['id'] ), $tax_object->name );
									if ( isset( $term_exists->term_id ) ) {
										$terms_to_connect[] = $term_exists->term_id;
									}
								}

								/**
								 * Next, handle the input for slug if there wasn't an ID input
								 */
							} elseif ( ! empty( $node['slug'] ) ) {
								$sanitized_slug = sanitize_text_field( $node['slug'] );
								$term_exists    = get_term_by( 'slug', $sanitized_slug, $tax_object->name );
								if ( isset( $term_exists->term_id ) ) {
									$terms_to_connect[] = $term_exists->term_id;
								}
								/**
								 * If the input for the term isn't an existing term, check to make sure
								 * we're allowed to create new terms during a Post Object mutation
								 */
							}

							/**
							 * If no term exists so far, and terms are set to be allowed to be created
							 * during a post object mutation, create the term to connect based on the
							 * input
							 */
							if ( ! $term_exists && true === $allow_term_creation ) {

								/**
								 * If the current user cannot edit terms, don't create terms to connect
								 */
								if ( ! isset( $tax_object->cap->edit_terms ) || ! current_user_can( $tax_object->cap->edit_terms ) ) {
									return;
								}

								$created_term = self::create_term_to_connect( $node, $tax_object->name );

								if ( ! empty( $created_term ) ) {
									$terms_to_connect[] = $created_term;
								}
							}
						}
					}

					/**
					 * If the current user cannot edit terms, don't create terms to connect
					 */
					if ( ! isset( $tax_object->cap->assign_terms ) || ! current_user_can( $tax_object->cap->assign_terms ) ) {
						return;
					}

					if ( $append && 'category' === $tax_object->name ) {
						$default_category_id = absint( get_option( 'default_category' ) );
						if ( ! in_array( $default_category_id, $terms_to_connect, true ) ) {
							wp_remove_object_terms( $post_id, $default_category_id, 'category' );
						}
					}

					wp_set_object_terms( $post_id, $terms_to_connect, $tax_object->name, $append );
				}
			}
		}
	}

	/**
	 * Given an array of Term properties (slug, name, description, etc), create the term and return
	 * a term_id
	 *
	 * @param array<string,mixed> $node     The node input for the term
	 * @param string              $taxonomy The taxonomy the term input is for
	 *
	 * @return int $term_id The ID of the created term. 0 if no term was created.
	 */
	protected static function create_term_to_connect( $node, $taxonomy ) {
		$created_term   = [];
		$term_to_create = [];
		$term_args      = [];

		if ( ! empty( $node['name'] ) ) {
			$term_to_create['name'] = sanitize_text_field( $node['name'] );
		} elseif ( ! empty( $node['slug'] ) ) {
			$term_to_create['name'] = sanitize_text_field( $node['slug'] );
		}

		if ( ! empty( $node['slug'] ) ) {
			$term_args['slug'] = sanitize_text_field( $node['slug'] );
		}

		if ( ! empty( $node['description'] ) ) {
			$term_args['description'] = sanitize_text_field( $node['description'] );
		}

		/**
		 * @todo: consider supporting "parent" input in $term_args
		 */

		if ( isset( $term_to_create['name'] ) && ! empty( $term_to_create['name'] ) ) {
			$created_term = wp_insert_term( $term_to_create['name'], $taxonomy, $term_args );
		}

		if ( is_wp_error( $created_term ) ) {
			if ( isset( $created_term->error_data['term_exists'] ) ) {
				return $created_term->error_data['term_exists'];
			}

			return 0;
		}

		/**
		 * Return the created term, or 0
		 */
		return isset( $created_term['term_id'] ) ? absint( $created_term['term_id'] ) : 0;
	}

	/**
	 * This is a copy of the wp_set_post_lock function that exists in WordPress core, but is not
	 * accessible because that part of WordPress is never loaded for WPGraphQL executions
	 *
	 * Mark the post as currently being edited by the current user
	 *
	 * @param int $post_id ID of the post being edited.
	 *
	 * @return int[]|false Array of the lock time and user ID. False if the post does not exist, or
	 *                     there is no current user.
	 */
	public static function set_edit_lock( $post_id ) {
		$post    = get_post( $post_id );
		$user_id = get_current_user_id();

		if ( empty( $post ) ) {
			return false;
		}

		if ( 0 === $user_id ) {
			return false;
		}

		$now  = time();
		$lock = "$now:$user_id";
		update_post_meta( $post->ID, '_edit_lock', $lock );

		return [ $now, $user_id ];
	}

	/**
	 * Remove the edit lock for a post
	 *
	 * @param int $post_id ID of the post to delete the lock for
	 *
	 * @return bool
	 */
	public static function remove_edit_lock( int $post_id ) {
		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return false;
		}

		return delete_post_meta( $post->ID, '_edit_lock' );
	}

	/**
	 * Check the edit lock for a post
	 *
	 * @param false|int           $post_id ID of the post to delete the lock for
	 * @param array<string,mixed> $input             The input for the mutation
	 *
	 * @return false|int Return false if no lock or the user_id of the owner of the lock
	 */
	public static function check_edit_lock( $post_id, array $input ) {
		if ( false === $post_id ) {
			return false;
		}

		// If override the edit lock is set, return early
		if ( isset( $input['ignoreEditLock'] ) && true === $input['ignoreEditLock'] ) {
			return false;
		}

		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			// @phpstan-ignore requireOnce.fileNotFound
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		return wp_check_post_lock( $post_id );
	}
}
