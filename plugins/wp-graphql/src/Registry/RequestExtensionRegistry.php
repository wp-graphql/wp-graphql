<?php

namespace WPGraphQL\Registry;

use WPGraphQL\Utils\Preview;

/**
 * Class RequestExtensionRegistry
 *
 * Holds the request extension namespaces: root-level keys of the request `extensions`
 * object (such as `preview`), each optionally carried by its own HTTP header as an
 * RFC 8941 dictionary, with typed and sanitized members and a cache rule.
 *
 * Core's namespaces are registered when the registry is created, before the
 * `graphql_register_request_extensions` action fires, so they and their members are
 * reserved. Every other collision is resolved by the first registration winning, so
 * behavior never depends on plugin load order. Every refused registration surfaces a
 * debug notice saying why.
 *
 * phpcs:disable -- PHPStan annotation.
 * @phpstan-type RequestExtensionMember array{
 *  types: list<string>,
 *  items: ?string,
 *  header_key: string,
 *  description: string,
 *  sanitize: callable(mixed):mixed,
 *  core: bool
 * }
 *
 * @phpstan-type RequestExtension array{
 *  namespace: string,
 *  description: string,
 *  header: ?string,
 *  cache: string,
 *  members: array<string,RequestExtensionMember>,
 *  reserved_members: array<string,string>,
 *  debug_prefix: string,
 *  core: bool
 * }
 * phpcs:enable
 *
 * @since x-release-please-version
 */
class RequestExtensionRegistry {

	/**
	 * The member types a registered member may declare.
	 *
	 * @since x-release-please-version
	 */
	public const MEMBER_TYPES = [ 'integer', 'string', 'boolean', 'inner-list' ];

	/**
	 * The item types an `inner-list` member may declare.
	 *
	 * @since x-release-please-version
	 */
	public const MEMBER_ITEM_TYPES = [ 'integer', 'string', 'boolean' ];

	/**
	 * The cache rules a namespace may declare.
	 *
	 * `vary`: the namespace's header is added to the response's `Vary` header.
	 * `no-store`: as `vary`, and a response to a request carrying the namespace is sent
	 * with `Cache-Control: no-store, private`, so no cache may store it.
	 *
	 * @since x-release-please-version
	 */
	public const CACHE_RULES = [ 'vary', 'no-store' ];

	/**
	 * Request headers WPGraphQL already reads for its own purposes, which a namespace may
	 * not claim as its header.
	 */
	private const RESERVED_HEADERS = [ 'Authorization', 'Content-Type' ];

	/**
	 * The registered namespaces, keyed by namespace.
	 *
	 * @var array<string,RequestExtension>
	 */
	private $extensions = [];

	/**
	 * Members registered for a namespace that has not been registered yet, applied (in
	 * order) when it is.
	 *
	 * @var array<string,array<string,array<string,mixed>>>
	 */
	private $pending_members = [];

	/**
	 * Whether the request in progress has already read the registered namespaces, so a
	 * namespace or member registered now is not parsed for it.
	 *
	 * @var bool
	 */
	private $read = false;

	/**
	 * Registers core's namespaces. They are registered before any extension can register
	 * one, which is what reserves them and their members.
	 *
	 * @since x-release-please-version
	 */
	public function __construct() {
		$preview = Preview::get_request_extension_config();

		$members = [];
		foreach ( $preview['members'] as $name => $member ) {
			$members[ $name ] = [
				'types'       => $member['types'],
				'items'       => null,
				'header_key'  => $member['header_key'],
				'description' => $member['description'],
				'sanitize'    => $member['sanitize'],
				'core'        => true,
			];
		}

		$this->extensions['preview'] = [
			'namespace'        => 'preview',
			'description'      => $preview['description'],
			'header'           => $preview['header'],
			'cache'            => 'no-store',
			'members'          => $members,
			'reserved_members' => $preview['reserved_members'],
			'debug_prefix'     => 'PREVIEW_CONTEXT',
			'core'             => true,
		];
	}

