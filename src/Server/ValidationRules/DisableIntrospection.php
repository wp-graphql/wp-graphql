<?php

namespace WPGraphQL\Server\ValidationRules;

/**
 * Class DisableIntrospection
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class DisableIntrospection extends \GraphQL\Validator\Rules\DisableIntrospection {

	/**
	 * DisableIntrospection constructor.
	 */
	public function __construct() {
		parent::__construct( $this->isEnabled() ? self::ENABLED : self::DISABLED );
	}

	/**
	 * Whether the rule is enabled or not.
	 */
	public function isEnabled(): bool {
		$enabled = false;

		if ( ! get_current_user_id() && ! \WPGraphQL::debug() && 'off' === get_graphql_setting( 'public_introspection_enabled', 'off' ) ) {
			$enabled = true;
		}

		return $enabled;
	}

	/**
	 * Returns a helpful message when introspection is disabled and an introspection query is attempted.
	 */
	public static function introspectionDisabledMessage(): string {
		return __( 'The query contained __schema or __type, however GraphQL introspection is not allowed for public requests by default. Public introspection can be enabled under the WPGraphQL Settings.', 'wp-graphql' );
	}
}
