<?php

namespace WPGraphQL\Server\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Rules\QuerySecurityRule;

/**
 * Class RequireAuthentication
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class RequireAuthentication extends QuerySecurityRule {

	/**
	 * Whether the rule is enabled or not.
	 */
	protected function isEnabled(): bool {
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
	 * {@inheritDoc}
	 *
	 * @param \GraphQL\Validator\QueryValidationContext $context
	 *
	 * @return array<string,array<string,callable(\GraphQL\Language\AST\Node): (\GraphQL\Language\VisitorOperation|void|false|null)>|(callable(\GraphQL\Language\AST\Node): (\GraphQL\Language\VisitorOperation|void|false|null))>
	 */
	public function getVisitor( \GraphQL\Validator\QueryValidationContext $context ): array {
		$allowed_root_fields = [];

		/**
		 * Filters the allowed root fields
		 *
		 * @param string[]                                    $allowed_root_fields The Root fields allowed to be requested without authentication
		 * @param \GraphQL\Validator\QueryValidationContext  $context The Validation context of the field being executed.
		 */
		$allowed_root_fields = apply_filters( 'graphql_require_authentication_allowed_fields', $allowed_root_fields, $context );

		/**
		 * @param \GraphQL\Language\AST\Node $node
		 * @return void
		 */
		$field_validator = static function ( Node $node ) use ( $context, $allowed_root_fields ): void {
			// If not a FieldNode, return early
			if ( ! $node instanceof FieldNode ) {
				return;
			}

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
						// translators: %s is the field name
							__( 'The field "%s" cannot be accessed without authentication.', 'wp-graphql' ),
							$context->getParentType() . '.' . $node->name->value
						),
						[ $node ]
					)
				);
			}
		};

		return $this->invokeIfNeeded(
			$context,
			[
				NodeKind::FIELD => $field_validator,
			]
		);
	}
}
