<?php

namespace WPGraphQL\Model;

use Exception;

/**
 * Class Model - Abstract class for modeling data for all core types
 *
 * @property bool $isPrivate
 * @property bool $isPublic
 * @property bool $isRestricted
 *
 * @template TData
 */
abstract class Model {

	/**
	 * Stores the name of the type the child class extending this one represents
	 *
	 * @var string
	 */
	protected $model_name;

	/**
	 * Stores the raw data passed to the child class when it's instantiated before it's transformed
	 *
	 * @var TData
	 */
	protected $data;

	/**
	 * Stores the capability name for what to check on the user if the data should be considered
	 * "Restricted"
	 *
	 * @var string
	 */
	protected $restricted_cap;

	/**
	 * Stores the array of allowed fields to show if the data is restricted
	 *
	 * @var string[]
	 */
	protected $allowed_restricted_fields;

	/**
	 * Stores the DB ID of the user that owns this piece of data, or null if there is no owner
	 *
	 * @var int|null
	 */
	protected $owner;

	/**
	 * Stores the WP_User object for the current user in the session
	 *
	 * @var \WP_User $current_user
	 */
	protected $current_user;

	/**
	 * Stores the visibility value for the current piece of data
	 *
	 * @var string
	 */
	protected $visibility;

	/**
	 * The fields for the modeled object. This will be populated in the child class
	 *
	 * @var array<string,mixed>
	 */
	public $fields;

	/**
	 * Model constructor.
	 *
	 * @param string   $restricted_cap            The capability to check against to determine if
	 *                                            the data should be restricted or not
	 * @param string[] $allowed_restricted_fields The allowed fields if the data is in fact restricted
	 * @param int|null $owner                     Database ID of the user that owns this piece of
	 *                                            data to compare with the current user ID
	 *
	 * @return void
	 * @throws \Exception Throws Exception.
	 */
	protected function __construct( $restricted_cap = '', $allowed_restricted_fields = [], $owner = null ) {
		if ( empty( $this->data ) ) {
			// translators: %s is the name of the model.
			throw new Exception( esc_html( sprintf( __( 'An empty data set was used to initialize the modeling of this %s object', 'wp-graphql' ), $this->get_model_name() ) ) );
		}

		$this->restricted_cap            = $restricted_cap;
		$this->allowed_restricted_fields = $allowed_restricted_fields;
		$this->owner                     = $owner;
		$this->current_user              = wp_get_current_user();

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		$this->init();
		$this->prepare_fields();
	}

	/**
	 * Magic method to re-map the isset check on the child class looking for properties when
	 * resolving the fields
	 *
	 * @param string $key The name of the field you are trying to retrieve
	 *
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
	 * @return mixed|null
	 */
	public function __get( $key ) {
		if ( ! array_key_exists( $key, $this->fields ) ) {
			return null;
		}

		// If the property is a callable, we need to process it.
		if ( is_callable( $this->fields[ $key ] ) ) {
			$data       = call_user_func( $this->fields[ $key ] );
			$this->$key = $data;

			return $data;
		}

		return $this->fields[ $key ];
	}

	/**
	 * Setup the global state before each field is resolved so the Model has the necessary context.
	 *
	 * @return void
	 */
	public function setup() {
	}

	/**
	 * Tear-down call that runs after each field is resolved.
	 *
	 * This can be used to reset state to where it was before the model was setup.
	 *
	 * @return void
	 */
	public function tear_down() {
	}

	/**
	 * Returns the name of the model, built from the child className
	 *
	 * @return string
	 */
	protected function get_model_name() {
		if ( empty( $this->model_name ) ) {
			$name = static::class;

			if ( false !== strpos( static::class, '\\' ) ) {
				$starting_character = strrchr( static::class, '\\' );
				if ( ! empty( $starting_character ) ) {
					$name = substr( $starting_character, 1 );
				}
			}
			$this->model_name = $name . 'Object';
		}

		return $this->model_name;
	}

