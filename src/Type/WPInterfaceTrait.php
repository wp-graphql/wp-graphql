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

			if ( empty( $new_field['description'] ) && ! empty( $interface_fields[ $field_name ]['description'] ) ) {
				$new_field['description'] = $interface_fields[ $field_name ]['description'];
			}

			if ( ! isset( $new_field['type'] ) ) {
				if ( isset( $interface_fields[ $field_name ]['type'] ) ) {
					$new_field['type'] = $interface_fields[ $field_name ]['type'];
				} else {
					unset( $fields[ $field_name ] );
				}
			}

			// If the field has not been unset, update the field
			if ( isset( $fields[ $field_name ] ) ) {
				$fields[ $field_name ] = $new_field;
			}
		}

		$fields = $this->prepare_fields( $fields, $config['name'], $config );
		$fields = $type_registry->prepare_fields( $fields, $config['name'] );

		$this->fields = $fields;
		return $this->fields;
	}
}
