<?php

namespace WPGraphQL\Server\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Rules\QuerySecurityRule;
use GraphQL\Validator\ValidationContext;
use WPGraphQL\AppContext;

/**
 * Class RequireAuthentication
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class RequireAuthentication extends QuerySecurityRule {

	/**
	 * @return bool
	 */
	protected function isEnabled() {

		$restrict_endpoint = null;

		/**
		 * Allows overriding the default graphql_restrict_endpoint behavior. Returning anything other
		 * than null will skip the default restrict checks.
		 *
		 * @param bool|null $restrict_endpoint Whether to restrict the endpoint. Defaults to null
		*/
		$restrict_endpoint = apply_filters( 'graphql_pre_restrict_endpoint', $restrict_endpoint );

		if ( null !== $restrict_endpoint ) {
			return (bool) $restrict_endpoint;
		}

		// Check to see if the endpoint should be restricted to logged in users
		$restrict_endpoint = get_graphql_setting( 'restrict_endpoint_to_logged_in_users' );

		if ( false === is_graphql_http_request() ) {
			return false;
		}

		if ( empty( $restrict_endpoint ) ) {
			return false;
		}

		if ( 'on' !== $restrict_endpoint ) {
			return false;
		}

		if ( null !== wp_get_current_user() && 0 !== wp_get_current_user()->ID ) {
			return false;
		}

		return true;
	}

	/**
	 * @param ValidationContext $context
	 *
	 * @return callable[]|mixed[]
	 */
	public function getVisitor( ValidationContext $context ) {

		$allowed_root_fields = [];

		/**
		 * Filters the allowed
		 *
		 * @param array             $allowed_root_fields The Root fields allowed to be requested without authentication
		 * @param ValidationContext $context             The Validation context of the field being executed.
		 */
		$allowed_root_fields = apply_filters( 'graphql_require_authentication_allowed_fields', $allowed_root_fields, $context );

		return $this->invokeIfNeeded(
			$context,
			[
				NodeKind::FIELD => static function ( FieldNode $node ) use ( $context, $allowed_root_fields ) {

					$parent_type = $context->getParentType();

					if ( ! $parent_type instanceof Type || empty( $parent_type->name ) ) {
						return;
					}

					if ( ! in_array( $parent_type->name, [ 'RootQuery', 'RootSubscription', 'RootMutation' ], true ) ) {
						return;
					}

					if ( empty( $allowed_root_fields ) || ! is_array( $allowed_root_fields ) || ! in_array( $node->name->value, $allowed_root_fields, true ) ) {
						$context->reportError(
							new Error(
								sprintf(
									__( 'The field "%s" cannot be accessed without authentication.', 'wp-graphql' ),
									$context->getParentType() . '.' . $node->name->value
								),
								//@phpstan-ignore-next-line
								[ $node ]
							)
						);
					}
				},
			]
		);
	}

}
