<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\ValidationRules\AllowDenyQueryDocument;
use GraphQL\Server\RequestError;

class Grant {

	const TAXONOMY_NAME = 'graphql_document_grant';

	// The string value used for the individual saved query
	const ALLOW                = 'allow';
	const DENY                 = 'deny';
	const USE_DEFAULT          = '';
	const NOT_SELECTED_DEFAULT = self::USE_DEFAULT;

	// The string value stored for the global admin setting
	const GLOBAL_ALLOWED = 'only_allowed';
	const GLOBAL_DENIED  = 'some_denied';
	const GLOBAL_PUBLIC  = 'public';
	const GLOBAL_DEFAULT = self::GLOBAL_PUBLIC; // The global admin setting default

	const GLOBAL_SETTING_NAME = 'grant_mode';

	/**
	 * @return void
	 */
	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			Document::TYPE_NAME,
			[
				'description'        => __( 'Allow/Deny access grant for a saved GraphQL query document', 'wp-graphql-smart-cache' ),
				'labels'             => [
					'name' => __( 'Allow/Deny', 'wp-graphql-smart-cache' ),
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
					'grant_input_box_cb',
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
					'type'        => 'String',
					'description' => __( 'Allow, deny or default access grant for specific query', 'wp-graphql-smart-cache' ),
				];

				register_graphql_field( 'Create' . $register_type_name . 'Input', 'grant', $config );
				register_graphql_field( 'Update' . $register_type_name . 'Input', 'grant', $config );

				$config['resolve'] = function ( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
					return self::getQueryGrantSetting( $post->ID );
				};
				register_graphql_field( $register_type_name, 'grant', $config );
			}
		);

		// Add to the wpgraphql server validation rules.
		// This filter allows us to add our validation rule to check a query for allow/deny access.
		add_filter( 'graphql_validation_rules', [ $this, 'add_validation_rules_cb' ], 10, 2 );

		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );
	}

	/**
	 * This runs on post create/update
	 * Check the grant allow/deny value is within limits
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

		if ( ! isset( $input['grant'] ) ) {
			return $input;
		}

		if ( ! in_array( $input['grant'], [ self::ALLOW, self::DENY, self::USE_DEFAULT ], true ) ) {
			// Translators: The placeholder is the input allow/deny value
			throw new RequestError( sprintf( __( 'Invalid value for allow/deny grant: "%s"', 'wp-graphql-smart-cache' ), $input['grant'] ) );
		}

		return $input;
	}

	/**
	 * This runs on post create/update
	 * Check the grant allow/deny value is within limits
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

		if ( ! isset( $filtered_input['grant'] ) || ! isset( $post_object['postObjectId'] ) ) {
			return;
		}

		$this->save( $post_object['postObjectId'], $filtered_input['grant'] );
	}

	/**
	 * Look up the allow/deny grant setting for a post
	 *
	 * @param int $post_id The post id
	 * @return string
	 */
	public static function getQueryGrantSetting( $post_id ) {
		$item = get_the_terms( $post_id, self::TAXONOMY_NAME );

		return ! is_wp_error( $item ) && isset( $item[0] ) && property_exists( $item[0], 'name' ) ? $item[0]->name : self::NOT_SELECTED_DEFAULT;
	}

	/**
	 * Use during processing of submitted form if value of selected input field is selected.
	 * And return value of the taxonomy.
	 *
	 * @param string $value The input form value
	 *
	 * @return string The string value used to save as the taxonomy value
	 */
	public function the_selection( $value ) {
		if ( in_array(
			$value,
			[
				self::ALLOW,
				self::DENY,
				self::USE_DEFAULT,
			],
			true
		) ) {
			return $value;
		}

		return self::USE_DEFAULT;
	}

	/**
	 * Save the data
	 *
	 * @param int    $post_id
	 * @param string $grant
	 * @return array|false|\WP_Error Array of term taxonomy IDs of affected terms. WP_Error or false on failure.
	 */
	public function save( $post_id, $grant ) {
		return wp_set_post_terms( $post_id, $grant, self::TAXONOMY_NAME );
	}

	/**
	 * Use graphql-php built in validation rules when a query is being requested.
	 * This allows the query to check access grant rules (allow/deny) and return correct error if
	 * needed.
	 *
	 * Return the validation rules to use in the request
	 *
	 * @param array   $validation_rules The validation rules to use in the request
	 * @param \WPGraphQL\Request $request The Request instance
	 * @return array
	 */
	public function add_validation_rules_cb( $validation_rules, $request ) {
		// Check the grant mode. If public for all, don't add this rule.
		$setting = get_graphql_setting( self::GLOBAL_SETTING_NAME, self::GLOBAL_DEFAULT, 'graphql_persisted_queries_section' );
		if ( self::GLOBAL_PUBLIC !== $setting && ! is_user_logged_in() ) {
			$validation_rules['allow_deny_query_document'] = new AllowDenyQueryDocument( $setting );
		}

		return $validation_rules;
	}
}
