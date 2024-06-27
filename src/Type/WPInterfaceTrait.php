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
			if ( $interface instanceof InterfaceType && $interface->name !== $this->name ) {
				$new_interfaces[ $interface->name ] = $interface;
				continue;
			}

			// surface when interfaces are trying to be registered with invalid configuration
			if ( ! is_string( $interface ) ) {
				graphql_debug(
					sprintf(
						// translators: %s is the name of the GraphQL type.
						__( 'Invalid Interface registered to the "%s" Type. Interfaces can only be registered with an interface name or a valid instance of an InterfaceType', 'wp-graphql' ),
						$this->name
					),
					[ 'invalid_interface' => $interface ]
				);
				continue;
			}

			// Prevent an interface from implementing itself
			if ( strtolower( $this->config['name'] ) === strtolower( $interface ) ) {
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
						$this->name
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
	 * Given a type it will return a string representation of the type.
	 *
	 * This is used for optimistic comparison of the arg types.
	 *
	 * @param string|array<string,mixed>|mixed $type A GraphQL Type
	 */
	private function field_arg_type_to_string( $type ): string {
		// Bail if the type is empty.
		if ( empty( $type ) ) {
			return '';
		} elseif ( is_string( $type ) ) {
			// If the type is already a string, return it as is.
			return $type;
		} elseif ( ! is_array( $type ) ) {
			// If the type is not an array, we can't do anything with it.
			return '';
		}

		// Arrays mean the type can be nested in modifiers.
		$output   = '';
		$modifier = array_keys( $type )[0];
		$type     = $type[ $modifier ];
		switch ( $modifier ) {
			case 'list_of':
				$output = '[' . $this->field_arg_type_to_string( $type ) . ']';
				break;
			case 'non_null':
				$output = '!' . $this->field_arg_type_to_string( $type );
				break;
		}

		return $output;
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
		$fields = $config['fields'];

		$fields = array_filter( $fields );

		/**
		 * Get the fields of interfaces and ensure they exist as fields of this type.
		 *
		 * Types are still responsible for ensuring the fields resolve properly.
		 */
		$interface_fields = [];

		if ( ! empty( $this->getInterfaces() ) && is_array( $this->getInterfaces() ) ) {
			foreach ( $this->getInterfaces() as $interface_type ) {
				if ( ! $interface_type instanceof InterfaceType ) {
					$interface_type = $type_registry->get_type( $interface_type );
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
		}

		// diff the $interface_fields and the $fields
		// if the field is not in $fields, add it
		$diff = ! empty( $interface_fields ) ? array_diff_key( $interface_fields, $fields ) : [];

		// If the Interface has fields defined that are not defined
		// on the Object Type, add them to the Object Type
		if ( ! empty( $diff ) ) {
			$fields = array_merge( $fields, $diff );
		}

		foreach ( $fields as $field_name => $field ) {
			$new_field = $field;

			// If the field does not have a type, attempt to inherit it from the interface.
			if ( ! isset( $new_field['type'] ) ) {
				// If the field doesn't exist in the interface, we have no way to determine (and later register) the type.
				if ( ! isset( $interface_fields[ $field_name ] ) ) {
					unset( $fields[ $field_name ] );
					continue;
				}

				$new_field['type'] = $interface_fields[ $field_name ]['type'];
			}

			// Inherit the description from the interface if it's not set on the field.
			if ( empty( $new_field['description'] ) && ! empty( $interface_fields[ $field_name ]['description'] ) ) {
				$new_field['description'] = $interface_fields[ $field_name ]['description'];
			}

			// Inherit the resolver from the interface if it's not set on the field.
			if ( empty( $new_field['resolve'] ) && ! empty( $interface_fields[ $field_name ]['resolve'] ) ) {
				$new_field['resolve'] = $interface_fields[ $field_name ]['resolve'];
			}

			// If the args aren't explicitly defined, inherit them from the interface.
			// If they're both set, we need to merge them.
			if ( empty( $new_field['args'] ) && ! empty( $interface_fields[ $field_name ]['args'] ) ) {
				$new_field['args'] = $interface_fields[ $field_name ]['args'];
			} elseif ( ! empty( $new_field['args'] ) && ! empty( $interface_fields[ $field_name ]['args'] ) ) {
				// Set field args to the interface fields to be overwrite with the new field args.
				$field_args = $interface_fields[ $field_name ]['args'];

				foreach ( $new_field['args'] as $arg_name => $arg_definition ) {
					// If the arg is not defined in the interface, we can use the current arg definition.
					if ( empty( $field_args[ $arg_name ] ) ) {
						$field_args[ $arg_name ] = $arg_definition;
						continue;
					}

					// Check if the interface arg type is different from the new field arg type.
					$new_field_arg_type = $this->field_arg_type_to_string( $arg_definition['type'] );
					$interface_arg_type = $field_args[ $arg_name ]['type']();
					if ( ! empty( $new_field_arg_type ) && $interface_arg_type !== $new_field_arg_type ) {
						graphql_debug(
							sprintf(
								/* translators: 1: Object type name, 2: Field name, 3: Argument name, 4: Expected argument type, 5: Actual argument type. */
								__(
									'Interface field argument "%1$s.%2$s(%3$s:)" expected to be of type "%4$s" but got "%5$s". Please ensure the field arguments match the interface field arguments or rename the argument.',
									'wp-graphql'
								),
								$config['name'],
								$field_name,
								$arg_name,
								$interface_arg_type,
								$new_field_arg_type
							)
						);
						continue;
					}

					// Set the field args to the new field args.
					$field_args[ $arg_name ] = array_merge( $field_args[ $arg_name ], $arg_definition );
				}

				$new_field['args'] = array_merge( $interface_fields[ $field_name ]['args'], $new_field['args'] );
			}

			// Update the field.
			$fields[ $field_name ] = $new_field;
		}

		$fields = $this->prepare_fields( $fields, $config['name'], $config );
		$fields = $type_registry->prepare_fields( $fields, $config['name'] );

		$this->fields = $fields;
		return $this->fields;
	}
}
