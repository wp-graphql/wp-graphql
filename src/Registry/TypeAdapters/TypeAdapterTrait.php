<?php
/**
 * A shared trait for adapting WPGraphQL types to webonyx/graphql-php types.
 *
 * @package WPGraphQL\Registry\TypeAdapters;
 * @since next-version
 */

namespace WPGraphQL\Registry\TypeAdapters;

use WPGraphQL;

/**
 * Trait - TypeAdapterTrait
 *
 * @phpstan-require-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface
 *
 * @template Config of array<string,mixed>
 */
trait TypeAdapterTrait {
	/**
	 * The keys that are prepared for introspection.
	 *
	 * @var string[]|null
	 */
	private static ?array $introspection_keys = null;

	/**
	 * Prepare the type configuration before passing it to the parent constructor.
	 *
	 * Uses the `::prepare` method to allow for type-specific preparation.
	 *
	 * @param array<string,mixed> $config The configuration array for the type.
	 *
	 * @return array<string,mixed> The prepared configuration.
	 */
	protected function prepare_config( array $config ): array {
		// Filter the type name.
		$name = $config['name'] ?? '';

		/**
		 * Filters the type name before anything else.
		 *
		 * @param string              $name The name of the type.
		 * @param array<string,mixed> $config The configuration array for the type.
		 * @param string              $kind The kind of WPGraphQL type being adapted.
		 */
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, static::get_kind() );

		// Handle the type-specific preparation.
		$config = $this->prepare( $config );

		/**
		 * Filters the configuration before it is registered.
		 *
		 * @param array<string,mixed> $config The configuration array for the type.
		 */
		$config = apply_filters( 'graphql_wp_' . static::get_kind() . '_type_config', $config );

		// Handle introspection fields.
		return static::prepare_config_for_introspection( $config );
	}

	/**
	 * Validates the type configuration before passing it to the parent constructor.
	 *
	 * @param array<string,mixed> $config The configuration array for the type.
	 *
	 * @phpstan-assert Config $config
	 * @throws \GraphQL\Error\UserError If the configuration is invalid.
	 */
	protected function validate_config( array $config ): void {
		// Validate the configuration.
		if ( ! isset( $config['name'] ) || empty( $config['name'] ) ) {
			throw new \GraphQL\Error\UserError(
				sprintf(
					// translators: %s is the type name, %s is the config array.
					esc_html__( 'GraphQL config array must have a name.', 'wp-graphql' ),
					esc_html( static::get_kind() ),
				)
			);
		}

		// Call the type-specific validation method if it exists.
		$this->validate( $config );
	}

	/**
	 * Prepares the resolveType callable for the config.
	 *
	 * @param mixed                                $obj The object being resolved.
	 * @param mixed                                $context The context of the request.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The resolve info.
	 * @param array<string,mixed>                  $config The configuration array for the type.
	 *
	 * @return \GraphQL\Type\Definition\ObjectType|string|callable():(\GraphQL\Type\Definition\ObjectType|string|null)|\GraphQL\Deferred|null The resolved type, or null if not resolvable.
	 */
	protected static function prepare_type_resolver( $obj, $context, \GraphQL\Type\Definition\ResolveInfo $info, array $config ) {
		$type = null;

		if ( isset( $config['resolveType'] ) && is_callable( $config['resolveType'] ) ) {
			$type = call_user_func( $config['resolveType'], $obj, $context, $info );
		}

		/**
		 * Filter the resolve type method for all interfaces
		 *
		 * @param mixed $type The Type to resolve to, based on the object being resolved.
		 * @param mixed $obj  The Object being resolved.
		 */
		return apply_filters( 'graphql_' . static::get_kind() . '_resolve_type', $type, $obj );
	}

	/**
	 * Prepare the config for introspection. This is used to resolve callable values for description and deprecationReason for
	 * introspection queries.
	 *
	 * @template T of array<string, mixed>
	 *
	 * @param T $config The config to prepare.
	 *
	 * @return array<string,mixed> The prepared config.
	 * @phpstan-return T&array{description?: string|null, deprecationReason?: string|null}
	 *
	 * @internal
	 */
	protected static function prepare_config_for_introspection( array $config ): array {
		// Get the keys that are prepared for introspection.
		$introspection_keys = self::get_introspection_keys();

		foreach ( $introspection_keys as $key ) {
			if ( ! isset( $config[ $key ] ) || ! is_callable( $config[ $key ] ) ) {
				continue;
			}

			if ( ! WPGraphQL::is_introspection_query() ) {
				// If not introspection, set to null.
				$config[ $key ] = null;
				continue;
			}

			$config[ $key ] = is_callable( $config[ $key ] ) ? $config[ $key ]() : '';
		}

		return $config;
	}

	/**
	 * Get the keys that are prepared for introspection.
	 *
	 * @return string[]
	 */
	protected static function get_introspection_keys(): array {
		if ( null === self::$introspection_keys ) {
			/**
			 * Filter the keys that are prepared for introspection.
			 *
			 * @param array<string> $introspection_keys The keys to prepare for introspection.
			 */
			$introspection_keys       = \apply_filters( 'graphql_introspection_keys', [ 'description', 'deprecationReason' ] );
			self::$introspection_keys = $introspection_keys;
		}

		return self::$introspection_keys;
	}
}