	/**
	 * Return the visibility state for the current piece of data
	 *
	 * @return string|null
	 */
	public function get_visibility() {
		if ( null === $this->visibility ) {

			/**
			 * Filter for the capability to check against for restricted data
			 *
			 * @param string      $restricted_cap The capability to check against
			 * @param string      $model_name     Name of the model the filter is currently being executed in
			 * @param TData       $data           The un-modeled incoming data
			 * @param string|null $visibility     The visibility that has currently been set for the data at this point
			 * @param int|null    $owner          The user ID for the owner of this piece of data
			 * @param \WP_User $current_user The current user for the session
			 *
			 * @return string
			 */
			$protected_cap = apply_filters( 'graphql_restricted_data_cap', $this->restricted_cap, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

			/**
			 * Filter to short circuit default is_private check for the model. This is expensive in some cases so
			 * this filter lets you prevent this from running by returning a true or false value.
			 *
			 * @param ?bool       $is_private   Whether the model data is private. Defaults to null.
			 * @param string      $model_name   Name of the model the filter is currently being executed in
			 * @param TData       $data         The un-modeled incoming data
			 * @param string|null $visibility   The visibility that has currently been set for the data at this point
			 * @param int|null    $owner        The user ID for the owner of this piece of data
			 * @param \WP_User $current_user The current user for the session
			 *
			 * @return bool|null
			 */
			$pre_is_private = apply_filters( 'graphql_pre_model_data_is_private', null, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

			// If 3rd party code has not filtered this, use the Models default logic to determine
			// whether the model should be considered private
			if ( null !== $pre_is_private ) {
				$is_private = $pre_is_private;
			} else {
				$is_private = $this->is_private();
			}

			/**
			 * Filter to determine if the data should be considered private or not
			 *
			 * @param bool        $is_private   Whether the model is private
			 * @param string      $model_name   Name of the model the filter is currently being executed in
			 * @param TData       $data         The un-modeled incoming data
			 * @param string|null $visibility   The visibility that has currently been set for the data at this point
			 * @param int|null    $owner        The user ID for the owner of this piece of data
			 * @param \WP_User    $current_user The current user for the session
			 *
			 * @return bool
			 */
			$is_private = apply_filters( 'graphql_data_is_private', (bool) $is_private, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

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
		 * @param string|null $visibility   The visibility that has currently been set for the data at this point
		 * @param string      $model_name   Name of the model the filter is currently being executed in
		 * @param TData       $data         The un-modeled incoming data
		 * @param int|null    $owner        The user ID for the owner of this piece of data
		 * @param \WP_User    $current_user The current user for the session
		 *
		 * @return string
		 */
		return apply_filters( 'graphql_object_visibility', $this->visibility, $this->get_model_name(), $this->data, $this->owner, $this->current_user );
	}

	/**
	 * Method to return the private state of the object. Can be overwritten in classes extending
	 * this one.
	 *
	 * @return bool
	 */
	protected function is_private() {
		return false;
	}

	/**
	 * Whether or not the owner of the data matches the current user
	 *
	 * @return bool
	 */
	protected function owner_matches_current_user() {
		if ( empty( $this->current_user->ID ) || empty( $this->owner ) ) {
			return false;
		}

		return absint( $this->owner ) === absint( $this->current_user->ID );
	}

	/**
	 * Restricts fields for the data to only return the allowed fields if the data is restricted
	 *
	 * @return void
	 */
	protected function restrict_fields() {
		$this->fields = array_intersect_key(
			$this->fields,
			array_flip(
			/**
			 * Filter for the allowed restricted fields
			 *
			 * @param string[]    $allowed_restricted_fields The fields to allow when the data is designated as restricted to the current user
			 * @param string      $model_name                Name of the model the filter is currently being executed in
			 * @param TData       $data                      The un-modeled incoming data
			 * @param string|null $visibility                The visibility that has currently been set for the data at this point
			 * @param int|null    $owner                     The user ID for the owner of this piece of data
			 * @param \WP_User    $current_user The current user for the session
			 */
				apply_filters( 'graphql_allowed_fields_on_restricted_type', $this->allowed_restricted_fields, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user )
			)
		);
	}

	/**
	 * Wraps all fields with another callback layer so we can inject hooks & filters into them
	 *
	 * @return void
	 */
	protected function wrap_fields() {
		if ( ! is_array( $this->fields ) || empty( $this->fields ) ) {
			return;
		}

		$clean_array = [];
		foreach ( $this->fields as $key => $data ) {
			$clean_array[ $key ] = function () use ( $key, $data ) {
				/**
				 * Filter to short circuit the callback for any field on a type.
				 *
				 * Returning anything other than null will stop the callback for the field from executing,
				 * and will return your data or execute your callback instead.
				 *
				 * @param mixed    $result       The data returned from the callback. Null by default.
				 * @param string   $key          The name of the field on the type
				 * @param string   $model_name   Name of the model the filter is currently being executed in
				 * @param TData    $data         The un-modeled incoming data
				 * @param string   $visibility   The visibility setting for this piece of data
				 * @param int|null $owner        The user ID for the owner of this piece of data
				 * @param \WP_User $current_user The current user for the session
				 */
				$pre = apply_filters( 'graphql_pre_return_field_from_model', null, $key, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

				if ( ! is_null( $pre ) ) {
					// If the pre filter returns a value, we use that instead of the callback.
					$result = $pre;
				} else {
					$result = $this->prepare_field( $key, $data );
				}

				/**
				 * Hook that fires after the data is returned for the field
				 *
				 * @param mixed    $result       The returned data for the field
				 * @param string   $key          The name of the field on the type
				 * @param string   $model_name   Name of the model the filter is currently being executed in
				 * @param TData    $data         The un-modeled incoming data
				 * @param string   $visibility   The visibility setting for this piece of data
				 * @param int|null $owner        The user ID for the owner of this piece of data
				 * @param \WP_User $current_user The current user for the session
				 */
				do_action( 'graphql_after_return_field_from_model', $result, $key, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

				return $result;
			};
		}

		$this->fields = $clean_array;
	}

	/**
	 * Prepares an individual field for the model.
	 *
	 * @param string $field_name The name of the field on the type
	 * @param TData  $field      The field data to prepare.
	 *
	 * @return TData
	 */
	private function prepare_field( string $field_name, $field ) {
		$can_access_field = $this->current_user_can_access_field( $field_name, $field );

		// If the field is an array with a 'callback', use that as the callback.
		if ( is_array( $field ) && ! empty( $field['callback'] ) ) {
			$field = $field['callback'];
		}

		// If the user doesn't have access to the field, sanitize it to null.
		if ( ! $can_access_field ) {
			$field = null;
		}

		if ( is_callable( $field ) ) {
			$this->setup();
			$field = call_user_func( $field );
			$this->tear_down();
		}

		/**
		 * Filter the data returned by the default callback for the field
		 *
		 * @param mixed    $field        The data returned from the callback
		 * @param string   $field_name   The name of the field on the type
		 * @param string   $model_name   Name of the model the filter is currently being executed in
		 * @param TData    $data         The un-modeled incoming data
		 * @param string   $visibility   The visibility setting for this piece of data
		 * @param int|null $owner        The user ID for the owner of this piece of data
		 * @param \WP_User $current_user The current user for the session
		 */
		return apply_filters( 'graphql_return_field_from_model', $field, $field_name, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );
	}

	/**
	 * Returns the capability to check for the field, or null if there is no capability set.
	 *
	 * @uses 'graphql_model_field_capability' to filter the capability to check for the field.
	 *
	 * @param string $field_name The name of the field to check
	 * @param mixed  $field The original metadata for the field.
	 */
	private function current_user_can_access_field( string $field_name, $field ): bool {
		$capability = '';

		// If the field metadata is an array, check for the capability key
		if ( is_array( $field ) && isset( $field['capability'] ) ) {
			$capability = (string) $field['capability'];
		}

		/**
		 * Capability to check required for the field
		 *
		 * @param string   $capability   The capability to check against to return the field
		 * @param string   $field_name   The name of the field on the type
		 * @param string   $model_name   Name of the model the filter is currently being executed in
		 * @param TData    $data         The un-modeled incoming data
		 * @param string   $visibility   The visibility setting for this piece of data
		 * @param int|null $owner        The user ID for the owner of this piece of data
		 * @param \WP_User $current_user The current user for the session
		 */
		$capability = apply_filters( 'graphql_model_field_capability', $capability, $field_name, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );

		if ( empty( $capability ) ) {
			return true;
		}

		// @todo add support passing capability args.
		return current_user_can( $capability );
	}

	/**
	 * Adds the model visibility fields to the data
	 */
	private function add_model_visibility(): void {

		/**
		 * @todo: potentially abstract this out into a more central spot
		 */
		$this->fields['isPublic']     = function () {
			return 'public' === $this->get_visibility();
		};
		$this->fields['isRestricted'] = function () {
			return 'restricted' === $this->get_visibility();
		};
		$this->fields['isPrivate']    = function () {
			return 'private' === $this->get_visibility();
		};
	}

	/**
	 * Returns instance of the data fully modeled
	 *
	 * @return void
	 */
	protected function prepare_fields() {
		if ( 'restricted' === $this->get_visibility() ) {
			$this->restrict_fields();
		}

		/**
		 * Add support for the deprecated "graphql_return_modeled_data" filter.
		 *
		 * @param array<string,mixed>    $fields       The array of fields for the model
		 * @param string                 $model_name   Name of the model the filter is currently being executed in
		 * @param string                 $visibility   The visibility setting for this piece of data
		 * @param ?int                   $owner        The user ID for the owner of this piece of data
		 * @param \WP_User               $current_user The current user for the session
		 *
		 * @deprecated 1.7.0 use "graphql_model_prepare_fields" filter instead, which passes additional context to the filter
		 */
		$this->fields = apply_filters_deprecated( 'graphql_return_modeled_data', [ $this->fields, $this->get_model_name(), $this->visibility, $this->owner, $this->current_user ], '1.7.0', 'graphql_model_prepare_fields' );

		/**
		 * Filter the array of fields for the Model before the object is hydrated with it
		 *
		 * @param array<string,mixed>    $fields       The array of fields for the model
		 * @param string                 $model_name   Name of the model the filter is currently being executed in
		 * @param TData                  $data         The un-modeled incoming data
		 * @param string                 $visibility   The visibility setting for this piece of data
		 * @param ?int                   $owner        The user ID for the owner of this piece of data
		 * @param \WP_User               $current_user The current user for the session
		 */
		$this->fields = apply_filters( 'graphql_model_prepare_fields', $this->fields, $this->get_model_name(), $this->data, $this->visibility, $this->owner, $this->current_user );
		$this->wrap_fields();
		$this->add_model_visibility();
	}

	/**
	 * Given a string, and optional context, this decodes html entities if html_entity_decode is
	 * enabled.
	 *
	 * @param string $str        The string to decode
	 * @param string $field_name The name of the field being encoded
	 * @param bool   $enabled    Whether decoding is enabled by default for the string passed in
	 *
	 * @return string
	 */
	public function html_entity_decode( $str, $field_name, $enabled = false ) {

		/**
		 * Determine whether html_entity_decode should be applied to the string
		 *
		 * @param bool                   $enabled    Whether decoding is enabled by default for the string passed in
		 * @param string                 $str        The string to decode
		 * @param string                 $field_name The name of the field being encoded
		 * @param \WPGraphQL\Model\Model $model      The Model the field is being decoded on
		 */
		$decoding_enabled = apply_filters( 'graphql_html_entity_decoding_enabled', $enabled, $str, $field_name, $this );

		if ( false === $decoding_enabled ) {
			return $str;
		}

		return html_entity_decode( $str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8' );
	}

	/**
	 * Filter the fields returned for the object
	 *
	 * @param string|string[]|null $fields The field or fields to build in the modeled object. Null to leave all fields.
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

	/**
	 * Initialized the object.
	 *
	 * @return void
	 */
	abstract protected function init();
}
