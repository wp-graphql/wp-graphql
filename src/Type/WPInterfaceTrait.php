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
	 * @param array $interfaces Array of interfaces the type implements
	 *
	 * @return array
	 */
	protected function get_implemented_interfaces( array $interfaces ) {

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

			if ( ! is_array( $interface_interfaces ) || empty( $interface_interfaces ) ) {
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
