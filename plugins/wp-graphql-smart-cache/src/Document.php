<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

use GraphQL\Server\OperationParams;
use WPGraphQL\SmartCache\Admin\Settings;
use GraphQL\Error\SyntaxError;
use GraphQL\Server\RequestError;

class Document {

	const TYPE_NAME           = 'graphql_document';
	const ALIAS_TAXONOMY_NAME = 'graphql_query_alias';
	const GRAPHQL_NAME        = 'graphqlDocument';

	/**
	 * @return void
	 */
	public function init() {
		add_filter( 'graphql_request_data', [ $this, 'graphql_query_contains_query_id_cb' ], 10, 2 );
		add_filter( 'graphql_execute_query_params', [ $this, 'graphql_execute_query_params_cb' ], 10, 2 );

		add_action( 'post_updated', [ $this, 'after_updated_cb' ], 10, 3 );

		if ( ! is_admin() ) {
			add_filter( 'wp_insert_post_data', [ $this, 'validate_and_pre_save_cb' ], 10, 2 );
			add_action( sprintf( 'save_post_%s', self::TYPE_NAME ), [ $this, 'save_document_cb' ], 10, 2 );
		}

		add_filter( 'graphql_post_object_insert_post_args', [ $this, 'mutation_filter_post_args' ], 10, 4 );
		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );

		// on delete of terms when the post type is deleted
		add_action( 'before_delete_post', [ $this, 'delete_post_cb' ], 10, 1 );

		register_post_type(
			'graphql_document',
			[
				'description'         => __( 'Saved GraphQL Documents', 'wp-graphql-smart-cache' ),
				'labels'              => [
					'name'          => __( 'GraphQL Documents', 'wp-graphql-smart-cache' ),
					'singular_name' => __( 'GraphQL Document', 'wp-graphql-smart-cache' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => Settings::show_in_admin(),
				'taxonomies'          => [
					self::ALIAS_TAXONOMY_NAME,
				],
				'show_in_graphql'     => true,
				'graphql_single_name' => self::GRAPHQL_NAME,
				'graphql_plural_name' => 'graphqlDocuments',
			]
		);

		register_taxonomy(
			self::ALIAS_TAXONOMY_NAME,
			self::TYPE_NAME,
			[
				'description'        => __( 'Alias names for saved GraphQL queries', 'wp-graphql-smart-cache' ),
				'hierarchical'       => false,
				'labels'             => [
					'name'          => __( 'Alias Names', 'wp-graphql-smart-cache' ),
					'singular_name' => __( 'Alias Name', 'wp-graphql-smart-cache' ),
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_admin_column'  => true,
				'show_in_menu'       => Settings::show_in_admin(),
				'show_ui'            => Settings::show_in_admin(),
				'show_in_quick_edit' => false,
				'show_in_graphql'    => false, // false because we register a field with different name
			]
		);

		add_action(
			'graphql_register_types',
			function () {
				$register_type_name = ucfirst( self::GRAPHQL_NAME );
				$config             = [
					'type'        => [ 'list_of' => [ 'non_null' => 'String' ] ],
					'description' => __( 'Alias names for saved GraphQL query documents', 'wp-graphql-smart-cache' ),
				];

				register_graphql_field( 'Create' . $register_type_name . 'Input', 'alias', $config );
				register_graphql_field( 'Update' . $register_type_name . 'Input', 'alias', $config );

				$config['resolve'] = function ( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
					$terms = get_the_terms( $post->ID, self::ALIAS_TAXONOMY_NAME );
					if ( ! is_array( $terms ) ) {
						return [];
					}
					return array_map(
						function ( $term ) {
							return $term->name;
						},
						$terms
					);
				};
				register_graphql_field( $register_type_name, 'alias', $config );
			}
		);
	}

	/**
	 * Run on mutation create/update.
	 *
	 * @param array        $insert_post_args The array of $input_post_args that will be passed to wp_insert_post
	 * @param array        $input            The data that was entered as input for the mutation
	 * @param \WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
	 * @param string       $mutation_name    The type of mutation being performed (create, edit, etc)
	 *
	 * @return array
	 */
	public function mutation_filter_post_args( $insert_post_args, $input, $post_type_object, $mutation_name ) {
		if ( in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			$insert_post_args = array_merge( $insert_post_args, $input );
		}
		return $insert_post_args;
	}

	/**
	 * This runs on post create/update
	 * Insert/Update the alias name. Make sure it is unique
	 * Filters the mutation input before it's passed to the `mutateAndGetPayload` callback.
	 *
	 * @param array                 $input The mutation input args.
	 * @param \WPGraphQL\AppContext $context The AppContext object.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
	 * @param string                $mutation_name The name of the mutation field.
	 *
	 * @return array
	 * @throws RequestError
	 */
	public function graphql_mutation_filter( $input, $context, $info, $mutation_name ) {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return $input;
		}

		if ( ! isset( $input['alias'] ) ) {
			return $input;
		}

		// If the create/update a document, see if any of these aliases already exist
		$existing_post = Utils::getPostByTermName( $input['alias'], self::TYPE_NAME, self::ALIAS_TAXONOMY_NAME );
		if ( $existing_post ) {
			// Translators: The placeholders are the input aliases and the existing post containing a matching alias
			throw new RequestError( sprintf( __( 'Alias "%1$s" already in use by another query "%2$s"', 'wp-graphql-smart-cache' ), join( ', ', $input['alias'] ), $existing_post->post_title ) );
		}

		// Make sure the normalized hash for the query string isset.
		$input['alias'][] = Utils::generateHash( $input['content'] );

		return $input;
	}

	/**
	 * Fires after the mutation payload has been returned from the `mutateAndGetPayload` callback.
	 *
	 * @param array $post_object The Payload returned from the mutation.
	 * @param array $filtered_input The mutation input args, after being filtered by 'graphql_mutation_input'.
	 * @param array $input The unfiltered input args of the mutation
	 * @param \WPGraphQL\AppContext $context The AppContext object.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
	 * @param string $mutation_name The name of the mutation field.
	 *
	 * @return void
	 */
	public function graphql_mutation_insert( $post_object, $filtered_input, $input, $context, $info, $mutation_name ) {
		if ( ! in_array( $mutation_name, [ 'createGraphqlDocument', 'updateGraphqlDocument' ], true ) ) {
			return;
		}

		if ( ! isset( $filtered_input['alias'] ) || ! isset( $post_object['postObjectId'] ) ) {
			return;
		}

		// Remove the existing/old alias terms before update
		$terms = wp_get_post_terms( $post_object['postObjectId'], self::ALIAS_TAXONOMY_NAME );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_remove_object_terms( $post_object['postObjectId'], $term->term_id, self::ALIAS_TAXONOMY_NAME );
				wp_delete_term( $term->term_id, self::ALIAS_TAXONOMY_NAME );
			}
		}

