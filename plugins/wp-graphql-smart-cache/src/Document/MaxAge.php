<?php
/**
 * The max age admin and filter for individual query documents.
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Utils;
use GraphQL\Server\RequestError;

class MaxAge {
	const TAXONOMY_NAME = 'graphql_document_http_maxage';

	/**
	 * The in-progress query(s)
	 *
	 * @var array
	 */
	public $query_ids = [];

	/**
	 * @return void
	 */
	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			Document::TYPE_NAME,
			[
				'description'        => __( 'HTTP Cache-Control max-age directive for a saved GraphQL document', 'wp-graphql-smart-cache' ),
				'labels'             => [
					'name' => __( 'Max-Age Header', 'wp-graphql-smart-cache' ),
				],
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => false,
				'show_admin_column'  => true,
				'show_in_menu'       => Settings::show_in_admin(),
				'show_ui'            => Settings::show_in_admin(),
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [
					'WPGraphQL\SmartCache\Admin\Editor',
					'maxage_input_box_cb',
				],
				'show_in_graphql'    => false,
				// false because we register a field with different name
			]
		);

		add_action(
			'graphql_register_types',
			function () {
				$register_type_name = ucfirst( Document::GRAPHQL_NAME );
				$config             = [
					'type'        => 'Int',
					'description' => __( 'HTTP Cache-Control max-age directive for a saved GraphQL document', 'wp-graphql-smart-cache' ),
				];

				register_graphql_field( 'Create' . $register_type_name . 'Input', 'max_age_header', $config );
				register_graphql_field( 'Update' . $register_type_name . 'Input', 'max_age_header', $config );

				$config['resolve'] = function ( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
					$term = get_the_terms( $post->ID, self::TAXONOMY_NAME );

					return is_array( $term ) && $term[0] instanceof \WP_Term ? $term[0]->name : null;
				};
				register_graphql_field( $register_type_name, 'max_age_header', $config );
			}
		);

		// From WPGraphql Router
		add_filter( 'graphql_response_headers_to_send', [ $this, 'http_headers_cb' ], 10, 1 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'peek_at_executing_query_cb' ], 10, 2 );

		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );
	}

	/**
	 * This runs on post create/update
	 * Check the max age value is within limits
	 *
	 * @param array $input The mutation input args.
	 * @param \WPGraphQL\AppContext $context The AppContext object.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
	 * @param string $mutation_name The name of the mutation field.
	 *
	 * @return array
	 */
	public function graphql_mutation_filter( $input, $context, $info, $mutation_name ) {
		if ( ! in_array(
			$mutation_name,
			[
				'createGraphqlDocument',
				'updateGraphqlDocument',
			],
			true
		) ) {
			return $input;
		}

		if ( ! isset( $input['maxAgeHeader'] ) ) {
			return $input;
		}

		return $input;
	}

	/**
	 * This runs on post create/update
	 * Check the max age value is within limits
	 *
	 * @param array $post_object The Payload returned from the mutation.
	 * @param array $filtered_input The mutation input args, after being filtered by 'graphql_mutation_input'.
	 * @param array $input The unfiltered input args of the mutation
	 * @param \WPGraphQL\AppContext $context The AppContext object.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
	 * @param string $mutation_name The name of the mutation field.
	 *
	 * @return void
	 **/
	public function graphql_mutation_insert( $post_object, $filtered_input, $input, $context, $info, $mutation_name ) {
		if ( ! in_array(
			$mutation_name,
			[
				'createGraphqlDocument',
				'updateGraphqlDocument',
			],
			true
		) ) {
			return;
		}

		if ( ! isset( $filtered_input['maxAgeHeader'] ) || ! isset( $post_object['postObjectId'] ) ) {
			return;
		}

		$this->save( $post_object['postObjectId'], $filtered_input['maxAgeHeader'] );
	}

	/**
	 * Get the max age if it exists for a saved persisted query
	 *
	 * @param int $post_id
	 * @return \WP_Error|string|null
	 */
	public function get( $post_id ) {
		$item = get_the_terms( $post_id, self::TAXONOMY_NAME );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		if ( ! $item || ! property_exists( $item[0], 'name' ) ) {
			return null;
		}
		return $item[0]->name;
	}

	/**
	 * Verify the max age value is acceptable
	 *
	 * @param string $value
	 * @return bool
	 */
	public function valid( $value ) {
		// TODO: terms won't save 0, as considers that empty and removes the term. Consider 'zero' or 'stale' or greater than zero.
		return ( is_numeric( $value ) && $value >= 0 );
	}

	/**
	 * Save the data
	 *
	 * @param int $post_id
	 * @param string $value
	 * @return array|false|\WP_Error Array of term taxonomy IDs of affected terms. WP_Error or false on failure.
	 */
	public function save( $post_id, $value ) {
		if ( ! $this->valid( $value ) ) {
			// Translators: The placeholder is the max-age-header input value
			throw new RequestError( sprintf( __( 'Invalid max age header value "%s". Must be greater than or equal to zero', 'wp-graphql-smart-cache' ), $value ) );
		}

		return wp_set_post_terms( $post_id, $value, self::TAXONOMY_NAME );
	}

	/**
	 * @param mixed|array|object $result The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 * @param \WPGraphQL\Request $request
	 * @return mixed|array|object
	 */
	public function peek_at_executing_query_cb( $result, $request ) {
		// For batch request, params are an array for each query/queryId in the batch
		$params = [];
		if ( is_array( $request->params ) ) {
			$params = $request->params;
		} else {
			$params[] = $request->params;
		}

		foreach ( $params as $req ) {
			//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( isset( $req->queryId ) ) {
				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$this->query_ids[] = $req->queryId;
			} elseif ( isset( $req->query ) ) {
				$this->query_ids[] = Utils::generateHash( $req->query );
			}
		}

		return $result;
	}

	/**
	 * @param array $headers
	 * @return array
	 */
	public function http_headers_cb( $headers ) {
		$age = null;

		// Look up this specific request query. If found and has an individual max-age setting, use it.
		// For batch queries, look up and use the smallest/shortest max-age selection.
		foreach ( $this->query_ids as $query_id ) {
			$post = Utils::getPostByTermName( $query_id, Document::TYPE_NAME, Document::ALIAS_TAXONOMY_NAME );
			if ( $post ) {
				// If this saved query has a specified max-age, use it. Make sure to keep the smallest value.
				$value = $this->get( $post->ID );
				if ( $value ) {
					$age = ( null === $age ) ? $value : min( $age, $value );
				}
			}
		}

		if ( null === $age ) {
			// If not, use a global max-age setting if set.
			$age = get_graphql_setting( 'global_max_age', null, 'graphql_cache_section' );
		}

		// Cache-Control max-age directive should be a positive integer, no decimals.
		// A value of zero indicates that caching should be disabled.
		if ( $this->valid( $age ) ) {
			if ( 0 === $age ) {
				$headers['Cache-Control'] = 'no-store';
			} else {
				$headers['Cache-Control'] = sprintf( 'max-age=%1$s, s-maxage=%1$s, must-revalidate', intval( $age ) );
			}
		}

		return $headers;
	}
}
