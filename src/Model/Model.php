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
	 * The fields for the modeled object. This will be populated in the child class
	 *
	 * @var array $fields
	 * @access public
	 */
	public $fields;

	/**
	 * Model constructor.
	 *
	 * @param string   $restricted_cap            The capability to check against to determine if
	 *                                            the data should be restricted or not
	 * @param array    $allowed_restricted_fields The allowed fields if the data is in fact restricted
	 * @param null|int $owner                     Database ID of the user that owns this piece of
	 *                                            data to compare with the current user ID
	 *
	 * @access protected
	 * @return void
	 * @throws \Exception
	 */
	protected function __construct( $restricted_cap = '', $allowed_restricted_fields = [], $owner = null ) {

		if ( empty( $this->data ) ) {
			throw new \Exception( sprintf( __( 'An empty data set was used to initialize the modeling of this %s object', 'wp-graphql' ), $this->get_model_name() ) );
		}

		$this->restricted_cap            = $restricted_cap;
		$this->allowed_restricted_fields = $allowed_restricted_fields;
		$this->owner                     = $owner;
		$this->current_user              = wp_get_current_user();

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		$this->init();
		self::prepare_fields();

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
				$data       = call_user_func( $this->fields[ $key ] );
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
	 * Generic model setup before the resolver function executes
	 */
	public function setup() {
	}

	/**
	 * Returns the name of the model, built from the child className
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_model_name() {

		if ( empty( $this->model_name ) ) {
			if ( false !== strpos( static::class, '\\' ) ) {
				$name = substr( strrchr( static::class, '\\' ), 1 );
			} else {
				$name = static::class;
			}
			$this->model_name = $name . 'Object';
		}

		return $this->model_name;

	}

	/**
	 * Return the visibility state for the current piece of data
	 *
	 * @return string
	 * @access public
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
			$protected_cap = apply_filters( 'graphql_restricted_data_cap', $this->restricted_cap, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

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
			$is_private = apply_filters( 'graphql_data_is_private', $this->is_private(), $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

			if ( true === $is_private ) {
				$this->visibility = 'private';
			} elseif ( null !== $this->owner && true === $this->owner_matches_current_user() ) {
				$this->visibility = 'public';
			} elseif ( empty( $protected_cap ) || current_user_can( $protected_cap ) ) {
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
		return apply_filters( 'graphql_object_visibility', $this->visibility, $this->get_model_name(), $this->data, $this->owner, $this->current_user );

	}

	/**
	 * Method to return the private state of the object. Can be overwritten in classes extending
	 * this one.
	 *
	 * @access protected
	 * @return bool
	 */
	protected function is_private() {
		return false;
	}

	/**
	 * Whether or not the owner of the data matches the current user
	 *
	 * @return bool
	 * @access protected
	 */
	protected function owner_matches_current_user() {
		if ( empty( $this->current_user->ID ) || empty( $this->owner ) ) {
			return false;
		}
		return ( absint( $this->owner ) === absint( $this->current_user->ID ) ) ? true : false;
	}

	/**
	 * Restricts fields for the data to only return the allowed fields if the data is restricted
	 *
	 * @access protected
	 * @return void
	 */
	protected function restrict_fields() {
		$this->fields = array_intersect_key(
			$this->fields,
			array_flip(

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
				apply_filters( 'graphql_allowed_fields_on_restricted_type', $this->allowed_restricted_fields, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user )
			)
		);
	}

	/**
	 * Wraps all fields with another callback layer so we can inject hooks & filters into them
	 *
	 * @access protected
	 * @return void
	 */
	protected function wrap_fields() {

		if ( ! is_array( $this->fields ) || empty( $this->fields ) ) {
			return;
		}

		$clean_array = [];
		$self        = $this;
		foreach ( $this->fields as $key => $data ) {

			$clean_array[ $key ] = function() use ( $key, $data, $self ) {
				$self->setup();

				if ( is_array( $data ) ) {
					$callback = ( ! empty( $data['callback'] ) ) ? $data['callback'] : null;

					/**
					 * Capability to check required for the field
					 *
					 * @param string   $capability   The capability to check against to return the field
					 * @param string   $key          The name of the field on the type
					 * @param string   $model_name   Name of the model the filter is currently being executed in
					 * @param mixed    $data         The un-modeled incoming data
					 * @param string   $visibility   The visibility setting for this piece of data
					 * @param null|int $owner        The user ID for the owner of this piece of data
					 * @param \WP_User $current_user The current user for the session
					 *
					 * @return string
					 */
					$cap_check = ( ! empty( $data['capability'] ) ) ? apply_filters( 'graphql_model_field_capability', $data['capability'], $key, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user ) : '';
					if ( ! empty( $cap_check ) ) {
						if ( ! current_user_can( $data['capability'] ) ) {
							$callback = null;
						}
					}
				} else {
					$callback = $data;
				}

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
				$pre = apply_filters( 'graphql_pre_return_field_from_model', null, $key, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

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
					$result = apply_filters( 'graphql_return_field_from_model', $field, $key, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );
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
				do_action( 'graphql_after_return_field_from_model', $result, $key, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

				return $result;
			};
		}

		$this->fields = $clean_array;

	}

	/**
	 * Adds the model visibility fields to the data
	 *
	 * @return void
	 */
	private function add_model_visibility() {

		/**
		 * @TODO: potentially abstract this out into a more central spot
		 */
		$this->fields['isPublic']     = function() {
			return ( 'public' === $this->get_visibility() ) ? true : false;
		};
		$this->fields['isRestricted'] = function() {
			return ( 'restricted' === $this->get_visibility() ) ? true : false;
		};
		$this->fields['isPrivate']    = function() {
			return ( 'private' === $this->get_visibility() ) ? true : false;
		};

	}

	/**
	 * Returns instance of the data fully modeled
	 *
	 * @access protected
	 * @return void
	 */
	protected function prepare_fields() {

		if ( 'restricted' === $this->get_visibility() ) {
			$this->restrict_fields();
		}

		/**
		 * Filter the array of fields for the Model before the object is hydrated with it
		 *
		 * @param array    $fields       The array of fields for the model
		 * @param string   $model_name   Name of the model the filter is currently being executed in
		 * @param string   $visibility   The visibility setting for this piece of data
		 * @param null|int $owner        The user ID for the owner of this piece of data
		 * @param \WP_User $current_user The current user for the session
		 *
		 * @return array
		 */
		$this->fields = apply_filters( 'graphql_return_modeled_data', $this->fields, $this->get_model_name(), $this->visibility, $this->owner, $this->current_user );
		$this->wrap_fields();
		$this->add_model_visibility();

	}

	/**
	 * Filter the fields returned for the object
	 *
	 * @param null|string|array $fields The field or fields to build in the modeled object. You can
	 *                                  pass null to build all of the fields, a string to only
	 *                                  build an object with one field, or an array of field keys
	 *                                  to build an object with those keys and their respective
	 *                                  values.
	 *
	 * @access public
	 * @return void
	 */
	public function filter( $fields ) {

		if ( is_string( $fields ) ) {
			$fields = [ $fields ];
		}

		if ( is_array( $fields ) ) {
			$this->fields = array_intersect_key( $this->fields, array_flip( $fields ) );
		}

	}

	abstract protected function init();

}
