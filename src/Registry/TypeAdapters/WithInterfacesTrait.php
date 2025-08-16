<?php
/**
 * Trait for types that can implement an GraphQL interface.
 *
 * @package WPGraphQL\Registry\TypeAdapters;
 * @since next-version
 */

namespace WPGraphQL\Registry\TypeAdapters;

use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL;

/**
 * Trait WithInterfacesTrait
 *
 * This Trait includes methods to help Interfaces and ObjectTypes ensure they implement
 * the proper inherited interfaces
 *
 * @phpstan-import-type FieldDefinitionConfig from \GraphQL\Type\Definition\FieldDefinition
 *
 * @phpstan-require-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface
 */
trait WithInterfacesTrait {
	/**
	 * Get the interfaces implemented by the ObjectType
	 *
	 * @param array<string,mixed> $config The configuration array for the type.
	 *
	 * @return \GraphQL\Type\Definition\InterfaceType[]
	 */
	protected static function prepare_interfaces( $config ): array {
		/**
		 * Filters the interfaces applied to an object type
		 *
		 * @param string[]            $interface_names List of interfaces applied to the Object Type
		 * @param array<string,mixed> $config     The config for the Object Type
		 * @param string              $kind       The kind of type being prepared (e.g., 'interface').
		 */
		$interface_names = apply_filters( 'graphql_type_interfaces', $config['interfaces'] ?? [], $config, self::get_kind() );

		$resolved_interfaces = [];

		// Recursively get the implementing interfaces.
		foreach ( $interface_names as $interface_name ) {
			// If already resolved, skip.
			if ( isset( $resolved_interfaces[ $interface_name ] ) ) {
				continue;
			}

			$resolved_interface = self::maybe_resolve_interface( $interface_name, $config['name'] );

			// Skip invalid interfaces.
			if ( null === $resolved_interface ) {
				continue;
			}

			$resolved_interfaces[ $resolved_interface->name ] = $resolved_interface;

			self::resolve_inherited_interfaces( $resolved_interface, $resolved_interfaces );
		}

		// We use array_unique as a final safeguard against duplicate entries.
		return array_unique( $resolved_interfaces );
	}

	/**
	 * Resolves a single interface configuration entry to an InterfaceType instance.
	 * Handles validation and debugging messages, using early returns for clarity.
	 *
	 * @param string $type_name              The type to resolve.
	 * @param string $implementing_type_name The name of the type that is implementing this interface.
	 *
	 * @return \GraphQL\Type\Definition\InterfaceType|null The resolved InterfaceType or null if invalid/skipped.
	 */
	private static function maybe_resolve_interface( string $type_name, string $implementing_type_name ): ?InterfaceType {
		// Bail if the entry is trying to implement itself.
		if ( strtolower( $implementing_type_name ) === strtolower( $type_name ) ) {
			graphql_debug(
				sprintf(
					// translators: %s is the name of the interface.
					__( 'The "%s" Interface attempted to implement itself, which is not allowed', 'wp-graphql' ),
					$type_name
				)
			);
			return null;
		}

		// Attempt to resolve the string to a type.
		$resolved_type = WPGraphQL::get_type_registry()->get_type( $type_name );

		// Check if the resolved type is a valid InterfaceType.
		if ( ! $resolved_type instanceof InterfaceType ) {
			graphql_debug(
				sprintf(
					// translators: %1$s is the name of the interface, %2$s is the name of the type.
					__( '"%1$s" is not a valid Interface Type and cannot be implemented as an Interface on the "%2$s" Type', 'wp-graphql' ),
					$type_name,
					$implementing_type_name
				)
			);
			return null;
		}

		return $resolved_type;
	}

	/**
	 * Adds interfaces implemented by the given InterfaceType to the target array.
	 * Handles recursive collection of interfaces, avoiding duplicates.
	 *
	 * @param \GraphQL\Type\Definition\InterfaceType                $interface_type     The interface whose implemented interfaces should be added.
	 * @param array<string, \GraphQL\Type\Definition\InterfaceType> &$target_interfaces The array to add interfaces to (passed by reference).
	 *
	 * @param-out array<string, \GraphQL\Type\Definition\InterfaceType> $target_interfaces The array to add interfaces to (passed by reference).
	 */
	private static function resolve_inherited_interfaces( InterfaceType $interface_type, array &$target_interfaces ): void {
		$interfaces = $interface_type->getInterfaces();

		if ( empty( $interfaces ) ) {
			return;
		}

		foreach ( $interfaces as $child_interface ) {
			if ( isset( $target_interfaces[ $child_interface->name ] ) ) {
				continue;
			}

			if ( ! $child_interface instanceof InterfaceType ) {
				graphql_debug(
					sprintf(
						/* translators: 1: Child interface name, 2: Parent interface name */
						__( 'Invalid interface "%1$s" detected while resolving inherited interfaces for "%2$s".', 'wp-graphql' ),
						$child_interface->name ?? 'unknown',
						$interface_type->name
					)
				);
				continue;
			}

			// Recursively resolve parent interfaces first.
			self::resolve_inherited_interfaces( $child_interface, $target_interfaces );

			$target_interfaces[ $child_interface->name ] = $child_interface;
		}
	}
}
