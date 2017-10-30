<?php

namespace WPGraphQL;

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
						 * @param object      $field     The Field def
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
						 */
						$filtered_resolver = apply_filters( 'graphql_resolve_field', $resolver, $source, $args, $context, $info, $type_name, $type_object, $field_key, $field );

						/**
						 * Run an action AFTER resolving the field. This can be useful for
						 * generating side effects, or hooking in for things like performance tracking.
						 *
						 * @param mixed       $source    The source being passed down the resolve tree
						 * @param array       $args      The Input args for the field
						 * @param AppContext  $context   The Context passed down the Resolve tree
						 * @param ResolveInfo $info      The ResolveInfo for this spot in the resolve tree
						 * @param string      $type_name The name of the Type def
						 * @param string      $field_key The name of the Field
						 * @param object      $field     The Field def
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

		return $fields;

	}

}
