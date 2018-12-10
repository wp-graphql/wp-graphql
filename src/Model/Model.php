<?php

namespace WPGraphQL\Model;


abstract class Model {

	protected $current_user;

	protected $restricted_fields;

	protected function __construct() {

		$this->current_user = get_current_user();

	}

//	public function get_restricted_fields() {
//		if ( null === $this->restricted_fields ) {
//			$this->set_restricted_fields();
//		}
//		return $this->restricted_fields;
//	}

	//abstract function set_restricted_fields();

	protected function prepare_object( $data, $name, $object_type ) {

		$object_fields = apply_filters( 'graphql_' . $name . '_object_fields', $data, $this );

		$object = new \stdClass();

		$object = new $object_type( $object );

		if ( ! empty( $object_fields ) && is_array( $object_fields ) ) {
			foreach ( $object_fields as $key => $value ) {
				$object->{$key} = $value;
			}
		}

		/**
		 * Return the prepared object
		 */
		return $object;
	}

}
