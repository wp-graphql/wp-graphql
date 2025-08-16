<?php
/**
 * Interface for type adapters to webonyx/graphql-php types.
 *
 * @package WPGraphQL\Registry\TypeAdapters
 */

namespace WPGraphQL\Registry\TypeAdapters;

/**
 * Interface - TypeAdapterInterface
 *
 * @internal
 *
 * @template Config of array<string, mixed>
 */
interface TypeAdapterInterface {
	/**
	 * Get the kind of type.
	 */
	public static function get_kind(): string;

	/**
	 * The class constructor.
	 *
	 * Prepares the configuration before passing it to the graphql-php parent constructor.
	 *
	 * @param array<string,mixed> $config
	 */
	public function __construct( array $config );

	/**
	 * Validate the configuration for the type.
	 *
	 * @param array<string, mixed> $config The configuration array for the type.
	 *
	 * @throws \GraphQL\Error\UserError If the config is invalid.
	 *
	 * @phpstan-assert Config $config
	 */
	public function validate( array $config ): void;

	/**
	 * Prepares the configuration for the type.
	 *
	 * @param array<string,mixed> $config The configuration array for the type.
	 *
	 * @return array<string,mixed> The prepared configuration.
	 */
	public function prepare( array $config ): array;
}