	/**
	 * Registers a request extension namespace.
	 *
	 * See register_graphql_request_extension() for the config contract.
	 *
	 * @param string              $name   The camelCase namespace, the key in the request `extensions`.
	 * @param array<string,mixed> $config The namespace config.
	 *
	 * @since x-release-please-version
	 */
	public function register_extension( string $name, array $config ): void {
		if ( isset( $this->extensions[ $name ] ) ) {
			$this->debug_not_registered(
				$name,
				$this->extensions[ $name ]['core']
					? __( 'it is reserved by WPGraphQL core', 'wp-graphql' )
					: __( 'a namespace with the same name is already registered', 'wp-graphql' )
			);
			return;
		}

		if ( ! preg_match( '/^[a-z][a-zA-Z0-9]*$/', $name ) ) {
			$this->debug_not_registered( $name, __( 'the name must be camelCase (a lowercase letter followed by letters and digits)', 'wp-graphql' ) );
			return;
		}

		$header = $config['header'] ?? null;

		if ( null !== $header ) {
			if ( ! is_string( $header ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9-]*$/', $header ) ) {
				$this->debug_not_registered( $name, __( 'the `header` must be a header name made of letters, digits and hyphens', 'wp-graphql' ) );
				return;
			}

			if ( null !== $this->get_header_owner( $header ) || in_array( strtolower( $header ), array_map( 'strtolower', self::RESERVED_HEADERS ), true ) ) {
				$this->debug_not_registered( $name, __( 'its header is reserved by WPGraphQL core or already used by another namespace', 'wp-graphql' ) );
				return;
			}
		}

		$cache = $config['cache'] ?? 'vary';

		if ( ! in_array( $cache, self::CACHE_RULES, true ) ) {
			$this->debug_not_registered( $name, __( 'the `cache` rule must be `vary` or `no-store`', 'wp-graphql' ) );
			return;
		}

		$members = $config['members'] ?? [];

		if ( ! is_array( $members ) ) {
			$this->debug_not_registered( $name, __( '`members` must be an array of member configs keyed by member name', 'wp-graphql' ) );
			return;
		}

		$this->extensions[ $name ] = [
			'namespace'        => $name,
			'description'      => isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : '',
			'header'           => $header,
			'cache'            => $cache,
			'members'          => [],
			'reserved_members' => [],
			'debug_prefix'     => 'REQUEST_EXTENSION',
			'core'             => false,
		];

		if ( $this->read ) {
			graphql_debug(
				sprintf(
					/* translators: %s: the request extension namespace */
					__( 'The request extension namespace `%s` was registered after this request read the request extensions, so it is not parsed for this request. Register request extension namespaces on the `graphql_register_request_extensions` action, or on `init` or earlier.', 'wp-graphql' ),
					$name
				),
				[ 'type' => 'REQUEST_EXTENSION_REGISTERED_LATE' ]
			);
		}

		// The namespace's own members first, then any registered for it ahead of it.
		foreach ( $members as $member_name => $member_config ) {
			$this->register_member( $name, (string) $member_name, is_array( $member_config ) ? $member_config : [] );
		}

		$pending = $this->pending_members[ $name ] ?? [];
		unset( $this->pending_members[ $name ] );

		foreach ( $pending as $member_name => $member_config ) {
			$this->register_member( $name, $member_name, $member_config );
		}
	}

