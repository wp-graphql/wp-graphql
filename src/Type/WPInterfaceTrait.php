<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;

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
	 * Given an array of interfaces, this gets the Interfaces the Type should implement including
	 * inherited interfaces.
	 *
	 * @return array
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
		 * @param array        $interfaces     List of interfaces applied to the Object Type
		 * @param array        $config         The config for the Object Type
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
				graphql_debug( sprintf( __( 'Invalid Interface registered to the "%s" Type. Interfaces can only be registered with an interface name or a valid instance of an InterfaceType', 'wp-graphql' ), $this->name ), [ 'invalid_interface' => $interface ] );
				continue;
			}

			// Prevent an interface from implementing itself
			if ( strtolower( $this->config['name'] ) === strtolower( $interface ) ) {
				graphql_debug( sprintf( __( 'The "%s" Interface attempted to implement itself, which is not allowed', 'wp-graphql' ), $interface ) );
				continue;
			}

			$interface_type = $this->type_registry->get_type( $interface );
			if ( ! $interface_type instanceof InterfaceType ) {
				graphql_debug( sprintf( __( '"%1$s" is not a valid Interface Type and cannot be implemented as an Interface on the "%2$s" Type', 'wp-graphql' ), $interface, $this->name ) );
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

}
