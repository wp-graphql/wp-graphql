<?php

namespace WPGraphQL\Model;

/**
 * Class Model - Abstract class for modeling data for all core types
 *
 * @package WPGraphQL\Model
 */
abstract class Model {

	/**
	 * Stores the name of the type the child class extending this one represents
	 *
	 * @var string $model_name
	 * @access protected
	 */
	protected $model_name;

	/**
	 * Stores the raw data passed to the child class when it's instantiated before it's transformed
	 *
	 * @var array $data
	 * @access protected
	 */
	protected $data;

	/**
	 * Stores the capability name for what to check on the user if the data should be considered "Restricted"
	 *
	 * @var string $restricted_cap
	 * @access protected
	 */
	protected $restricted_cap;

	/**
	 * Stores the array of allowed fields to show if the data is restricted
	 *
	 * @var array $allowed_restricted_fields
	 * @access protected
	 */
	protected $allowed_restricted_fields;

	/**
	 * Stores the DB ID of the user that owns this piece of data, or null if there is no owner
	 *
	 * @var int|null $owner
	 * @access protected
	 */
	protected $owner;

	/**
	 * Stores the WP_User object for the current user in the session
	 *
	 * @var \WP_User $current_user
	 * @access protected
	 */
	protected $current_user;

	/**
	 * Stores the visibility value for the current piece of data
	 *
	 * @var string
	 * @access protected
	 */
	protected $visibility;

	/**
	 * Model constructor.
	 *
	 * @param string   $name                      Name of the data being passed in for hook/filter context
	 * @param array    $data                      The data passed to the child class before it's
	 *                                            transformed for hook/filter context
	 * @param string   $restricted_cap            The capability to check against to determine if
	 *                                            the data should be restricted or not
	 * @param array    $allowed_restricted_fields The allowed fields if the data is in fact restricted
	 * @param null|int $owner                     Database ID of the user that owns this piece of
	 *                                            data to compare with the current user ID
	 *
	 * @access protected
	 * @return void
	 */
	protected function __construct( $name, $data, $restricted_cap = '', $allowed_restricted_fields = [], $owner = null ) {
		$this->model_name = $name;
		$this->data = $data;
		$this->restricted_cap = $restricted_cap;
		$this->allowed_restricted_fields = $allowed_restricted_fields;
		$this->owner = $owner;
		$this->current_user = wp_get_current_user();
	}

	/**
	 * Return the visibility state for the current piece of data
	 *
	 * @return string
	 * @access protected
	 */
	protected function get_visibility() {

		if ( null === $this->visibility ) {

			$protected_cap = apply_filters( 'graphql_protected_data_cap', $this->restricted_cap, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );
			$is_private = apply_filters( 'graphql_data_is_private', false, $this->data, $this->model_name, $this->owner, $this->current_user );

			if ( null !== $this->owner && true === $this->owner_matches_current_user() ) {
				$this->visibility = 'public';
			} else if ( empty( $protected_cap ) || current_user_can( $protected_cap ) ) {
				$this->visibility = 'public';
			} else if ( true === $is_private ) {
				$this->visibility = 'private';
			} else {
				$this->visibility = 'restricted';
			}

		}

		return apply_filters( 'graphql_object_visibility', $this->visibility, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );

	}

	/**
	 * Whether or not the owner of the data matches the current user
	 *
	 * @return bool
	 * @access private
	 */
	private function owner_matches_current_user() {
		return ( $this->owner === $this->current_user->ID ) ? true : false;
	}

	/**
	 * Restricts fields for the data to only return the allowed fields if the data is restricted
	 *
	 * @param array $fields Fields for the data
	 *
	 * @access protected
	 * @return array
	 */
	protected function restrict_fields( $fields ) {
		return array_intersect_key( $fields, array_flip(
			apply_filters( 'graphql_allowed_field_on_restricted_type', $this->allowed_restricted_fields, $this->model_name, $this->current_user )
		) );
	}

	/**
	 * Wraps all fields with another callback layer so we can inject hooks & filters into them
	 *
	 * @param array $fields Fields for the data
	 *
	 * @access protected
	 * @return array
	 */
	protected function prepare_fields( $fields ) {

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $fields;
		}

		$clean_array = [];
		foreach ( $fields as $key => $callback ) {
			$clean_array[ $key ] = $this->return_field( $key, $callback );
		}

		return $clean_array;

	}

	/**
	 * Callback wrapper for the return field
	 *
	 * @param string   $field_name Name of the field
	 * @param callable $callback   The callback to resolve the data
	 *
	 * @access private
	 * @return mixed
	 */
	private function return_field( $field_name, $callback ) {

		/**
		 * @TODO: Revisit all of the filter/hook context here.
		 * @TODO: Also rethink how we are returning the callback here. We can't really filter the _results_ of the callback, only override the callback
		 */
		$pre = apply_filters( 'graphql_pre_return_field_from_model', null, $field_name, $this->model_name );

		if ( ! is_null( $pre ) ) {
			$result = $pre;
		} else {
			$result = apply_filters( 'graphql_return_field_from_model', $callback, $field_name, $this->model_name );
		}

		do_action( 'graphql_after_return_field_from_model', $result, $field_name, $this->model_name );

		return $result;

	}

	/**
	 * Adds the model visibility fields to the data
	 *
	 * @param array $fields Field definitions for the data
	 *
	 * @return mixed
	 */
	private function add_model_visibility( $fields ) {

		/**
		 * @TODO: potentially abstract this out into a more central spot
		 */
		$fields['isPublic'] = function() { return ( 'public' === $this->get_visibility() ) ? true : false; };
		$fields['isRestricted'] = function() { return ( 'restricted' === $this->get_visibility() ) ? true : false; };
		$fields['isPrivate'] = function() { return ( 'private' === $this->get_visibility() ) ? true : false; };
		return $fields;

	}

	/**
	 * Method to retrieve the instance of the data
	 *
	 * @param null|array|string $fields Options to filter the result by returning a subset of
	 *                                  fields or a single field from the model
	 *
	 * @abstract
	 * @return mixed
	 */
	abstract function get_instance( $fields = null );

	/**
	 * Returns instance of the data fully modeled
	 *
	 * @param array $data The data with field definitions to be modeled
	 * @param null  $fields The fields to pluck from the instance of data
	 *
	 * @access protected
	 * @return array
	 */
	protected function return_instance( $data, $fields = null ) {

		if ( 'restricted' === $this->get_visibility() ) {
			$data = $this->restrict_fields( $data );
		}

		if ( is_string( $fields ) ) {
			$fields = [ $fields ];
		}

		if ( is_array( $fields ) ) {
			$data = array_intersect_key( $data, array_flip( $fields ) );
		}

		$data = $this->prepare_fields( $data );
		$data = $this->add_model_visibility( $data );

		return apply_filters( 'graphql_return_modeled_data', $data, $this->model_name, $this->owner, $this->current_user );

	}

}
