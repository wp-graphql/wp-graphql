<?php

namespace WPGraphQL;

use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Schema;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Type\WPObjectType;

/**
 * Class WPSchema
 *
 * Extends the Schema to make some properties accessible via hooks/filters
 *
 * @package WPGraphQL
 */
class WPSchema extends Schema {

	/**
	 * Holds the $filterable_config which allows WordPress access to modifying the
	 * $config that gets passed down to the Executable Schema
	 *
	 * @var array|null
	 * @since 0.0.9
	 */
	public $filterable_config;

	/**
	 * WPSchema constructor.
	 *
	 * @param array|null $config
	 *
	 * @since 0.0.9
	 */
	public function __construct( $config ) {

		/**
		 * Set the $filterable_config as the $config that was passed to the WPSchema when instantiated
		 *
		 * @since 0.0.9
		 */
		$this->filterable_config = apply_filters( 'graphql_schema_config', $config );
		// $this->check_field_permissions();

		parent::__construct( $this->filterable_config );
	}

	/**
	 * This takes in the Schema and escapes it before it's returned to the executor.
	 *
	 * @param \WPGraphQL\WPSchema $schema
	 *
	 * @return mixed
	 */
	public static function sanitize_schema( \WPGraphQL\WPSchema $schema ) {

		/**
		 * Get the prepared TypeMap
		 */
		$types = $schema->getTypeMap();

		/**
		 * Ensure there are types
		 */
		if ( ! empty( $types ) && is_array( $types ) ) {

			/**
			 * Loop through the types
			 */
			foreach ( $types as $type_name => $type_object ) {

				if ( $type_object instanceof ObjectType || $type_object instanceof WPObjectType ) {
					/**
					 * esc the values
					 */
					$sanitized_types[ $type_name ]                   = $type_object;
					$sanitized_types[ $type_name ]->name             = ucfirst( esc_html( $type_object->name ) );
					$sanitized_types[ $type_name ]->description      = esc_html( $type_object->description );
					$sanitized_fields                                = self::sanitize_fields( $type_object->getFields(), $type_name, $type_object );
					$sanitized_types[ $type_name ]->config['fields'] = $sanitized_fields;
				}
			}
		}

		/**
		 * Ensure there are $sanitized_types, and set the config's types as the sanitized types
		 */
		if ( ! empty( $sanitized_types ) && is_array( $sanitized_types ) ) {
			$schema->filterable_config['types'] = $sanitized_types;
		}

		/**
		 * Return the $schema with the sanitized types
		 */
		return $schema;

	}

	/**
	 * Sanitize Fields
	 *
	 * This sanitizes field output and provides default hooks and filters for the resolvers
	 *
	 * @param array  $fields      Array of fields for the given type
	 * @param string $type_name   The name of the Type
	 * @param object $type_object The Type definition
	 *
	 * @return mixed
	 */
	protected static function sanitize_fields( $fields, $type_name, $type_object ) {

		if ( ! empty( $fields ) && is_array( $fields ) ) {

			foreach ( $fields as $field_key => $field ) {

				if ( $field instanceof FieldDefinition ) {

					/**
					 * Capture any existing resolveFn for the field to be used later
					 */
					$field_resolver = ! empty( $field->resolveFn ) ? $field->resolveFn : null;

					/**
					 * Safely output the deprecationReason if there is one
					 */
					$field->deprecationReason = ! empty( $field->deprecationReason ) ? esc_html( $field->deprecationReason ) : '';

					/**
					 * @param mixed       $source  The source being passed down the resolve tree
					 * @param array       $args    The Input args for the field
					 * @param AppContext  $context The Context passed down the Resolve tree
					 * @param ResolveInfo $info    The ResolveInfo for this spot in the resolve tree
					 *
					 * @return callable
					 */
					$field->resolveFn = function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $field_resolver, $type_name, $type_object, $field_key, $field ) {

						/**
						 * Run an action BEFORE resolving the field. This can be useful for
						 * generating side effects, or hooking in for things like performance tracking.
						 *
						 * @param mixed       $source    The source being passed down the resolve tree
						 * @param array       $args      The Input args for the field
						 * @param AppContext  $context   The Context passed down the Resolve tree
						 * @param ResolveInfo $info      The ResolveInfo for this spot in the resolve tree
						 * @param string      $type_name The name of the Type def
						 * @param string      $field_key The name of the Field
						 * @param object      $field     The Field definition
						 */
						do_action( 'graphql_before_resolve_field', $source, $args, $context, $info, $type_name, $field_key, $field );

						/**
						 * Determine the Resolver to execute
						 */
						if ( null === $field_resolver || ! is_callable( $field_resolver ) ) {
							$resolver = Executor::defaultFieldResolver( $source, $args, $context, $info );
						} else {
							$resolver = call_user_func( $field_resolver, $source, $args, $context, $info );
						}

						/**
						 * Filter the resolver execution
						 *
						 * @param callable    $resolver  The resolve function to execute and fulfill the field's contract
						 * @param mixed       $source    The source being passed down the resolve tree
						 * @param array       $args      The Input args for the field
						 * @param AppContext  $context   The Context passed down the Resolve tree
						 * @param ResolveInfo $info      The ResolveInfo for this spot in the resolve tree
						 * @param string      $type_name The name of the Type def
						 * @param string      $field_key The name of the Field
						 * @param object      $field     The Field def
						 *
						 * @return mixed
						 * @throws UserError
						 */
						$filtered_resolver = apply_filters( 'graphql_field_resolver', $resolver, $source, $args, $context, $info, $type_name, $type_object, $field_key, $field );

						/**
						 * Run an action AFTER resolving the field. This can be useful for
						 * generating side effects, or hooking in for things like performance tracking.
						 *
						 * @param mixed           $source    The source being passed down the resolve tree
						 * @param array           $args      The Input args for the field
						 * @param AppContext      $context   The Context passed down the Resolve tree
						 * @param ResolveInfo     $info      The ResolveInfo for this spot in the resolve tree
						 * @param string          $type_name The name of the Type def
						 * @param string          $field_key The name of the Field
						 * @param FieldDefinition $field     The Field def
						 */
						do_action( 'graphql_after_resolve_field', $source, $args, $context, $info, $type_name, $type_object, $field_key, $field );

						/**
						 * Return the
						 */
						return $filtered_resolver;
					};

				}
			}

		}

