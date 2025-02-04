<?php

namespace WPGraphQL\Server\ValidationRules;

/**
 * Class DisableIntrospection
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class DisableIntrospection extends \GraphQL\Validator\Rules\DisableIntrospection {

	/**
	 * Returns a helpful message when introspection is disabled and an introspection query is attempted.
	 */
	public static function introspectionDisabledMessage(): string {
		return 'The query contained __schema or __type, however GraphQL introspection is not allowed for public requests by default. Public introspection can be enabled under the WPGraphQL Settings.';
	}
}
