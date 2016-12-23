<?php
namespace DFM\WPGraphQL\Utils;

class Fields {

	/**
	 * unsetFieldByName
	 *
	 * Removes a field from an array of fields based on the fields name
	 *
	 * @since 0.0.2
	 * @param $fields
	 * @param $name
	 *
	 * @return mixed
	 */
	public function unsetFieldByName( $fields, $name ) {

		$found = false;

		foreach( $fields as $key => $value ) {

			if ( $value->getName() === $name ) {
				$found = true;
				break;
			}
		}

		if ( true === $found ) {
			unset( $fields[ $key ] );
		}

		return $fields;

	}

}