		wp_set_post_terms( $post_object['postObjectId'], $filtered_input['alias'], self::ALIAS_TAXONOMY_NAME );
	}

	/**
	 * Process request looking for when queryid and query are present.
	 * Save the query and remove it from the request.
	 *
	 * @param  array $parsed_body_params Request parameters.
	 * @param  array $request_context An array containing both body and query params.
	 *
	 * @return array Updated $parsed_body_params Request parameters.
	 * @throws RequestError
	 */
	public function graphql_query_contains_query_id_cb( $parsed_body_params, $request_context ) {

		// Normalize keys to handle both `queryId` and `queryid`.
		$query_id_key = isset( $parsed_body_params['queryId'] ) ? 'queryId' : ( isset( $parsed_body_params['queryid'] ) ? 'queryid' : null );

		// If both query and queryId/queryid are set
		if ( ! empty( $parsed_body_params['query'] ) && ! empty( $query_id_key ) ) {
			// Save the query
			$this->save( $parsed_body_params[ $query_id_key ], $parsed_body_params['query'] );

			// Remove it from processed body params so graphql-php operation proceeds without conflict.
			unset( $parsed_body_params['query'] );
		}

		// If the query is empty, but queryId/queryid is set
		if ( empty( $parsed_body_params['query'] ) && ! empty( $query_id_key ) ) {
			$query_string = $this->get( $parsed_body_params[ $query_id_key ] );
			if ( ! empty( $query_string ) ) {
				$parsed_body_params['query']           = $query_string;
				$parsed_body_params['originalQueryId'] = $parsed_body_params[ $query_id_key ];
				unset( $parsed_body_params[ $query_id_key ] );
			}
		}

		return $parsed_body_params;
	}

	/**
	 * During invoking 'graphql()', not as an HTTP request, if queryId is present, look it up and return the query string.
	 *
	 * @param string $query  The graphql query string.
	 * @param mixed|array|\GraphQL\Server\OperationParams $params  The graphql request params, potentially containing queryId.
	 *
	 * @return string|null
	 */
	public function graphql_execute_query_params_cb( $query, $params ) {
		$query_id = null;
		if ( empty( $query ) ) {
			// Check both camelCase and lowercase query ID
			if ( isset( $params->queryId ) ) {
				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$query_id = $params->queryId;
			} elseif ( is_array( $params ) ) {
				if ( isset( $params['queryid'] ) ) {
					$query_id = $params['queryid'];
				} elseif ( isset( $params['queryId'] ) ) {
					$query_id = $params['queryId'];
				}
			}

			if ( ! empty( $query_id ) ) {
				$query = $this->get( $query_id );
			}
		}
		return $query;
	}

	/**
	 * If existing post is edited, verify query string in content is valid graphql
	 *
	 * @param array $data             An array of slashed, sanitized, and processed post data.
	 * @param array $post             An array of sanitized (and slashed) but otherwise unmodified post data.
	 *
	 * @return array $data
	 * @throws RequestError
	 */
	public function validate_and_pre_save_cb( $data, $post ) {
		if ( self::TYPE_NAME !== $post['post_type'] ) {
			return $data;
		}

		if ( array_key_exists( 'post_content', $post ) ) {
			// Change the shape of the data
			$data['post_content'] = $this->valid_or_throw( $post['post_content'], $post['ID'] );
		}

		return $data;
	}

	/**
	 * @param string $post_content
	 * @param int    $post_id
	 * @return string post content
	 * @throws RequestError
	 */
	public function valid_or_throw( $post_content, $post_id ) {
		if ( empty( $post_content ) ) {
			return $post_content;
		}

		/**
		 * Before post is saved, check content for valid graphql.
		 */
		try {
			// Use graphql parser to check query string validity.
			// Because the data comes from form submission, comes with PHP characters escaped/slashed.
			$ast = \GraphQL\Language\Parser::parse( wp_unslash( $post_content ) );

			// Get post using the normalized hash of the query string. If not valid graphql, throws syntax error
			$normalized_hash = Utils::generateHash( $ast );

			// If queryId alias name is already in the system and doesn't match the query hash
			$existing_post = Utils::getPostByTermName( $normalized_hash, self::TYPE_NAME, self::ALIAS_TAXONOMY_NAME );
			if ( $existing_post && $existing_post->ID !== $post_id ) {
				// Translators: The placeholder is the existing saved query with matching hash/query-id
				throw new RequestError( sprintf( __( 'This query has already been associated with another query "%s"', 'wp-graphql-smart-cache' ), $existing_post->post_title ) );
			}

			// Format the query string and save that
			return \GraphQL\Language\Printer::doPrint( $ast );
		} catch ( SyntaxError $e ) {
			// Translators: The placeholder is the query string content
			throw new RequestError( sprintf( __( 'Invalid graphql query string "%s"', 'wp-graphql-smart-cache' ), $post_content ) );
		}
	}

	/**
	 * When wp_insert_post saves the query, update the slug to match the content.
	 *
	 * @param int $post_ID
	 * @param \WP_Post $post
	 *
	 * @return void
	 */
	public function save_document_cb( $post_ID, $post ) {
		if ( empty( $post->post_content ) ) {
			return;
		}

		$post->post_content = $this->valid_or_throw( $post->post_content, $post->ID );

		// Get the query id for the new query and save as a term
		// Verify the post content is valid graphql query document
		// Use graphql parser to check query string validity.
		// @throws on syntax error
		$query_id = Utils::generateHash( $post->post_content );

		// Set terms using wp_add_object_terms instead of wp_insert_post because the user my not have permissions to set terms
		wp_add_object_terms( $post_ID, $query_id, self::ALIAS_TAXONOMY_NAME );
	}

	/**
	 * If existing post is edited in the wp admin editor, use previous content to remove query term ids
	 *
	 * @param int     $post_ID      Post ID.
	 * @param \WP_Post $post_after   Post object following the update.
	 * @param \WP_Post $post_before  Post object before the update.
	 *
	 * @return void
	 */
	public function after_updated_cb( $post_ID, $post_after, $post_before ) {
		if ( self::TYPE_NAME !== $post_before->post_type ) {
			return;
		}

		// If the same hash, the query content hasn't changed, do not remove it.
		if ( $post_before->post_content === $post_after->post_content ) {
			return;
		}

		// Use graphql parser to check query string validity.
		try {
			// Get the existing normalized hash for this post and remove it before build a new on, only if the query has changed.
			// Because content is from WP_Post object, does not require explicit unslash here.
			$old_query_id = Utils::generateHash( $post_before->post_content );
		} catch ( SyntaxError $e ) {
			// syntax error in the old query, nothing to do here.
			return;
		}

		// If the old query string hash is assigned to this post, delete it
		$terms = wp_get_post_terms( $post_ID, self::ALIAS_TAXONOMY_NAME );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $old_query_id === $term->name ) {
					wp_remove_object_terms( $post_ID, $term->term_id, self::ALIAS_TAXONOMY_NAME );
					wp_delete_term( $term->term_id, self::ALIAS_TAXONOMY_NAME );
				}
			}
		}
	}

	/**
	 * Load a persisted query corresponding to a query ID (hash) or alias/alternate name
	 *
	 * @param  string $query_id Query ID
	 * @return string|null
	 */
	public function get( $query_id ) {
		$post = Utils::getPostByTermName( $query_id, self::TYPE_NAME, self::ALIAS_TAXONOMY_NAME );
		if ( false === $post || empty( $post->post_content ) ) {
			return null;
		}

		return $post->post_content;
	}

	/**
	 * Save a query by query ID (hash) or alias/alternate name
	 *
	 * @param string $query_id Query string str256 hash
	 * @param string $query  The graphql query string.
	 *
	 * @throws RequestError
	 *
	 * @return int post id
	 */
	public function save( $query_id, $query ) {
		// Get post using the normalized hash of the query string
		$ast             = \GraphQL\Language\Parser::parse( $query );
		$query           = \GraphQL\Language\Printer::doPrint( $ast );
		$normalized_hash = Utils::getHashFromFormattedString( $query );

		// If queryId alias name is already in the system and doesn't match the query hash
		$post = Utils::getPostByTermName( $query_id, self::TYPE_NAME, self::ALIAS_TAXONOMY_NAME );
		if ( $post && $post->post_name !== $normalized_hash ) {
			// translators: existing query title
			throw new RequestError( sprintf( __( 'This queryId has already been associated with another query "%s"', 'wp-graphql-smart-cache' ), $post->post_title ) );
		}

		// If the normalized query is associated with a saved document
		$post = Utils::getPostByTermName( $normalized_hash, self::TYPE_NAME, self::ALIAS_TAXONOMY_NAME );
		if ( empty( $post ) ) {
			$query_operation = \GraphQL\Utils\AST::getOperationAST( $ast );

			$operation_names = [];

			$definition_count = $ast->definitions->count();
			for ( $i = 0; $i < $definition_count; $i++ ) {
				$node              = $ast->definitions->offsetGet( $i );
				$operation_names[] = isset( $node->name->value ) ? $node->name->value : __( 'A Persisted Query', 'wp-graphql-smart-cache' );
			}
			$data = [
				'post_content' => \GraphQL\Language\Printer::doPrint( $ast ),
				'post_name'    => $normalized_hash,
				'post_title'   => join( ', ', $operation_names ),
				'post_status'  => 'publish',
				'post_type'    => self::TYPE_NAME,
			];

			// The post ID on success. The value 0 or WP_Error on failure.
			$post_id = wp_insert_post( $data, true );
			if ( is_wp_error( $post_id ) ) {
				throw new RequestError( sprintf( __( 'Error save the document data for "%s"', 'wp-graphql-smart-cache' ), $normalized_hash ) );
			}
		} elseif ( $query !== $post->post_content ) {
			// If the hash for the query string loads a post with a different query string,
			// This means this hash was previously used as an alias for a query
			// translators: existing query title
			throw new RequestError( sprintf( __( 'This query has already been associated with another query "%s"', 'wp-graphql-smart-cache' ), $post->post_title ) );
		} else {
			$post_id = $post->ID;
		}

		// Save the term entries for normalized hash and if provided query id is different
		$term_names = [ $normalized_hash ];

		// If provided query_id hash is different than normalized hash, save the term associated with the hierarchy
		if ( $query_id !== $normalized_hash ) {
			$term_names[] = $query_id;
		}

		// Set terms using wp_add_object_terms instead of wp_insert_post because the user my not have permissions to set terms
		wp_add_object_terms( $post_id, $term_names, self::ALIAS_TAXONOMY_NAME );

		return $post_id;
	}

	/**
	 * When a saved query post type is deleted, also delete the data for the other information.
	 *
	 * @param int $post_id the Post Object Id
	 * @return void
	 */
	public function delete_post_cb( $post_id ) {
		if ( self::TYPE_NAME === get_post_type( $post_id ) ) {
			$this->delete_term( $post_id );
		}
	}

	/**
	 * When a saved query post type is deleted, also delete the taxonomies.
	 *
	 * @param int $post_id the Post Object Id
	 * @return void
	 */
	public function delete_term( $post_id ) {
		$terms = wp_get_object_terms( $post_id, self::ALIAS_TAXONOMY_NAME );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, self::ALIAS_TAXONOMY_NAME );
			}
		}
	}
}
