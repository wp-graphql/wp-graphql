<?php

namespace WPGraphQL\Data;

/**
 * Class ConnectorCredentials
 *
 * Identifies and masks the settings WordPress connectors store their
 * credentials in.
 *
 * WordPress 7.0 added a connectors API: each connector (Akismet, AI providers,
 * and anything a plugin registers) can name the option holding its API key in
 * `authentication.setting_name`, and core registers that option with
 * `show_in_rest => true` so the connectors screen can manage it through
 * `/wp/v2/settings`. Core masks those values in REST responses. WPGraphQL
 * reads registered settings too, so it applies the same treatment on its own
 * surfaces: connector credential settings always require `manage_options`,
 * and the value is masked even for users who have it.
 *
 * Both rules are enforced where the value is read (the capability is also
 * recorded in the normalized settings map, for the field-level check and its
 * debug message), so neither the `graphql_normalized_settings` filter nor a
 * setting's own registration args can relax them. Writes are guarded so the
 * mask can't replace the stored credential.
 *
 * @internal Used by the settings read and write surfaces. Not an extension seam.
 *
 * @package WPGraphQL\Data
 *
 * @since 2.26.1
 */
final class ConnectorCredentials {

	/**
	 * The character a masked credential is made of, matching WordPress core's
	 * connector key mask (U+2022, BULLET).
	 */
	public const MASK_CHARACTER = "\u{2022}";

	/**
	 * The capability required to read a connector credential setting.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The connector authentication methods whose `setting_name` holds a secret:
	 * a plain API key string, or (WordPress 7.1+) application password
	 * credentials stored as an array with a `password` entry.
	 */
	private const SECRET_METHODS = [ 'api_key', 'application_password' ];

	/**
	 * Returns the option names connectors store credentials in, mapped to the
	 * connector's authentication method.
	 *
	 * Returns an empty array on WordPress versions without the connectors API,
	 * and before the connector registry is initialized (core initializes it on
	 * `init`, at priority 15). Requests to the GraphQL endpoint run after
	 * `init`, but a `graphql()` call made earlier than that can't identify
	 * connector credential settings. Core registers its own connector settings
	 * at priority 20, so those aren't in the Schema that early either.
	 *
	 * @return array<string,string> Option name => authentication method.
	 */
	public static function get_setting_names(): array {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return [];
		}

		$setting_names = [];

		foreach ( wp_get_connectors() as $connector ) {
			$auth = $connector['authentication'] ?? null;

			if ( ! is_array( $auth ) || ! isset( $auth['method'], $auth['setting_name'] ) ) {
				continue;
			}

			if ( ! in_array( $auth['method'], self::SECRET_METHODS, true ) || ! is_string( $auth['setting_name'] ) || '' === $auth['setting_name'] ) {
				continue;
			}

			$setting_names[ $auth['setting_name'] ] = (string) $auth['method'];
		}

		return $setting_names;
	}

	/**
	 * Masks an API key in WordPress core's format: one bullet per character
	 * except the last four, capped at 16 bullets, followed by the last four
	 * characters.
	 *
	 * Unlike core, a key of four characters or fewer is masked entirely rather
	 * than returned as is, since showing its last four characters would show
	 * the whole key. An empty key stays empty.
	 *
	 * @param string $key The API key.
	 */
	public static function mask_api_key( string $key ): string {
		$length = strlen( $key );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 4 ) {
			return str_repeat( self::MASK_CHARACTER, $length );
		}

		return str_repeat( self::MASK_CHARACTER, min( $length - 4, 16 ) ) . substr( $key, -4 );
	}

	/**
	 * Masks a connector credential value.
	 *
	 * An API key string is masked with mask_api_key(). Application password
	 * credentials keep their username and have a non-empty password replaced
	 * with 16 bullets, as core does. Null and false mean no credential is
	 * stored and are returned unchanged. Any other value can't be masked, so
	 * it is replaced with null rather than returned as is.
	 *
	 * @param mixed  $value  The resolved setting value.
	 * @param string $method The connector's authentication method.
	 *
	 * @return mixed
	 */
	public static function mask_value( $value, string $method ) {
		if ( null === $value || false === $value ) {
			return $value;
		}

		if ( 'application_password' === $method ) {
			if ( ! is_array( $value ) ) {
				return null;
			}

			if ( isset( $value['password'] ) && '' !== $value['password'] && false !== $value['password'] ) {
				$value['password'] = str_repeat( self::MASK_CHARACTER, 16 );
			}

			return $value;
		}

		return is_string( $value ) ? self::mask_api_key( $value ) : null;
	}

	/**
	 * Prepares an incoming value for a connector credential setting so that
	 * writing back a masked value never replaces the stored credential.
	 *
	 * A client that reads settings and saves them back sends the mask it was
	 * given. For an API key, a value that starts with the mask character means
	 * "unchanged", so `write` is false and the caller skips it. That is checked
	 * by the character rather than by comparing with the mask of the stored
	 * key, because the value a client read may have come from a filter rather
	 * than the stored option, and a real key never contains the character. For
	 * application password credentials, a password equal to the mask is
	 * replaced with the stored password, so the rest of the value (the
	 * username) is still written. Any other value is returned as is.
	 *
	 * @param string $option_name The option name.
	 * @param string $method      The connector's authentication method.
	 * @param mixed  $value       The incoming value.
	 *
	 * @return array{write:bool,value:mixed} Whether to write, and the value to write (null when `write` is false).
	 */
	public static function prepare_incoming_value( string $option_name, string $method, $value ): array {
		if ( 'application_password' === $method ) {
			if ( is_array( $value ) && isset( $value['password'] ) && str_repeat( self::MASK_CHARACTER, 16 ) === $value['password'] ) {
				$stored            = get_option( $option_name );
				$value['password'] = is_array( $stored ) && isset( $stored['password'] ) ? $stored['password'] : '';
			}

			return [
				'write' => true,
				'value' => $value,
			];
		}

		if ( is_string( $value ) && 0 === strpos( $value, self::MASK_CHARACTER ) ) {
			return [
				'write' => false,
				'value' => null,
			];
		}

		return [
			'write' => true,
			'value' => $value,
		];
	}
}
