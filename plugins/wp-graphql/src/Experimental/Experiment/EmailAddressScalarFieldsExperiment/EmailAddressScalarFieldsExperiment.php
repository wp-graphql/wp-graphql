<?php
/**
 * Email Address Scalar Fields Experiment
 *
 * Adds emailAddress fields using the EmailAddress scalar to core WPGraphQL types.
 *
 * @package WPGraphQL\Experimental\Experiment\EmailAddressScalarFieldsExperiment
 * @since 2.5.0
 */

namespace WPGraphQL\Experimental\Experiment\EmailAddressScalarFieldsExperiment;

use GraphQL\Error\UserError;
use WPGraphQL\AppContext;
use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * Class - EmailAddressScalarFieldsExperiment
 *
 * Adds emailAddress fields to core types (User, Commenter, CommentAuthor, GeneralSettings)
 * and mutation inputs, providing better type safety and validation for email addresses
 * throughout the WPGraphQL schema.
 *
 * This experiment depends on the email-address-scalar experiment, which registers
 * the EmailAddress scalar type itself.
 */
class EmailAddressScalarFieldsExperiment extends AbstractExperiment {
	/**
	 * {@inheritDoc}
	 */
	protected static function slug(): string {
		return 'email-address-scalar-fields';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function config(): array {
		return [
			'title'       => __( 'Email Address Scalar Fields', 'wp-graphql' ),
			'description' => __( 'Adds emailAddress fields using the EmailAddress scalar to core WPGraphQL types including User, Commenter, CommentAuthor, and GeneralSettings. Also updates mutation inputs to accept EmailAddress values.', 'wp-graphql' ),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_dependencies(): array {
		return [
			'required' => [ 'email-address-scalar' ],
		];
	}

	/**
	 * Gets the activation message for this experiment.
	 *
	 * @return string The activation message.
	 */
	public function get_activation_message(): string {
		return __( 'Email Address Scalar Fields experiment activated! New emailAddress fields are now available on User, Commenter, CommentAuthor, and GeneralSettings types. Deprecated email fields remain functional for backward compatibility.', 'wp-graphql' );
	}

	/**
	 * Gets the deactivation message for this experiment.
	 *
	 * @return string The deactivation message.
	 */
	public function get_deactivation_message(): string {
		return __( 'Email Address Scalar Fields experiment deactivated. The emailAddress fields have been removed from the schema. Existing email String fields continue to work normally.', 'wp-graphql' );
	}

	/**
	 * Initializes the experiment.
	 *
	 * Registers fields and handles deprecated field management.
	 */
	protected function init(): void {
		add_action( 'graphql_register_types', [ $this, 'register_fields' ] );
		add_filter( 'graphql_input_fields', [ $this, 'add_deprecated_email_input_to_user_mutations' ], 10, 3 );
		add_filter( 'graphql_GeneralSettings_fields', [ $this, 'deprecate_general_settings_email_field' ], 10, 1 );
		add_filter( 'graphql_Commenter_fields', [ $this, 'deprecate_commenter_email_field' ], 10, 1 );
		add_filter( 'graphql_CommentAuthor_fields', [ $this, 'deprecate_comment_author_email_field' ], 10, 1 );
		add_filter( 'graphql_CommentToCommenterConnectionEdge_fields', [ $this, 'deprecate_comment_edge_email_field' ], 10, 1 );

		// Normalize email input for user mutations using the graphql_mutation_input filter
		add_filter( 'graphql_mutation_input', [ $this, 'normalize_email_input_for_user_mutations' ], 10, 4 );
	}

	/**
	 * Registers emailAddress fields to core types.
	 */
	public function register_fields(): void {
		// Register Commenter.emailAddress field (interface)
		// Note: User and CommentAuthor implement Commenter, so they automatically inherit this field
		register_graphql_field(
			'Commenter',
			'emailAddress',
			[
				'type'        => 'EmailAddress',
				'description' => static function () {
					return __( 'Email address of the comment author. Only visible to users with comment moderation privileges.', 'wp-graphql' );
				},
				'resolve'     => static function ( $source ) {
					// The emailAddress field resolves to the underlying email property
					return isset( $source->email ) ? $source->email : null;
				},
			]
		);

		// Register GeneralSettings.adminEmail field
		register_graphql_field(
			'GeneralSettings',
			'adminEmail',
			[
				'type'        => 'EmailAddress',
				'description' => static function () {
					return __( 'Email address of the site administrator. Only visible to users with administrative privileges.', 'wp-graphql' );
				},
				'resolve'     => static function ( $root, $args, $context, $info ) {
					if ( ! current_user_can( 'manage_options' ) ) {
						throw new UserError( esc_html__( 'Sorry, you do not have permission to view this setting.', 'wp-graphql' ) );
					}

					return get_option( 'admin_email' );
				},
			]
		);

		// Register CommentToCommenterConnectionEdge.emailAddress field
		register_graphql_field(
			'CommentToCommenterConnectionEdge',
			'emailAddress',
			[
				'type'        => 'EmailAddress',
				'description' => static function () {
					return __( 'The email address representing the author for this particular comment', 'wp-graphql' );
				},
				'resolve'     => static function ( $edge ) {
					return $edge['source']->commentAuthorEmail ?: null;
				},
			]
		);
	}

	/**
	 * Add deprecated email input field to user mutation input types.
	 *
	 * @param array<string,array<string,mixed>> $fields The input fields
	 * @param string                            $type_name The input type name
	 * @param array<string,mixed>               $config The input type config
	 * @return array<string,array<string,mixed>>
	 */
	public function add_deprecated_email_input_to_user_mutations( array $fields, string $type_name, array $config ): array {
		// List of user mutation input types that should have deprecated email field
		$user_mutation_inputs = [
			'CreateUserInput',
			'UpdateUserInput',
			'RegisterUserInput',
		];

		if ( ! in_array( $type_name, $user_mutation_inputs, true ) ) {
			return $fields;
		}

		// Add emailAddress field if it doesn't already exist
		if ( ! isset( $fields['emailAddress'] ) ) {
			// For RegisterUserInput, emailAddress should be non-null since email is required
			// For CreateUserInput and UpdateUserInput, it's optional
			$email_address_type = ( 'RegisterUserInput' === $type_name )
				? [ 'non_null' => 'EmailAddress' ]
				: 'EmailAddress';

			$fields['emailAddress'] = [
				'type'        => $email_address_type,
				'description' => static function () {
					return __( 'The user\'s email address.', 'wp-graphql' );
				},
			];
		}

		// Modify existing email field to add deprecation
		// Preserve the original type structure to avoid breaking schema changes
		// Use strings instead of callables to ensure deprecation shows in introspection
		if ( isset( $fields['email'] ) ) {
			// The base code now has RegisterUserInput.email as String (nullable) to match production schema
			// Our filter just needs to add the deprecation reason, preserving the existing type
			// Add deprecation reason for all user mutation inputs
			$fields['email']['deprecationReason'] = __( 'Deprecated in favor of the `emailAddress` field for better validation and type safety. This deprecation is part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version.', 'wp-graphql' );
		} else {
			// Add deprecated email field if it doesn't exist
			$fields['email'] = [
				'type'              => 'String',
				'description'       => __( 'A string containing the user\'s email address.', 'wp-graphql' ),
				'deprecationReason' => __( 'Deprecated in favor of the `emailAddress` field for better validation and type safety. This deprecation is part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version.', 'wp-graphql' ),
			];
		}

		return $fields;
	}

	/**
	 * Add deprecation to the existing GeneralSettings.email field.
	 *
	 * @param array<string,array<string,mixed>> $fields The GeneralSettings fields
	 * @return array<string,array<string,mixed>>
	 */
	public function deprecate_general_settings_email_field( array $fields ): array {
		if ( isset( $fields['email'] ) ) {
			// Use a string instead of callable to ensure deprecation shows in introspection
			// (prepare_config_for_introspection sets callable deprecationReason to null for non-introspection queries)
			$fields['email']['deprecationReason'] = __( 'Deprecated in favor of the `adminEmail` field for better validation and type safety. This deprecation is part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version.', 'wp-graphql' );

			// Wrap the existing resolver to add deprecation warning
			$original_resolve           = $fields['email']['resolve'] ?? null;
			$fields['email']['resolve'] = static function ( $root, $args, $context, $info ) use ( $original_resolve ) {
				// Log deprecation warning
				graphql_debug(
					__( 'WPGraphQL: The field "GeneralSettings.email" is deprecated as part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version. Use "GeneralSettings.adminEmail" instead.', 'wp-graphql' )
				);

				// Call the original resolver if it exists
				if ( is_callable( $original_resolve ) ) {
					return $original_resolve( $root, $args, $context, $info );
				}

				// Fallback resolver (same logic as original)
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new UserError( esc_html__( 'Sorry, you do not have permission to view this setting.', 'wp-graphql' ) );
				}

				return get_option( 'admin_email' );
			};
		}

		return $fields;
	}

	/**
	 * Add deprecation to the existing Commenter.email field.
	 *
	 * @param array<string,array<string,mixed>> $fields The Commenter fields
	 * @return array<string,array<string,mixed>>
	 */
	public function deprecate_commenter_email_field( array $fields ): array {
		if ( isset( $fields['email'] ) ) {
			// Use a string instead of callable to ensure deprecation shows in introspection
			$fields['email']['deprecationReason'] = __( 'Deprecated in favor of the `emailAddress` field for better validation and type safety. This deprecation is part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version.', 'wp-graphql' );

			// Wrap the existing resolver to add deprecation warning
			$original_resolve           = $fields['email']['resolve'] ?? null;
			$fields['email']['resolve'] = static function ( $source, $args, $context, $info ) use ( $original_resolve ) {
				// Log deprecation warning
				graphql_debug(
					__( 'WPGraphQL: The field "Commenter.email" is deprecated as part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version. Use "Commenter.emailAddress" instead.', 'wp-graphql' )
				);

				// Call the original resolver if it exists
				if ( is_callable( $original_resolve ) ) {
					return $original_resolve( $source, $args, $context, $info );
				}

				// Fallback: return the email property
				return isset( $source->email ) ? $source->email : null;
			};
		}

		return $fields;
	}

	/**
	 * Add deprecation to the existing CommentAuthor.email field.
	 *
	 * @param array<string,array<string,mixed>> $fields The CommentAuthor fields
	 * @return array<string,array<string,mixed>>
	 */
	public function deprecate_comment_author_email_field( array $fields ): array {
		if ( isset( $fields['email'] ) ) {
			// Use a string instead of callable to ensure deprecation shows in introspection
			$fields['email']['deprecationReason'] = __( 'Deprecated in favor of the `emailAddress` field for better validation and type safety. This deprecation is part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version.', 'wp-graphql' );

			// Wrap the existing resolver to add deprecation warning
			$original_resolve           = $fields['email']['resolve'] ?? null;
			$fields['email']['resolve'] = static function ( $source, $args, $context, $info ) use ( $original_resolve ) {
				// Log deprecation warning
				graphql_debug(
					__( 'WPGraphQL: The field "CommentAuthor.email" is deprecated as part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version. Use "CommentAuthor.emailAddress" instead.', 'wp-graphql' )
				);

				// Call the original resolver if it exists
				if ( is_callable( $original_resolve ) ) {
					return $original_resolve( $source, $args, $context, $info );
				}

				// Fallback: return the emailAddress property
				return isset( $source->emailAddress ) ? $source->emailAddress : null;
			};
		}

		return $fields;
	}

	/**
	 * Add deprecation to the existing CommentToCommenterConnectionEdge.email field.
	 *
	 * @param array<string,array<string,mixed>> $fields The CommentToCommenterConnectionEdge fields
	 * @return array<string,array<string,mixed>>
	 */
	public function deprecate_comment_edge_email_field( array $fields ): array {
		if ( isset( $fields['email'] ) ) {
			// Use a string instead of callable to ensure deprecation shows in introspection
			$fields['email']['deprecationReason'] = __( 'Deprecated in favor of the `emailAddress` field for better validation and type safety. This deprecation is part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version.', 'wp-graphql' );

			// Wrap the existing resolver to add deprecation warning
			$original_resolve           = $fields['email']['resolve'] ?? null;
			$fields['email']['resolve'] = static function ( $source, $args, $context, $info ) use ( $original_resolve ) {
				// Log deprecation warning
				graphql_debug(
					__( 'WPGraphQL: The connection edge field "email" is deprecated as part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version. Use "emailAddress" instead.', 'wp-graphql' )
				);

				// Call the original resolver if it exists
				if ( is_callable( $original_resolve ) ) {
					return $original_resolve( $source, $args, $context, $info );
				}

				// Fallback: return the commentAuthorEmail
				return $source['source']->commentAuthorEmail ?: null;
			};
		}

		return $fields;
	}

	/**
	 * Normalize email input for user mutations.
	 *
	 * Uses the graphql_mutation_input filter to normalize email/emailAddress input
	 * before the mutation's mutateAndGetPayload callback executes.
	 *
	 * @param array<string,mixed>                  $input         The mutation input
	 * @param \WPGraphQL\AppContext                $context       The AppContext
	 * @param \GraphQL\Type\Definition\ResolveInfo $info          The ResolveInfo
	 * @param string                               $mutation_name The mutation name
	 * @return array<string,mixed>
	 * @throws \GraphQL\Error\UserError If both email and emailAddress fields are provided.
	 */
	public function normalize_email_input_for_user_mutations( array $input, AppContext $context, $info, string $mutation_name ): array {
		// Only process user mutations
		$user_mutations = [ 'createUser', 'updateUser', 'registerUser' ];

		if ( ! in_array( $mutation_name, $user_mutations, true ) ) {
			return $input;
		}

		$has_email         = ! empty( $input['email'] );
		$has_email_address = ! empty( $input['emailAddress'] );

		// If both are provided, throw an error
		if ( $has_email && $has_email_address ) {
			throw new UserError(
				esc_html__( 'Cannot provide both "email" and "emailAddress" fields. Please use "emailAddress" as "email" is deprecated as part of the Email Address Scalar Fields experiment.', 'wp-graphql' )
			);
		}

		// If emailAddress is provided but email is not, copy emailAddress to email
		// so the core mutation can use it
		if ( $has_email_address && ! $has_email ) {
			$input['email'] = $input['emailAddress'];
		}

		// If deprecated email field is used, log deprecation warning
		if ( $has_email && ! $has_email_address ) {
			graphql_debug(
				__( 'WPGraphQL: The input field "email" is deprecated as part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version. Use "emailAddress" instead.', 'wp-graphql' )
			);
		}

		return $input;
	}

	/**
	 * Resolve email input from both deprecated 'email' and new 'emailAddress' fields.
	 *
	 * This is a static helper method that can be called from mutation resolvers.
	 *
	 * @param array<string,mixed> $input The mutation input
	 * @return string|null The resolved email value
	 * @throws \GraphQL\Error\UserError If both email fields are provided.
	 */
	public static function resolve_email_input( array $input ): ?string {
		$has_email         = ! empty( $input['email'] );
		$has_email_address = ! empty( $input['emailAddress'] );

		// If both are provided, throw an error
		if ( $has_email && $has_email_address ) {
			throw new UserError(
				esc_html__( 'Cannot provide both "email" and "emailAddress" fields. Please use "emailAddress" as "email" is deprecated as part of the Email Address Scalar Fields experiment.', 'wp-graphql' )
			);
		}

		// If deprecated email field is used, log deprecation warning
		if ( $has_email ) {
			graphql_debug(
				__( 'WPGraphQL: The input field "email" is deprecated as part of the Email Address Scalar Fields experiment. If the experiment graduates to core, this field would be deprecated in a future version. Use "emailAddress" instead.', 'wp-graphql' )
			);
			return $input['email'];
		}

		// Return emailAddress if provided
		if ( $has_email_address ) {
			return $input['emailAddress'];
		}

		return null;
	}
}
