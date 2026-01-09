<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Trait WPInterfaceTrait
 *
 * This Trait includes methods to help Interfaces and ObjectTypes ensure they implement
 * the proper inherited interfaces
 *
 * @package WPGraphQL\Type
 */
trait WPInterfaceTrait {

	/**
	 * Given an array of interfaces, this gets the Interfaces the Type should implement including inherited interfaces.
	 *
	 * @return \GraphQL\Type\Definition\InterfaceType[]
	 */
	protected function get_implemented_interfaces(): array {
		$interfaces = ! empty( $this->config['interfaces'] ) && is_array( $this->config['interfaces'] ) ? $this->config['interfaces'] : null;

		if ( null === $interfaces ) {
			// If no interfaces are explicitly defined, fall back to the underlying class.
			$interfaces = parent::getInterfaces();
		}

		/**
		 * Filters the interfaces applied to an object type
		 *
		 * @param string[]                   $interfaces     List of interfaces applied to the Object Type
		 * @param array<string,mixed>        $config         The config for the Object Type
		 * @param mixed|\WPGraphQL\Type\WPInterfaceType|\WPGraphQL\Type\WPObjectType $type The Type instance
		 */
		$interfaces = apply_filters( 'graphql_type_interfaces', $interfaces, $this->config, $this );

		// If the filter gets rid of valid interfaces, we should return an empty array.
		if ( empty( $interfaces ) || ! is_array( $interfaces ) ) {
			return [];
		}

		$implemented_interfaces = [];
		$implementing_type_name = $this->config['name'] ?? 'Unknown';

		foreach ( $interfaces as $maybe_interface ) {
			$interface = $this->maybe_resolve_interface( $maybe_interface, $implementing_type_name );
			// Skip invalid interfaces.
			if ( null === $interface ) {
				continue;
			}

			$implemented_interfaces[ $interface->name ] = $interface;

			// Add interfaces implemented by this interface and their ancestors
			$this->resolve_inherited_interfaces( $interface, $implemented_interfaces );
		}

		// We use array_unique as a final safeguard against duplicate entries.
		// While we're already using interface names as array keys which generally prevents duplicates,
		// this provides an extra layer of protection for edge cases or future modifications.
		return array_unique( $implemented_interfaces );
	}