		/**
		 * Return the wrapped fields
		 */
		return $fields;

	}

	/**
	 * Check permissions on fields that are configured with specific permissions
	 */
	protected function check_field_permissions() {

		/**
		 * Filter the field resolver to respect permissions on the field
		 *
		 * @param mixed       $resolver  The resolve function for the field
		 * @param mixed       $source    The source of the field being passed down the resolve tree
		 * @param array       $args      The Input args for the field
		 * @param AppContext  $context   The Context passed down the Resolve tree
		 * @param ResolveInfo $info      The ResolveInfo for this spot in the resolve tree
		 * @param string      $type_name The name of the Type def
		 * @param string      $field_key The name of the Field
		 *
		 * @return  mixed
		 * @throws UserError
		 */
		add_filter( 'graphql_field_resolver', function( $resolver, $source, array $args, AppContext $context, ResolveInfo $info, $type_name, $type_object, $field_key, FieldDefinition $field ) {

			/**
			 * If the field has "auth" config set, try and fulfill it
			 */
			if ( isset( $field->config['auth'] ) || isset( $field->config['isPrivate'] ) ) {

				/**
				 * Set the default auth error message
				 */
				$default_auth_error_message = __( 'You do not have permission to view this', 'wp-graphql' );

				/**
				 * Filter the error that should be returned if the user doesn't have permissions to get the field
				 *
				 * @param FieldDefinition $field     The field definition to check auth for
				 * @param mixed           $source    The source of the field being passed down the resolve tree
				 * @param array           $args      The Input args for the field
				 * @param AppContext      $context   The Context passed down the Resolve tree
				 * @param ResolveInfo     $info      The ResolveInfo for this spot in the resolve tree
				 * @param string          $type_name The name of the Type def
				 * @param string          $field_key The name of the Field
				 */
				$auth_error = apply_filters( 'graphql_field_resolver_auth_error_message', $default_auth_error_message, $field, $source, $args, $context, $info, $type_name, $type_object );

				/**
				 * If the auth config is a callback,
				 */
				if ( isset( $field->config['auth']['callback'] ) && is_callable( $field->config['auth']['callback'] ) ) {

					/**
					 * Execute the auth callback
					 *
					 * @param FieldDefinition $field     The field definition to check auth for
					 * @param mixed           $source    The source of the field being passed down the resolve tree
					 * @param array           $args      The Input args for the field
					 * @param AppContext      $context   The Context passed down the Resolve tree
					 * @param ResolveInfo     $info      The ResolveInfo for this spot in the resolve tree
					 * @param string          $type_name The name of the Type def
					 * @param string          $field_key The name of the Field
					 *
					 * @return mixed
					 * @throws UserError
					 */
					return call_user_func( $field->config['auth']['callback'], $resolver, $field, $source, $args, $context, $info, $type_name, $type_object );

				} else if ( ! empty( $field->config['auth']['allowedCaps'] ) && is_array( $field->config['auth']['allowedCaps'] ) ) {

					/**
					 * If the user DOESN'T have any of the allowedCaps throw the error
					 */
					if ( empty( array_intersect( array_keys( wp_get_current_user()->allcaps ), array_values( $field->config['auth']['allowedCaps'] ) ) ) ) {
						throw new UserError( $auth_error );
					}

					/**
					 * If the field auth config is set as a string, treat it as a capability
					 * and check to make sure the current user has the capability
					 */
				} else if ( ! empty( $field->config['auth']['allowedRoles'] ) && is_array( $field->config['auth']['allowedRoles'] ) ) {

					/**
					 * If the user DOESN'T have any of the allowedCaps throw the error
					 */
					if ( empty( array_intersect( array_values( wp_get_current_user()->roles ), array_values( $field->config['auth']['allowedRoles'] ) ) ) ) {
						throw new UserError( $auth_error );
					}

					/**
					 * If the field is marked as "isPrivate" make sure the request is authenticated, else throw a UserError
					 */
				} else if ( true === $field->config['isPrivate'] ) {

					/**
					 * If the field is marked as private, but no specific auth check was configured,
					 * make sure a user is authenticated, or throw an error
					 */
					if ( 0 === wp_get_current_user()->ID ) {
						throw new UserError( $auth_error );
					}

				}

			}

			/**
			 * Return the resolver
			 */
			return $resolver;

		}, 10, 9 );
	}

}
