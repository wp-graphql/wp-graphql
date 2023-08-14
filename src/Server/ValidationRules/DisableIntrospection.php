<?php

namespace WPGraphQL\Server\ValidationRules;

/**
 * Class DisableIntrospection
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class DisableIntrospection extends \GraphQL\Validator\Rules\DisableIntrospection {

	/**
	 * @return bool
	 */
	public function isEnabled() {

		$enabled = false;

		if ( ! get_current_user_id() && ! \WPGraphQL::debug() && 'off' === get_graphql_setting( 'public_introspection_enabled', 'off' ) ) {
			$enabled = true;
		}

		return $enabled;
	}

}
