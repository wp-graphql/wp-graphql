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
		$should_be_enabled = $this->should_be_enabled();
		parent::__construct( $should_be_enabled ? self::ENABLED : self::DISABLED );
	}

	/**
	 * Determines whether the DisableIntrospection rule should be disabled.
	 */
	public function should_be_enabled(): bool {
		if ( ! get_current_user_id() && ! \WPGraphQL::debug() && 'off' === get_graphql_setting( 'public_introspection_enabled', 'off' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns a helpful message when introspection is disabled and an introspection query is attempted.
	 */
	public static function introspectionDisabledMessage(): string {
		return __( 'The query contained __schema or __type, however GraphQL introspection is not allowed for public requests by default. Public introspection can be enabled under the WPGraphQL Settings.', 'wp-graphql' );
	}
}
