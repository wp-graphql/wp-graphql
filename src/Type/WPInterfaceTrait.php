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
		 * @param mixed|WPInterfaceType|WPObjectType $type The Type instance
		 */
		$interfaces = apply_filters( 'graphql_type_interfaces', $interfaces, $this->config, $this );

		if ( empty( $interfaces ) || ! is_array( $interfaces ) ) {
			return $interfaces;
		}

		// check if an interface is attempting to implement itself, and if so unset it
		$key = array_search( strtolower( $this->config['name'] ), array_map( 'strtolower', $interfaces ), true );
		if ( false !== $key ) {
			graphql_debug( sprintf( __( 'The "%s" Interface attempted to implement itself, which is not allowed', 'wp-graphql' ), $interfaces[ $key ] ) );
			unset( $interfaces[ $key ] );
		}

		$new_interfaces = [];

		foreach ( $interfaces as $interface ) {
			if ( ! is_string( $interface ) ) {
				continue;
			}

			$interface_type = $this->type_registry->get_type( $interface );
			if ( ! $interface_type instanceof InterfaceType ) {
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
