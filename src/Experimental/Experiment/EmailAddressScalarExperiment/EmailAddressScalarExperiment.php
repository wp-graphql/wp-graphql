<?php
/**
 * Email Address Scalar Experiment
 *
 * Registers the EmailAddress scalar type for better email validation and type safety.
 *
 * @package WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment
 * @since 2.5.0
 */

namespace WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * Class - EmailAddressScalarExperiment
 *
 * Adds a custom EmailAddress scalar type that validates email addresses using
 * WordPress's built-in is_email() and sanitize_email() functions.
 *
 * When enabled, developers can use the EmailAddress scalar in their custom fields:
 *
 * ```php
 * register_graphql_field( 'User', 'customEmail', [
 *     'type' => 'EmailAddress',
 *     'resolve' => function( $user ) {
 *         return get_user_meta( $user->ID, 'custom_email', true );
 *     }
 * ] );
 * ```
 */
class EmailAddressScalarExperiment extends AbstractExperiment {
	/**
	 * {@inheritDoc}
	 */
	protected static function slug(): string {
		return 'email-address-scalar';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function config(): array {
		return [
			'title'       => __( 'Email Address Scalar', 'wp-graphql' ),
			'description' => __( 'Registers the EmailAddress scalar type for validating email addresses using WordPress\'s is_email() function. This provides better type safety and validation for email fields.', 'wp-graphql' ),
		];
	}

	/**
	 * Gets the activation message for this experiment.
	 *
	 * @return string The activation message.
	 */
	public function get_activation_message(): string {
		return __( 'Email Address Scalar experiment activated! The EmailAddress scalar type is now available for use in custom fields. See the documentation for usage examples.', 'wp-graphql' );
	}

	/**
	 * Gets the deactivation message for this experiment.
	 *
	 * @return string The deactivation message.
	 */
	public function get_deactivation_message(): string {
		return __( 'Email Address Scalar experiment deactivated. The EmailAddress scalar type has been removed from the schema. Any custom fields using this scalar will now fail validation.', 'wp-graphql' );
	}

	/**
	 * Initializes the experiment.
	 *
	 * Registers the EmailAddress scalar type to the GraphQL schema.
	 */
	protected function init(): void {
		add_action( 'graphql_register_types', [ $this, 'register_scalar' ] );
	}

	/**
	 * Registers the EmailAddress scalar type.
	 */
	public function register_scalar(): void {
		EmailAddress::register_scalar();
	}
}