	/**
	 * Resolves a single interface configuration entry to an InterfaceType instance.
	 * Handles validation and debugging messages, using early returns for clarity.
	 *
	 * @param mixed  $type The interface entry from the config (string name or InterfaceType instance).
	 * @param string $implementing_type_name The name of the type that is implementing this interface (for debug messages and self-implementation check).
	 * @return \GraphQL\Type\Definition\InterfaceType|null The resolved InterfaceType or null if invalid/skipped.
	 */
	private function maybe_resolve_interface( $type, string $implementing_type_name ): ?InterfaceType {
		$type_name = $type instanceof InterfaceType ? $type->name : $type;

		// Bail if the entry is trying to implement itself.
		if ( ! empty( $implementing_type_name ) && strtolower( $implementing_type_name ) === strtolower( $type_name ) ) {
			graphql_debug(
				sprintf(
					// translators: %s is the name of the interface.
					__( 'The "%s" Interface attempted to implement itself, which is not allowed', 'wp-graphql' ),
					$type_name
				)
			);
			return null;
		}

		// Return early if it's already a valid interface type
		if ( $type instanceof InterfaceType ) {
			return $type;
		}

		// If it's not a string, we won't be able to resolve it.
		if ( ! is_string( $type ) ) {
			graphql_debug(
				sprintf(
					// translators: %s is the name of the GraphQL type.
					__( 'Invalid Interface registered to the "%s" Type. Interfaces can only be registered with an interface name or a valid instance of an InterfaceType', 'wp-graphql' ),
					$implementing_type_name
				),
				[ 'invalid_interface' => $type ]
			);
			return null;
		}

		// Attempt to resolve the string to a type.
		$resolved_type = $this->type_registry->get_type( $type );

		// Check if the resolved type is a valid InterfaceType.
		if ( ! $resolved_type instanceof InterfaceType ) {
			graphql_debug(
				sprintf(
					// translators: %1$s is the name of the interface, %2$s is the name of the type.
					__( '"%1$s" is not a valid Interface Type and cannot be implemented as an Interface on the "%2$s" Type', 'wp-graphql' ),
					$type,
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
	 * @@param-out array<string, \GraphQL\Type\Definition\InterfaceType> $target_interfaces The array to add interfaces to (passed by reference).
	 */
	private function resolve_inherited_interfaces( InterfaceType $interface_type, array &$target_interfaces ): void {
		// Get interfaces implemented by this interface.
		$interfaces = $interface_type->getInterfaces();

		if ( empty( $interfaces ) ) {
			return;
		}

		foreach ( $interfaces as $child_interface ) {
			// Skip invalid interface entries
			if ( ! $child_interface instanceof InterfaceType ) {
				continue;
			}

			// Skip if the interface is already in the target array
			if ( isset( $target_interfaces[ $child_interface->name ] ) ) {
				continue;
			}

			// Add the interface to our collection, keyed by name
			$target_interfaces[ $child_interface->name ] = $child_interface;

			// Recursively add interfaces from the child interface
			$this->resolve_inherited_interfaces( $child_interface, $target_interfaces );
		}
	}

	/**
	 * Returns the fields for a Type, applying any missing fields defined on interfaces implemented on the type
	 *
	 * @param array<string,mixed>              $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return array<string, array<string,mixed>>
	 * @throws \Exception
	 */
	protected function get_fields( array $config, TypeRegistry $type_registry ): array {

		if ( is_callable( $config['fields'] ) ) {
			$config['fields'] = $config['fields']();
		}

		$fields = array_filter( $config['fields'] );

		/**
		 * Get the fields of interfaces and ensure they exist as fields of this type.
		 *
		 * Types are still responsible for ensuring the fields resolve properly.
		 */
		$interface_fields = $this->get_fields_from_implemented_interfaces( $type_registry );

		// Merge fields with interface fields that are not already in fields
		$fields = array_merge( $fields, array_diff_key( $interface_fields, $fields ) );

		foreach ( $fields as $field_name => $field ) {
			$merged_field_config = $this->inherit_field_config_from_interface( $field_name, $field, $interface_fields );

			if ( null === $merged_field_config ) {
				unset( $fields[ $field_name ] );
				continue;
			}

			// Update the field.
			$fields[ $field_name ] = $merged_field_config;
		}

		$fields = $this->prepare_fields( $fields, $config['name'], $config );
		$fields = $type_registry->prepare_fields( $fields, $config['name'] );

		$this->fields = $fields;
		return $this->fields;
	}

	/**
	 * Get the fields from the implemented interfaces.
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $registry The TypeRegistry instance.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_fields_from_implemented_interfaces( TypeRegistry $registry ): array {
		$interface_fields = [];
		$interfaces       = $this->getInterfaces();

		// Get the fields for each interface.
		foreach ( $interfaces as $interface_type ) {
			// Get the resolved InterfaceType instance, if it's not already an instance of InterfaceType.
			if ( ! $interface_type instanceof InterfaceType ) {
				$interface_type = $registry->get_type( $interface_type );
			}

			if ( ! $interface_type instanceof InterfaceType ) {
				continue;
			}

			$interface_config_fields = $interface_type->getFields();

			if ( empty( $interface_config_fields ) ) {
				continue;
			}

			foreach ( $interface_config_fields as $interface_field_name => $interface_field ) {
				$interface_fields[ $interface_field_name ] = $interface_field->config;
			}
		}

		return $interface_fields;
	}

	/**
	 * Inherit missing field configs from the interface.
	 *
	 * @param string                            $field_name       The field name.
	 * @param array<string,mixed>               $field            The field config.
	 * @param array<string,array<string,mixed>> $interface_fields The fields from the interface.
	 *
	 * @return ?array<string,mixed> The field config with inherited values. Null if the field type cannot be determined.
	 */
	private function inherit_field_config_from_interface( string $field_name, array $field, array $interface_fields ): ?array {

		$interface_field = $interface_fields[ $field_name ] ?? [];

		// Return early if neither field nor interface type is defined, as registration cannot proceed.
		if ( empty( $field['type'] ) && empty( $interface_field['type'] ) ) {
			graphql_debug(
				sprintf(
					// translators: %1$s is the field name, %2$s is the type name.
					__( 'Invalid Interface field %1$s registered to the "%2$s" Type. Fields must be registered a valid GraphQL `type`.', 'wp-graphql' ),
					$field_name,
					$this->config['name'] ?? 'Unknown'
				)
			);

			return null;
		}

		// Inherit the field config from the interface if it's not set on the field.
		foreach ( $interface_field as $key => $config ) {
			// Inherit the field config from the interface if it's not set on the field.
			if ( empty( $field[ $key ] ) ) {
				$field[ $key ] = $config;
				continue;
			}

			// If the args on both the field and the interface are set, we need to merge them.
			if ( 'args' === $key ) {
				$field[ $key ] = $this->merge_field_args( $field_name, $field[ $key ], $interface_field[ $key ] );
			}
		}

		return $field;
	}

	/**
	 * Merge the field args from the field and the interface.
	 *
	 * @param string                            $field_name     The field name.
	 * @param array<string,array<string,mixed>> $field_args     The field args.
	 * @param array<string,array<string,mixed>> $interface_args The interface args.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function merge_field_args( string $field_name, array $field_args, array $interface_args ): array {
		// We use the interface args as the base and overwrite them with the field args.
		$merged_args = $interface_args;

		foreach ( $field_args as $arg_name => $config ) {
			// If the arg is not defined on the interface, simply add it from the field.
			if ( empty( $merged_args[ $arg_name ] ) ) {
				$merged_args[ $arg_name ] = $config;
				continue;
			}

			// Check if the interface arg type is different from the new field arg type.
			$field_arg_type     = $this->normalize_type_name( $config['type'] );
			$interface_arg_type = $this->normalize_type_name( $merged_args[ $arg_name ]['type'] );

			// Log a message and skip the arg if types are incompatible
			if ( ! empty( $field_arg_type ) && $interface_arg_type !== $field_arg_type ) {
				graphql_debug(
					sprintf(
						/* translators: 1: Object type name, 2: Field name, 3: Argument name, 4: Expected argument type, 5: Actual argument type. */
						__(
							'Interface field argument "%1$s.%2$s(%3$s:)" expected to be of type "%4$s" but got "%5$s". Please ensure the field arguments match the interface field arguments or rename the argument.',
							'wp-graphql'
						),
						$this->config['name'] ?? 'Unknown',
						$field_name,
						$arg_name,
						$interface_arg_type,
						$field_arg_type
					)
				);
				continue;
			}

			// Merge the field arg config with the interface arg config.
			$merged_args[ $arg_name ] = array_merge( $merged_args[ $arg_name ], $config );
		}

