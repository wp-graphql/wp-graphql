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
		if ( ! isset( $this->config['interfaces'] ) || ! is_array( $this->config['interfaces'] ) || empty( $this->config['interfaces'] ) ) {
			$interfaces = parent::getInterfaces();
		} else {
			$interfaces = $this->config['interfaces'];
		}

		/**
		 * Filters the interfaces applied to an object type
		 *
		 * @param string[]                   $interfaces     List of interfaces applied to the Object Type
		 * @param array<string,mixed>        $config         The config for the Object Type
		 * @param mixed|\WPGraphQL\Type\WPInterfaceType|\WPGraphQL\Type\WPObjectType $type The Type instance
		 */
		$interfaces = apply_filters( 'graphql_type_interfaces', $interfaces, $this->config, $this );

		if ( empty( $interfaces ) || ! is_array( $interfaces ) ) {
			return $interfaces;
		}

		$new_interfaces = [];

		foreach ( $interfaces as $interface ) {
			if ( $interface instanceof InterfaceType && ( $this->config['name'] ?? null ) !== $interface->name ) {
				$new_interfaces[ $interface->name ] = $interface;
				continue;
			}

			// surface when interfaces are trying to be registered with invalid configuration
			if ( ! is_string( $interface ) ) {
				graphql_debug(
					sprintf(
						// translators: %s is the name of the GraphQL type.
						__( 'Invalid Interface registered to the "%s" Type. Interfaces can only be registered with an interface name or a valid instance of an InterfaceType', 'wp-graphql' ),
						$this->config['name'] ?? 'Unknown'
					),
					[ 'invalid_interface' => $interface ]
				);
				continue;
			}

			// Prevent an interface from implementing itself
			if ( ! empty( $this->config['name'] ) && strtolower( $this->config['name'] ) === strtolower( $interface ) ) {
				graphql_debug(
					sprintf(
						// translators: %s is the name of the interface.
						__( 'The "%s" Interface attempted to implement itself, which is not allowed', 'wp-graphql' ),
						$interface
					)
				);
				continue;
			}

			$interface_type = $this->type_registry->get_type( $interface );
			if ( ! $interface_type instanceof InterfaceType ) {
				graphql_debug(
					sprintf(
						// translators: %1$s is the name of the interface, %2$s is the name of the type.
						__( '"%1$s" is not a valid Interface Type and cannot be implemented as an Interface on the "%2$s" Type', 'wp-graphql' ),
						$interface,
						$this->config['name'] ?? 'Unknown'
					)
				);
				continue;
			}

			$new_interfaces[ $interface ] = $interface_type;
			$interface_interfaces         = $interface_type->getInterfaces();

			if ( empty( $interface_interfaces ) ) {
				continue;
			}

			foreach ( $interface_interfaces as $interface_interface_name => $interface_interface ) {
				if ( ! $interface_interface instanceof InterfaceType ) {
					continue;
				}

				$new_interfaces[ $interface_interface_name ] = $interface_interface;
			}
		}

		return array_unique( $new_interfaces );
	}


	/**
	 * Returns the fields for a Type, applying any missing fields defined on interfaces implemented on the type
	 *
	 * @param array<mixed>                     $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return array<mixed>
	 * @throws \Exception
	 */
	protected function get_fields( array $config, TypeRegistry $type_registry ): array {
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

		$interfaces = $this->getInterfaces();

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
	 * @param string                            $field_name The field name.
	 * @param array<string,mixed>               $field The field config.
	 * @param array<string,array<string,mixed>> $interface_fields The fields from the interface. This is passed by reference.
	 *
	 * @return ?array<string,mixed> The field config with inherited values. Null if the field type cannot be determined.
	 */
	private function inherit_field_config_from_interface( string $field_name, array $field, array $interface_fields ): ?array {

		$interface_field = $interface_fields[ $field_name ] ?? [];

		// Bail early, if there is no field type or an interface to inherit it from since we won't be able to register it.
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
	 * @param string                            $field_name The field name.
	 * @param array<string,array<string,mixed>> $field_args The field args.
	 * @param array<string,array<string,mixed>> $interface_args The interface args.
	 *
	 * @return array<string,array<string,mixed>> The merged field args.
	 */
	private function merge_field_args( string $field_name, array $field_args, array $interface_args ): array {
		// We use the interface args as the base and overwrite them with the field args.
		$merged_args = $interface_args;

		foreach ( $field_args as $arg_name => $config ) {
			// If the arg is not defined on the interface, we can use the field arg config.
			if ( empty( $merged_args[ $arg_name ] ) ) {
				$merged_args[ $arg_name ] = $config;
				continue;
			}

			// Check if the interface arg type is different from the new field arg type.
			$field_arg_type     = $this->normalize_type_name( $config['type'] );
			$interface_arg_type = $this->normalize_type_name( $merged_args[ $arg_name ]['type'] );

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
	 * This is used for optimistic comparison of the arg types.
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
