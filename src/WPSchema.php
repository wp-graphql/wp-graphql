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
		$sanitized_types = [];
		$types           = $schema->getTypeMap();

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
					$sanitized_type                   = $type_object;
					$sanitized_type->name             = ucfirst( esc_html( $type_object->name ) );
					$sanitized_type->description      = esc_html( $type_object->description );
					$sanitized_fields                 = self::sanitize_fields( $type_object->getFields(), $type_name, $type_object );
					$sanitized_type->config['fields'] = $sanitized_fields;
					$sanitized_types[ $type_name ]    = $sanitized_type;
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
	 * @param array  $fields    Array of fields for the given type
	 * @param string $type_name The name of the Type
	 *
	 * @return mixed
	 */
	protected static function sanitize_fields( $fields, $type_name, $type_object ) {

		if ( ! empty( $fields ) && is_array( $fields ) ) {

			foreach ( $fields as $field_key => $field ) {

				if ( $field instanceof FieldDefinition ) {

					/**
					 * Safely output the deprecationReason if there is one
					 */
					$field->deprecationReason = ! empty( $field->deprecationReason ) ? esc_html( $field->deprecationReason ) : '';
					$field->description = ! empty( $field->description ) ? esc_html( $field->description ) : '';

				}
			}

		}

		/**
		 * Return the wrapped fields
		 */
		return $fields;

	}

}
