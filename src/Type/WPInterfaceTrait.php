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
	protected function get_implemented_interfaces( array $interfaces ): array{

		$new_interfaces = [];

		if ( empty( $interfaces ) ) {
			return $new_interfaces;
		}

		foreach ( $interfaces as $interface ) {
			if ( is_string( $interface ) ) {
				$interface_type = $this->type_registry->get_type( $interface );
				if ( $interface_type instanceof InterfaceType ) {

					$interface_interfaces = $interface_type->getInterfaces();

					if ( ! empty( $interface_interfaces ) ) {
						foreach ( $interface_interfaces as $interface_interface_name => $interface_interface ) {
							$new_interfaces[ $interface_interface_name ] = $interface_interface;
						}
					}

					$new_interfaces[ $interface ] = $interface_type;
				}
				continue;
			}
			if ( $interface instanceof InterfaceType ) {
				$new_interfaces[ get_class( $interface ) ] = $interface;
			}
		}

		return array_unique( $new_interfaces );

	}

}
