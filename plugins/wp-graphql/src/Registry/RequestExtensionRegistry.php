<?php

namespace WPGraphQL\Registry;

/**
 * Class RequestExtensionRegistry
 *
 * Manages registered request extension namespaces, headers, members, and cache behaviors.
 *
 * @package WPGraphQL\Registry
 */
class RequestExtensionRegistry {

	/**
	 * Registered request extensions.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	protected $extensions = [];

	/**
	 * Registers a request extension namespace.
	 *
	 * @param string              $namespace The extension namespace (e.g. 'postPassword', 'preview').
	 * @param array<string,mixed> $config    Configuration including 'header', 'members', 'cache', and 'description'.
	 */
	public function register_request_extension( string $namespace, array $config ): void {
		$config['namespace'] = $namespace;
		$config['members']   = $config['members'] ?? [];
		$config['cache']     = $config['cache'] ?? 'vary';

		if ( isset( $this->extensions[ $namespace ] ) ) {
			$existing_members               = $this->extensions[ $namespace ]['members'] ?? [];
			$new_members                    = $config['members'] ?? [];
			$this->extensions[ $namespace ] = array_merge( $this->extensions[ $namespace ], $config );
			$this->extensions[ $namespace ]['members'] = array_merge( $existing_members, $new_members );
		} else {
			$this->extensions[ $namespace ] = $config;
		}
	}

	/**
	 * Registers a member on an existing request extension namespace (subsumes #4378).
	 *
	 * @param string              $namespace   The extension namespace.
	 * @param string              $member_name The member name (in camelCase).
	 * @param array<string,mixed> $config      Configuration including 'type', 'description', 'sanitize_callback'.
	 */
	public function register_request_extension_member( string $namespace, string $member_name, array $config ): void {
		if ( ! isset( $this->extensions[ $namespace ] ) ) {
			$this->extensions[ $namespace ] = [
				'namespace' => $namespace,
				'members'   => [],
				'cache'     => 'vary',
			];
		}

		$this->extensions[ $namespace ]['members'][ $member_name ] = $config;
	}

	/**
	 * Returns all registered request extensions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_registered_request_extensions(): array {
		return $this->extensions;
	}

	/**
	 * Returns a registered request extension by namespace.
	 *
	 * @param string $namespace The extension namespace.
	 * @return array<string,mixed>|null
	 */
	public function get_request_extension( string $namespace ): ?array {
		return $this->extensions[ $namespace ] ?? null;
	}
}