		return $merged_args;
	}

	/**
	 * Given a type it will return a string representation of the type.
	 *
	 * This is used for optimistic comparison of the arg types using strings.
	 *
	 * @param string|array<string,mixed>|callable|\GraphQL\Type\Definition\Type $type The type to normalize.
	 */
	private function normalize_type_name( $type ): string {
		// Bail early if the type is empty.
		if ( empty( $type ) ) {
			return '';
		}

		// If the type is a callable, we need to resolve it.
		if ( is_callable( $type ) ) {
			$type = $type();
		}

		// If the type is an instance of a Type, we can get the name.
		if ( $type instanceof \GraphQL\Type\Definition\Type ) {
			$type = $type->name ?? $type->toString();
		}

		// If the type is *now* a string, we can return it.
		if ( is_string( $type ) ) {
			return $type;
		} elseif ( ! is_array( $type ) ) {
			// If the type is not an array, we can't do anything with it.
			return '';
		}

		// Arrays mean the type can be nested in modifiers.
		$output   = '';
		$modifier = array_keys( $type )[0];
		$type     = $type[ $modifier ];

		// Convert the type wrappers to a string, and recursively get the internals.
		switch ( $modifier ) {
			case 'list_of':
				$output = '[' . $this->normalize_type_name( $type ) . ']';
				break;
			case 'non_null':
				$output = '!' . $this->normalize_type_name( $type );
				break;
		}

		return $output;
	}
}
