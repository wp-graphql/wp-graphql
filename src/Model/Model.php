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
	 * @param mixed    $data                      The data passed to the child class before it's
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
	 * Magic method to re-map the isset check on the child class looking for properties when
	 * resolving the fields
	 *
	 * @param string $key The name of the field you are trying to retrieve
	 *
	 * @access public
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->fields[ $key ] );
	}

	/**
	 * Magic method to re-map setting new properties to the class inside of the $fields prop rather
	 * than on the class in unique properties
	 *
	 * @param string                    $key   Name of the key to set the data to
	 * @param callable|int|string|mixed $value The value to set to the key
	 *
	 * @access public
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->fields[ $key ] = $value;
	}

	/**
	 * Magic method to re-map where external calls go to look for properties on the child objects.
	 * This is crucial to let objects modeled through this class work with the default field
	 * resolver.
	 *
	 * @param string $key Name of the property that is trying to be accessed
	 *
	 * @access public
	 * @return mixed|null
	 */
	public function __get( $key ) {
		if ( ! empty( $this->fields[ $key ] ) ) {
			if ( is_callable( $this->fields[ $key ] ) ) {
				$data = call_user_func( $this->fields[ $key ] );
				$this->$key = $data;
				return $data;
			} else {
				return $this->fields[ $key ];
			}
		} else {
			return null;
		}
	}

	/**
	 * Return the visibility state for the current piece of data
	 *
	 * @return string
	 * @access protected
	 */
	public function get_visibility() {

		if ( null === $this->visibility ) {

			/**
			 * Filter for the capability to check against for restricted data
			 *
			 * @param string      $restricted_cap The capability to check against
			 * @param string      $model_name     Name of the model the filter is currently being executed in
			 * @param mixed       $data           The un-modeled incoming data
			 * @param string|null $visibility     The visibility that has currently been set for the data at this point
			 * @param null|int    $owner          The user ID for the owner of this piece of data
			 * @param \WP_User    $current_user   The current user for the session
			 *
			 * @return string
			 */
			$protected_cap = apply_filters( 'graphql_restricted_data_cap', $this->restricted_cap, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );

			/**
			 * Filter to determine if the data should be considered private or not
			 *
			 * @param string      $model_name     Name of the model the filter is currently being executed in
			 * @param mixed       $data           The un-modeled incoming data
			 * @param string|null $visibility     The visibility that has currently been set for the data at this point
			 * @param null|int    $owner          The user ID for the owner of this piece of data
			 * @param \WP_User    $current_user   The current user for the session
			 *
			 * @return bool
			 */
			$is_private = apply_filters( 'graphql_data_is_private', false, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );

			if ( true === $is_private ) {
				$this->visibility = 'private';
			} else if ( null !== $this->owner && true === $this->owner_matches_current_user() ) {
				$this->visibility = 'public';
			} else if ( empty( $protected_cap ) || current_user_can( $protected_cap ) ) {
				$this->visibility = 'public';
			} else {
				$this->visibility = 'restricted';
			}

		}

		/**
		 * Filter the visibility name to be returned
		 *
		 * @param string|null $visibility     The visibility that has currently been set for the data at this point
		 * @param string      $model_name     Name of the model the filter is currently being executed in
		 * @param mixed       $data           The un-modeled incoming data
		 * @param null|int    $owner          The user ID for the owner of this piece of data
		 * @param \WP_User    $current_user   The current user for the session
		 *
		 * @return string
		 */
		return apply_filters( 'graphql_object_visibility', $this->visibility, $this->model_name, $this->data, $this->owner, $this->current_user );

	}

	/**
	 * Whether or not the owner of the data matches the current user
	 *
	 * @return bool
	 * @access private
	 */
	protected function owner_matches_current_user() {
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

			/**
			 * Filter for the allowed restricted fields
			 *
			 * @param array       $allowed_restricted_fields The fields to allow when the data is designated as restricted to the current user
			 * @param string      $model_name                Name of the model the filter is currently being executed in
			 * @param mixed       $data                      The un-modeled incoming data
			 * @param string|null $visibility                The visibility that has currently been set for the data at this point
			 * @param null|int    $owner                     The user ID for the owner of this piece of data
			 * @param \WP_User    $current_user              The current user for the session
			 *
			 * @return array
			 */
			apply_filters( 'graphql_allowed_fields_on_restricted_type', $this->allowed_restricted_fields, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user )
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
	protected function wrap_fields( $fields ) {

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $fields;
		}

		$clean_array = [];
		foreach ( $fields as $key => $callback ) {

			$clean_array[ $key ] = function() use ( $key, $callback ) {

				/**
				 * Filter to short circuit the callback for any field on a type. Returning anything
				 * other than null will stop the callback for the field from executing, and will
				 * return your data or execute your callback instead.
				 *
				 * @param string   $key          The name of the field on the type
				 * @param string   $model_name   Name of the model the filter is currently being executed in
				 * @param mixed    $data         The un-modeled incoming data
				 * @param string   $visibility   The visibility setting for this piece of data
				 * @param null|int $owner        The user ID for the owner of this piece of data
				 * @param \WP_User $current_user The current user for the session
				 *
				 * @return null|callable|int|string|array|mixed
				 */
				$pre = apply_filters( 'graphql_pre_return_field_from_model', null, $key, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );

				if ( ! is_null( $pre ) ) {
					$result = $pre;
				} else {
					if ( is_callable( $callback ) ) {
						$field = call_user_func( $callback );
					} else {
						$field = $callback;
					}

					/**
					 * Filter the data returned by the default callback for the field
					 *
					 * @param string   $field        The data returned from the callback
					 * @param string   $key          The name of the field on the type
					 * @param string   $model_name   Name of the model the filter is currently being executed in
					 * @param mixed    $data         The un-modeled incoming data
					 * @param string   $visibility   The visibility setting for this piece of data
					 * @param null|int $owner        The user ID for the owner of this piece of data
					 * @param \WP_User $current_user The current user for the session
					 *
					 * @return mixed
					 */
					$result = apply_filters( 'graphql_return_field_from_model', $field, $key, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );
				}

				/**
				 * Hook that fires after the data is returned for the field
				 *
				 * @param string   $result       The returned data for the field
				 * @param string   $key          The name of the field on the type
				 * @param string   $model_name   Name of the model the filter is currently being executed in
				 * @param mixed    $data         The un-modeled incoming data
				 * @param string   $visibility   The visibility setting for this piece of data
				 * @param null|int $owner        The user ID for the owner of this piece of data
				 * @param \WP_User $current_user The current user for the session
				 */
				do_action( 'graphql_after_return_field_from_model', $result, $key, $this->model_name, $this->data, $this->visibility, $this->owner, $this->current_user );

				return $result;
			};
		}

		return $clean_array;

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
		$fields['isPublic']     = function() { return ( 'public' === $this->get_visibility() ) ? true : false;};
		$fields['isRestricted'] = function() { return ( 'restricted' === $this->get_visibility() ) ? true : false; };
		$fields['isPrivate']    = function() { return ( 'private' === $this->get_visibility() ) ? true : false; };

		return $fields;

	}

	/**
	 * Returns instance of the data fully modeled
	 *
	 * @param array $data The data with field definitions to be modeled
	 * @param null  $filter The fields to pluck from the instance of data
	 *
	 * @access protected
	 * @return array
	 */
	protected function prepare_fields( $data, $filter = null ) {

		if ( 'restricted' === $this->get_visibility() ) {
			$data = $this->restrict_fields( $data );
		}

		if ( is_string( $filter ) ) {
			$filter = [ $filter ];
		}

		if ( is_array( $filter ) ) {
			$data = array_intersect_key( $data, array_flip( $filter ) );
		}

		/**
		 * Filter the array of fields for the Model before the object is hydrated with it
		 *
		 * @param array    $data         The array of fields for the model
		 * @param string   $model_name   Name of the model the filter is currently being executed in
		 * @param string   $visibility   The visibility setting for this piece of data
		 * @param null|int $owner        The user ID for the owner of this piece of data
		 * @param \WP_User $current_user The current user for the session
		 *
		 * @return array
		 */
		$data = apply_filters( 'graphql_return_modeled_data', $data, $this->model_name, $this->visibility, $this->owner, $this->current_user );
		$data = $this->wrap_fields( $data );
		$data = $this->add_model_visibility( $data );

		return $data;

	}

	/**
	 * Method to initialize the object
	 *
	 * @param null|array|string $fields Options to filter the result by returning a subset of
	 *                                  fields or a single field from the model
	 *
	 * @abstract
	 * @return mixed
	 */
	abstract function init( $fields = null );

}