	/**
	 * Registers a member of a request extension namespace.
	 *
	 * A member registered for a namespace that is not registered yet is held until it is,
	 * so the order two plugins register in does not matter.
	 *
	 * See register_graphql_request_extension_member() for the config contract.
	 *
	 * @param string              $namespace_name The namespace the member belongs to.
	 * @param string              $name      The camelCase member name.
	 * @param array<string,mixed> $config    The member config.
	 *
	 * @since x-release-please-version
	 */
	public function register_member( string $namespace_name, string $name, array $config ): void {
		if ( ! isset( $this->extensions[ $namespace_name ] ) ) {
			if ( isset( $this->pending_members[ $namespace_name ][ $name ] ) ) {
				$this->debug_member_not_registered( $namespace_name, $name, __( 'a member with the same name is already registered', 'wp-graphql' ) );
				return;
			}

			$this->pending_members[ $namespace_name ][ $name ] = $config;
			return;
		}

		$extension = $this->extensions[ $namespace_name ];

		if ( isset( $extension['reserved_members'][ $name ] ) || ( isset( $extension['members'][ $name ] ) && $extension['members'][ $name ]['core'] ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'it is reserved by WPGraphQL core', 'wp-graphql' ) );
			return;
		}

		if ( ! preg_match( '/^[a-z][a-zA-Z0-9]*$/', $name ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'the name must be camelCase (a lowercase letter followed by letters and digits)', 'wp-graphql' ) );
			return;
		}

		$type  = $config['type'] ?? null;
		$items = 'inner-list' === $type ? ( $config['items'] ?? null ) : null;

		if ( ! in_array( $type, self::MEMBER_TYPES, true ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'the type must be one of `integer`, `string`, `boolean` or `inner-list`', 'wp-graphql' ) );
			return;
		}

		if ( 'inner-list' === $type && ! in_array( $items, self::MEMBER_ITEM_TYPES, true ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'an `inner-list` member must declare `items` as one of `integer`, `string` or `boolean`', 'wp-graphql' ) );
			return;
		}

		if ( ! isset( $config['sanitize'] ) || ! is_callable( $config['sanitize'] ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'a `sanitize` callback is required', 'wp-graphql' ) );
			return;
		}

		// The header key is derived from the name so the two transports cannot drift.
		$header_key = $config['header_key'] ?? strtolower( (string) preg_replace( '/[A-Z]/', '_$0', $name ) );

		// RFC 8941 dictionary keys: a lowercase letter or "*", then lowercase letters, digits, "_", "-", "." or "*".
		if ( ! is_string( $header_key ) || ! preg_match( '/^[a-z*][a-z0-9_\-.*]*$/', $header_key ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'the `header_key` must be a valid lowercase RFC 8941 dictionary key', 'wp-graphql' ) );
			return;
		}

		$core_header_keys = $extension['reserved_members'];
		foreach ( $extension['members'] as $member ) {
			if ( $member['core'] ) {
				$core_header_keys[] = $member['header_key'];
			}
		}

		if ( in_array( $header_key, $core_header_keys, true ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'its header key is reserved by WPGraphQL core', 'wp-graphql' ) );
			return;
		}

		// First wins, so behavior never depends on plugin load order.
		if ( isset( $extension['members'][ $name ] ) || in_array( $header_key, wp_list_pluck( $extension['members'], 'header_key' ), true ) ) {
			$this->debug_member_not_registered( $namespace_name, $name, __( 'a member with the same name or header key is already registered', 'wp-graphql' ) );
			return;
		}

		$this->extensions[ $namespace_name ]['members'][ $name ] = [
			'types'       => [ $type ],
			'items'       => $items,
			'header_key'  => $header_key,
			'description' => isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : '',
			'sanitize'    => $config['sanitize'],
			'core'        => false,
		];

		// The request reads the registered members before it builds the schema, so a member
		// registered on `graphql_register_types`, or later, is not parsed for the request in progress.
		if ( $this->read ) {
			graphql_debug(
				'preview' === $namespace_name
					? sprintf(
						/* translators: %s: the preview context member name */
						__( 'The preview context member `%s` was registered after this request read the preview context, so it is not parsed for this request. Register preview context members on `init` or earlier.', 'wp-graphql' ),
						$name
					)
					: sprintf(
						/* translators: 1: the request extension member name, 2: the request extension namespace */
						__( 'The request extension member `%1$s` of `%2$s` was registered after this request read the request extensions, so it is not parsed for this request. Register request extension members on the `graphql_register_request_extensions` action, or on `init` or earlier.', 'wp-graphql' ),
						$name,
						$namespace_name
					),
				[ 'type' => $extension['debug_prefix'] . '_MEMBER_REGISTERED_LATE' ]
			);
		}
	}

	/**
	 * Returns the registered namespaces, keyed by namespace.
	 *
	 * @return array<string,RequestExtension>
	 *
	 * @since x-release-please-version
	 */
	public function get_extensions(): array {
		return $this->extensions;
	}

	/**
	 * Returns a registered namespace, or null when it is not registered.
	 *
	 * @param string $name The namespace.
	 *
	 * @return RequestExtension|null
	 *
	 * @since x-release-please-version
	 */
	public function get_extension( string $name ): ?array {
		return $this->extensions[ $name ] ?? null;
	}

	/**
	 * Returns the headers of the registered namespaces that declare one.
	 *
	 * @return string[]
	 *
	 * @since x-release-please-version
	 */
	public function get_headers(): array {
		return array_values( array_filter( wp_list_pluck( $this->extensions, 'header' ) ) );
	}

	/**
	 * Marks whether the request in progress has read the registered namespaces.
	 *
	 * The request sets it before it parses its request extensions and clears it when it
	 * finishes, so only a registration too late for the request in progress surfaces a
	 * notice. Reading also reports members still waiting for a namespace that was never
	 * registered.
	 *
	 * @internal
	 *
	 * @param bool $read Whether the namespaces have been read.
	 *
	 * @since x-release-please-version
	 */
	public function set_read( bool $read ): void {
		$this->read = $read;

		if ( ! $read ) {
			return;
		}

		foreach ( $this->pending_members as $namespace_name => $members ) {
			foreach ( array_keys( $members ) as $name ) {
				$this->debug_member_not_registered( (string) $namespace_name, $name, __( 'its namespace is not registered', 'wp-graphql' ) );
			}
		}
	}

	/**
	 * Returns the namespace whose header matches the given header name (header names are
	 * case-insensitive), or null.
	 *
	 * @param string $header The header name.
	 */
	private function get_header_owner( string $header ): ?string {
		foreach ( $this->extensions as $name => $extension ) {
			if ( null !== $extension['header'] && 0 === strcasecmp( $extension['header'], $header ) ) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Surfaces a debug notice for a namespace registration that was refused.
	 *
	 * @param string $name   The namespace.
	 * @param string $reason Why the namespace was not registered.
	 */
	private function debug_not_registered( string $name, string $reason ): void {
		graphql_debug(
			sprintf(
				/* translators: 1: the request extension namespace, 2: the reason it was not registered */
				__( 'The request extension namespace `%1$s` was not registered: %2$s.', 'wp-graphql' ),
				$name,
				$reason
			),
			[ 'type' => 'REQUEST_EXTENSION_NOT_REGISTERED' ]
		);
	}

	/**
	 * Surfaces a debug notice for a member registration that was refused.
	 *
	 * @param string $namespace_name The namespace.
	 * @param string $name      The member name.
	 * @param string $reason    Why the member was not registered.
	 */
	private function debug_member_not_registered( string $namespace_name, string $name, string $reason ): void {
		$prefix = $this->extensions[ $namespace_name ]['debug_prefix'] ?? 'REQUEST_EXTENSION';

		graphql_debug(
			'preview' === $namespace_name
				? sprintf(
					/* translators: 1: the preview context member name, 2: the reason it was not registered */
					__( 'The preview context member `%1$s` was not registered: %2$s.', 'wp-graphql' ),
					$name,
					$reason
				)
				: sprintf(
					/* translators: 1: the request extension member name, 2: the request extension namespace, 3: the reason it was not registered */
					__( 'The request extension member `%1$s` of `%2$s` was not registered: %3$s.', 'wp-graphql' ),
					$name,
					$namespace_name,
					$reason
				),
			[ 'type' => $prefix . '_MEMBER_NOT_REGISTERED' ]
		);
	}
}
