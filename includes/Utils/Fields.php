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

	/**
	 * format_field_name
	 *
	 * Formats the Field Name to make sure there are no spaces or special characters
	 * as GraphQL will barf if there are.
	 *
	 * @param $query_name
	 * @return mixed
	 */
	public function format_field_name( $query_name ) {

		$query_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $query_name );
		$query_name = preg_replace( '/[^A-Za-z0-9]/i', '',  ucwords( $query_name ) );

		return $query_name;

	}

}